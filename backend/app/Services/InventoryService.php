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
}
