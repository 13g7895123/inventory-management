<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Stocktake;
use App\Entities\StocktakeLine;
use App\Models\StocktakeModel;
use App\Models\StocktakeLineModel;
use CodeIgniter\Database\BaseConnection;

/**
 * StocktakeService — 盤點業務邏輯
 *
 * 盤點流程：
 *   1. create()   → 建立盤點單（draft），快照當前庫存為 system_qty
 *   2. start()    → 切換為 in_progress（開始實際盤點）
 *   3. updateCount() → 錄入各 SKU 的 actual_qty
 *   4. confirm()  → 確認盤點結果 → 對有差異的品項呼叫 inventoryService->adjustStock()
 *   5. cancel()   → 取消（draft 或 in_progress 皆可）
 */
class StocktakeService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly StocktakeModel     $stocktakeModel,
        private readonly StocktakeLineModel $lineModel,
        private readonly InventoryService   $inventoryService,
    ) {
        $this->db = \Config\Database::connect();
    }

    /**
     * 查詢盤點單列表
     *
     * @return array{data: Stocktake[], total: int}
     */
    public function list(array $criteria = [], array $options = []): array
    {
        $page    = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['per_page'] ?? 20);

        $builder = $this->stocktakeModel
            ->select('stocktakes.*, warehouses.name as warehouse_name')
            ->join('warehouses', 'warehouses.id = stocktakes.warehouse_id', 'left');

        if (!empty($criteria['status'])) {
            $builder->where('stocktakes.status', $criteria['status']);
        }
        if (!empty($criteria['warehouse_id'])) {
            $builder->where('stocktakes.warehouse_id', $criteria['warehouse_id']);
        }

        $total = $builder->countAllResults(false);
        $data  = $builder
            ->orderBy('stocktakes.id', 'desc')
            ->paginate($perPage, 'default', $page) ?: [];

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 取得盤點單詳情（含明細）
     *
     * @return array{stocktake: Stocktake, lines: StocktakeLine[]}
     * @throws \RuntimeException
     */
    public function getWithLines(int $stocktakeId): array
    {
        $stocktake = $this->stocktakeModel->find($stocktakeId);
        if ($stocktake === null) {
            throw new \RuntimeException("找不到盤點單 #{$stocktakeId}");
        }

        $lines = $this->lineModel
            ->select('stocktake_lines.*, item_skus.sku_code, items.name as item_name')
            ->join('item_skus', 'item_skus.id = stocktake_lines.sku_id', 'left')
            ->join('items',     'items.id = item_skus.item_id',          'left')
            ->where('stocktake_id', $stocktakeId)
            ->findAll() ?: [];

        return ['stocktake' => $stocktake, 'lines' => $lines];
    }

    /**
     * 建立盤點單（快照當前倉庫庫存）
     *
     * @throws \DomainException
     * @throws \RuntimeException
     */
    public function create(int $warehouseId, array $data, int $createdBy): Stocktake
    {
        $this->db->transStart();

        $stocktake = new Stocktake();
        $stocktake->fill([
            'stocktake_number' => $this->generateStocktakeNumber(),
            'warehouse_id'     => $warehouseId,
            'status'           => Stocktake::STATUS_DRAFT,
            'notes'            => $data['notes'] ?? null,
            'created_by'       => $createdBy,
        ]);

        $this->stocktakeModel->save($stocktake);
        $stocktakeId = (int) $this->stocktakeModel->getInsertID();

        // 快照：讀取此倉庫所有現有庫存作為 system_qty
        $inventories = $this->db->table('inventory')
            ->select('inventory.sku_id, inventory.on_hand_qty')
            ->where('warehouse_id', $warehouseId)
            ->get()
            ->getResultArray();

        foreach ($inventories as $inv) {
            $line = new StocktakeLine();
            $line->fill([
                'stocktake_id' => $stocktakeId,
                'sku_id'       => (int) $inv['sku_id'],
                'system_qty'   => (float) $inv['on_hand_qty'],
                'actual_qty'   => null,
                'difference_qty' => null,
            ]);
            $this->lineModel->save($line);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('建立盤點單 DB 交易失敗');
        }

        $stocktake->id = $stocktakeId;
        return $stocktake;
    }

    /**
     * 開始盤點（draft → in_progress）
     *
     * @throws \DomainException
     * @throws \RuntimeException
     */
    public function start(int $stocktakeId): Stocktake
    {
        $stocktake = $this->stocktakeModel->find($stocktakeId);
        if ($stocktake === null) {
            throw new \RuntimeException("找不到盤點單 #{$stocktakeId}");
        }

        $stocktake->start();
        $this->stocktakeModel->save($stocktake);

        return $stocktake;
    }

    /**
     * 錄入實際盤點數量
     *
     * @throws \DomainException 盤點單狀態不正確
     * @throws \RuntimeException
     */
    public function updateCount(int $stocktakeId, int $skuId, float $actualQty): StocktakeLine
    {
        $stocktake = $this->stocktakeModel->find($stocktakeId);
        if ($stocktake === null) {
            throw new \RuntimeException("找不到盤點單 #{$stocktakeId}");
        }

        if (! in_array($stocktake->status, [Stocktake::STATUS_DRAFT, Stocktake::STATUS_IN_PROGRESS], true)) {
            throw new \DomainException("盤點單狀態 [{$stocktake->status}] 不允許錄入盤點數量");
        }

        $line = $this->lineModel
            ->where('stocktake_id', $stocktakeId)
            ->where('sku_id', $skuId)
            ->first();

        if ($line === null) {
            // 允許新增盤點時未存在庫存的 SKU（system_qty = 0）
            $line = new StocktakeLine();
            $line->fill([
                'stocktake_id' => $stocktakeId,
                'sku_id'       => $skuId,
                'system_qty'   => 0.0,
            ]);
        }

        $line->calculateDifference($actualQty);
        $this->lineModel->save($line);

        return $line;
    }

    /**
     * 確認盤點（in_progress → confirmed），對差異品項執行庫存調整
     *
     * @throws \DomainException
     * @throws \RuntimeException
     */
    public function confirm(int $stocktakeId, int $confirmedBy): Stocktake
    {
        $data      = $this->getWithLines($stocktakeId);
        $stocktake = $data['stocktake'];
        $lines     = $data['lines'];

        $stocktake->confirm($confirmedBy);  // DomainException guard

        foreach ($lines as $line) {
            // 只對錄入了 actual_qty 且有差異的品項調整
            if ($line->actual_qty !== null && $line->hasDifference()) {
                $this->inventoryService->adjustStock(
                    skuId:       (int) $line->sku_id,
                    warehouseId: (int) $stocktake->warehouse_id,
                    qty:         (float) $line->actual_qty,
                    reason:      "盤點確認 #{$stocktake->stocktake_number}",
                    operatorId:  $confirmedBy,
                );
            }
        }

        $this->stocktakeModel->save($stocktake);

        return $stocktake;
    }

    /**
     * 取消盤點（不執行任何庫存調整）
     *
     * @throws \DomainException
     * @throws \RuntimeException
     */
    public function cancel(int $stocktakeId): Stocktake
    {
        $stocktake = $this->stocktakeModel->find($stocktakeId);
        if ($stocktake === null) {
            throw new \RuntimeException("找不到盤點單 #{$stocktakeId}");
        }

        $stocktake->cancel();
        $this->stocktakeModel->save($stocktake);

        return $stocktake;
    }

    private function generateStocktakeNumber(): string
    {
        $prefix = 'SK-' . date('Ymd') . '-';
        $last   = $this->stocktakeModel
            ->like('stocktake_number', $prefix, 'after')
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last !== null) {
            $parts = explode('-', $last->stocktake_number);
            $seq   = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
