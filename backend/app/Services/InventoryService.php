<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Inventory;
use App\Events\StockDeducted;
use App\Events\StockReplenished;
use App\Models\InventoryTransactionModel;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;

/**
 * InventoryService — 庫存核心業務邏輯
 *
 * 所有異動操作均包在 DB Transaction 內，
 * 成功後觸發對應 Domain Event。
 */
class InventoryService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepo,
        private readonly ItemRepositoryInterface      $itemRepo,
        private readonly InventoryTransactionModel    $txModel,
    ) {
        $this->db = \Config\Database::connect();
    }

    /**
     * 出庫（銷售出貨、手動扣減）
     *
     * @throws \DomainException  庫存不足時
     * @throws \RuntimeException DB 錯誤時
     */
    public function deductStock(
        int    $skuId,
        int    $warehouseId,
        float  $qty,
        string $sourceType,
        int    $sourceId,
        int    $operatorId,
        bool   $allowNegative = false,
    ): Inventory {
        $this->db->transStart();

        // SELECT FOR UPDATE 避免 race condition
        $inventory = $this->inventoryRepo->findAndLock($skuId, $warehouseId);

        if ($inventory === null) {
            throw new \RuntimeException("找不到 SKU #{$skuId} 在倉庫 #{$warehouseId} 的庫存記錄");
        }

        $inventory->deductStock($qty, $allowNegative);  // 可能拋 DomainException

        $this->inventoryRepo->save($inventory);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('庫存扣減 DB 交易失敗');
        }

        // 觸發 Domain Event（Transaction 提交後）
        Events::trigger('stock.deducted', new StockDeducted(
            skuId:              $skuId,
            warehouseId:        $warehouseId,
            deductedQuantity:   $qty,
            remainingOnHand:    $inventory->on_hand_qty,
            remainingAvailable: $inventory->getAvailableQty(),
            sourceType:         $sourceType,
            sourceId:           $sourceId,
            operatorId:         $operatorId,
            occurredAt:         date('Y-m-d H:i:s'),
        ));

        return $inventory;
    }

    /**
     * 入庫（採購到貨、盤盈、移倉入）
     */
    public function replenishStock(
        int    $skuId,
        int    $warehouseId,
        float  $qty,
        float  $unitCost,
        string $sourceType,
        int    $sourceId,
        int    $operatorId,
    ): Inventory {
        $this->db->transStart();

        $inventory = $this->inventoryRepo->findAndLock($skuId, $warehouseId);

        if ($inventory === null) {
            // 首次入庫：建立新的庫存記錄
            $inventory = new Inventory();
            $inventory->fill([
                'sku_id'       => $skuId,
                'warehouse_id' => $warehouseId,
                'on_hand_qty'  => 0,
                'reserved_qty' => 0,
                'on_order_qty' => 0,
                'avg_cost'     => 0,
            ]);
        }

        $inventory->addStock($qty, $unitCost);

        $this->inventoryRepo->save($inventory);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('庫存入庫 DB 交易失敗');
        }

        Events::trigger('stock.replenished', new StockReplenished(
            skuId:         $skuId,
            warehouseId:   $warehouseId,
            addedQuantity: $qty,
            newOnHand:     $inventory->on_hand_qty,
            unitCost:      $unitCost,
            sourceType:    $sourceType,
            sourceId:      $sourceId,
            operatorId:    $operatorId,
            occurredAt:    date('Y-m-d H:i:s'),
        ));

        return $inventory;
    }

    /**
     * 預留庫存（銷售單確認時）
     */
    public function reserveStock(int $skuId, int $warehouseId, float $qty): Inventory
    {
        $this->db->transStart();

        $inventory = $this->inventoryRepo->findAndLock($skuId, $warehouseId);

        if ($inventory === null) {
            throw new \RuntimeException("找不到 SKU #{$skuId} 在倉庫 #{$warehouseId} 的庫存記錄");
        }

        $inventory->reserve($qty);
        $this->inventoryRepo->save($inventory);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('庫存預留 DB 交易失敗');
        }

        return $inventory;
    }

    /**
     * 取消預留（銷售單取消時）
     */
    public function releaseReservation(int $skuId, int $warehouseId, float $qty): Inventory
    {
        $this->db->transStart();

        $inventory = $this->inventoryRepo->findAndLock($skuId, $warehouseId);

        if ($inventory === null) {
            throw new \RuntimeException("找不到 SKU #{$skuId} 在倉庫 #{$warehouseId} 的庫存記錄");
        }

        $inventory->releaseReservation($qty);
        $this->inventoryRepo->save($inventory);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('取消庫存預留 DB 交易失敗');
        }

        return $inventory;
    }

    /**
     * 取得低於安全庫存的品項列表
     *
     * @return Inventory[]
     */
    public function getLowStockItems(?int $warehouseId = null): array
    {
        return $this->inventoryRepo->findBelowSafetyStock($warehouseId);
    }

    /**
     * 查詢全部庫存（支援 warehouse_id / sku_id 過濾 + 分頁）
     *
     * @return array{data: Inventory[], total: int}
     */
    public function getAllInventory(array $criteria = [], array $options = []): array
    {
        $page    = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['per_page'] ?? 20);
        $offset  = ($page - 1) * $perPage;

        $db = $this->txModel->db;

        // 取得筆數（獨立 builder，避免和 data query 共用狀態）
        $countBuilder = $db->table('inventory i')
            ->join('item_skus', 'item_skus.id = i.sku_id', 'left')
            ->join('items',     'items.id = item_skus.item_id', 'left')
            ->join('warehouses', 'warehouses.id = i.warehouse_id', 'left');

        if (!empty($criteria['warehouse_id'])) {
            $countBuilder->where('i.warehouse_id', (int) $criteria['warehouse_id']);
        }
        if (!empty($criteria['sku_id'])) {
            $countBuilder->where('i.sku_id', (int) $criteria['sku_id']);
        }

        $total = (int) $countBuilder->countAllResults();

        // 取得資料（再建一個 builder）
        $dataBuilder = $db->table('inventory i')
            ->select('i.*, item_skus.sku_code, items.name as item_name, warehouses.name as warehouse_name')
            ->join('item_skus', 'item_skus.id = i.sku_id', 'left')
            ->join('items',     'items.id = item_skus.item_id', 'left')
            ->join('warehouses', 'warehouses.id = i.warehouse_id', 'left');

        if (!empty($criteria['warehouse_id'])) {
            $dataBuilder->where('i.warehouse_id', (int) $criteria['warehouse_id']);
        }
        if (!empty($criteria['sku_id'])) {
            $dataBuilder->where('i.sku_id', (int) $criteria['sku_id']);
        }

        $rows = $dataBuilder->limit($perPage, $offset)->get()->getResultArray();

        $entities = array_map(static function (array $row): Inventory {
            $e = new Inventory();
            $e->fill($row);
            return $e;
        }, $rows);

        return ['data' => $entities, 'total' => $total];
    }

    /**
     * 手動調整庫存（盤點差值、後台修正）
     *
     * @throws \RuntimeException
     */
    public function adjustStock(
        int    $skuId,
        int    $warehouseId,
        float  $qty,
        string $reason,
        int    $operatorId,
    ): Inventory {
        $this->db->transStart();

        $inventory = $this->inventoryRepo->findAndLock($skuId, $warehouseId);

        if ($inventory === null) {
            // 首次調整視為首次入庫
            $inventory = new Inventory();
            $inventory->fill([
                'sku_id'       => $skuId,
                'warehouse_id' => $warehouseId,
                'on_hand_qty'  => 0,
                'reserved_qty' => 0,
                'on_order_qty' => 0,
                'avg_cost'     => 0,
            ]);
        }

        // 記錄舊值（必須在修改前讀取）
        $oldQty = (float) ($inventory->attributes['on_hand_qty'] ?? 0);

        // qty 代表調整後的絕對在庫數量
        $inventory->attributes['on_hand_qty'] = $qty;

        $this->inventoryRepo->save($inventory);

        // 記錄調整流水
        $this->txModel->insert([
            'sku_id'       => $skuId,
            'warehouse_id' => $warehouseId,
            'tx_type'      => 'ADJUST',
            'qty_change'   => $qty - $oldQty,
            'qty_after'    => $qty,
            'source_type'  => 'manual',
            'source_id'    => 0,
            'operator_id'  => $operatorId,
            'note'         => $reason,
            'occurred_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('庫存調整 DB 交易失敗');
        }

        return $inventory;
    }
}
