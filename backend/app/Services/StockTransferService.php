<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\StockTransfer;
use App\Entities\StockTransferLine;
use App\Models\StockTransferModel;
use App\Models\StockTransferLineModel;
use App\Models\WarehouseModel;
use CodeIgniter\Database\BaseConnection;

/**
 * StockTransferService — 庫存調撥業務邏輯
 *
 * 調撥流程：建立草稿 → 確認（執行實際庫存移動）→ 或取消
 */
class StockTransferService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly StockTransferModel     $transferModel,
        private readonly StockTransferLineModel $lineModel,
        private readonly WarehouseModel         $warehouseModel,
        private readonly InventoryService       $inventoryService,
    ) {
        $this->db = \Config\Database::connect();
    }

    /**
     * 查詢調撥單列表
     *
     * @return array{data: StockTransfer[], total: int}
     */
    public function list(array $criteria = [], array $options = []): array
    {
        $page    = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['per_page'] ?? 20);

        $builder = $this->transferModel
            ->select('stock_transfers.*, wf.name as from_warehouse_name, wt.name as to_warehouse_name')
            ->join('warehouses wf', 'wf.id = stock_transfers.from_warehouse_id', 'left')
            ->join('warehouses wt', 'wt.id = stock_transfers.to_warehouse_id', 'left');

        if (!empty($criteria['status'])) {
            $builder->where('stock_transfers.status', $criteria['status']);
        }
        if (!empty($criteria['from_warehouse_id'])) {
            $builder->where('stock_transfers.from_warehouse_id', $criteria['from_warehouse_id']);
        }
        if (!empty($criteria['to_warehouse_id'])) {
            $builder->where('stock_transfers.to_warehouse_id', $criteria['to_warehouse_id']);
        }

        $total = $builder->countAllResults(false);
        $data  = $builder
            ->orderBy('stock_transfers.id', 'desc')
            ->paginate($perPage, 'default', $page)
            ?: [];

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 取得調撥單詳情（含明細）
     *
     * @return array{transfer: StockTransfer, lines: StockTransferLine[]}
     * @throws \RuntimeException
     */
    public function getWithLines(int $transferId): array
    {
        $transfer = $this->transferModel->find($transferId);
        if ($transfer === null) {
            throw new \RuntimeException("找不到調撥單 #{$transferId}");
        }

        $lines = $this->lineModel
            ->select('stock_transfer_lines.*, item_skus.sku_code, items.name as item_name')
            ->join('item_skus', 'item_skus.id = stock_transfer_lines.sku_id', 'left')
            ->join('items', 'items.id = item_skus.item_id', 'left')
            ->where('stock_transfer_id', $transferId)
            ->findAll() ?: [];

        return ['transfer' => $transfer, 'lines' => $lines];
    }

    /**
     * 建立調撥單（草稿）
     *
     * @throws \DomainException
     */
    public function create(array $data, int $createdBy): StockTransfer
    {
        $fromWarehouseId = (int) $data['from_warehouse_id'];
        $toWarehouseId   = (int) $data['to_warehouse_id'];

        if ($fromWarehouseId === $toWarehouseId) {
            throw new \DomainException('來源倉庫與目標倉庫不可相同');
        }

        if (!$this->warehouseModel->find($fromWarehouseId)) {
            throw new \DomainException("找不到來源倉庫 #{$fromWarehouseId}");
        }
        if (!$this->warehouseModel->find($toWarehouseId)) {
            throw new \DomainException("找不到目標倉庫 #{$toWarehouseId}");
        }

        $lines = $data['lines'] ?? [];
        if (empty($lines)) {
            throw new \DomainException('調撥明細不可為空');
        }

        $this->db->transStart();

        $transfer = new StockTransfer();
        $transfer->fill([
            'transfer_number'   => $this->generateTransferNumber(),
            'from_warehouse_id' => $fromWarehouseId,
            'to_warehouse_id'   => $toWarehouseId,
            'status'            => StockTransfer::STATUS_DRAFT,
            'reason'            => $data['reason'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'created_by'        => $createdBy,
        ]);

        $this->transferModel->save($transfer);
        $transferId = (int) $this->transferModel->getInsertID();

        foreach ($lines as $lineData) {
            $line = new StockTransferLine();
            $line->fill([
                'stock_transfer_id' => $transferId,
                'sku_id'            => (int) $lineData['sku_id'],
                'qty'               => (float) $lineData['qty'],
                'batch_number'      => $lineData['batch_number'] ?? null,
                'notes'             => $lineData['notes'] ?? null,
            ]);
            $this->lineModel->save($line);
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('建立調撥單 DB 交易失敗');
        }

        $transfer->id = $transferId;
        return $transfer;
    }

    /**
     * 確認調撥單（執行庫存移動）
     *
     * @throws \DomainException
     * @throws \RuntimeException
     */
    public function confirm(int $transferId, int $confirmedBy): StockTransfer
    {
        $data = $this->getWithLines($transferId);
        $transfer = $data['transfer'];
        $lines    = $data['lines'];

        $transfer->confirm($confirmedBy);  // 可能拋 DomainException

        foreach ($lines as $line) {
            // 從來源倉庫扣減
            $this->inventoryService->deductStock(
                skuId:       (int) $line->sku_id,
                warehouseId: (int) $transfer->from_warehouse_id,
                qty:         (float) $line->qty,
                sourceType:  'transfer',
                sourceId:    $transferId,
                operatorId:  $confirmedBy,
            );

            // 補入目標倉庫
            $this->inventoryService->replenishStock(
                skuId:       (int) $line->sku_id,
                warehouseId: (int) $transfer->to_warehouse_id,
                qty:         (float) $line->qty,
                unitCost:    0.0,
                sourceType:  'transfer',
                sourceId:    $transferId,
                operatorId:  $confirmedBy,
            );
        }

        $this->transferModel->save($transfer);

        return $transfer;
    }

    /**
     * 取消調撥單
     *
     * @throws \DomainException
     */
    public function cancel(int $transferId): StockTransfer
    {
        $transfer = $this->transferModel->find($transferId);
        if ($transfer === null) {
            throw new \RuntimeException("找不到調撥單 #{$transferId}");
        }

        $transfer->cancel();
        $this->transferModel->save($transfer);

        return $transfer;
    }

    private function generateTransferNumber(): string
    {
        $prefix = 'ST-' . date('Ymd') . '-';
        $last   = $this->transferModel
            ->like('transfer_number', $prefix, 'after')
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last !== null) {
            $parts = explode('-', $last->transfer_number);
            $seq   = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
