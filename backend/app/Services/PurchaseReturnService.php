<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\PurchaseOrder;
use App\Entities\PurchaseReturn;
use App\Entities\PurchaseReturnLine;
use App\Models\PurchaseReturnLineModel;
use App\Models\PurchaseReturnModel;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use CodeIgniter\Database\BaseConnection;

/**
 * PurchaseReturnService — 採購退貨業務邏輯
 *
 * 退貨流程：
 *   1. 建立草稿退貨單（draft）
 *   2. 確認退貨單（confirmed）→ 扣減庫存
 *
 * 只有已驗收（received / partial）的採購單才能退貨。
 */
class PurchaseReturnService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepo,
        private readonly InventoryService                 $inventoryService,
        private readonly PurchaseReturnModel              $returnModel,
        private readonly PurchaseReturnLineModel          $returnLineModel,
    ) {
        $this->db = \Config\Database::connect();
    }

    /**
     * 取得採購單的退貨記錄
     *
     * @return PurchaseReturn[]
     */
    public function listByOrder(int $purchaseOrderId): array
    {
        $po = $this->poRepo->findById($purchaseOrderId);
        if ($po === null) {
            throw new \RuntimeException("找不到採購單 #{$purchaseOrderId}");
        }

        return $this->returnModel
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * 取得退貨單（含明細）
     *
     * @return array{return: PurchaseReturn, lines: PurchaseReturnLine[]}
     */
    public function getWithLines(int $returnId): array
    {
        $return = $this->returnModel->find($returnId);
        if ($return === null) {
            throw new \RuntimeException("找不到退貨單 #{$returnId}");
        }

        $lines = $this->returnLineModel
            ->where('purchase_return_id', $returnId)
            ->findAll();

        return ['return' => $return, 'lines' => $lines];
    }

    /**
     * 建立草稿退貨單
     *
     * @param array{
     *     reason: ?string,
     *     notes: ?string,
     *     lines: array<array{
     *         purchase_order_line_id: int,
     *         sku_id: int,
     *         return_qty: float,
     *         unit_cost: ?float,
     *         return_reason: ?string,
     *         batch_number: ?string,
     *         notes: ?string
     *     }>
     * } $data
     */
    public function create(int $purchaseOrderId, array $data, int $createdBy): PurchaseReturn
    {
        $po = $this->poRepo->findById($purchaseOrderId);
        if ($po === null) {
            throw new \RuntimeException("找不到採購單 #{$purchaseOrderId}");
        }

        // 只有已到貨（received 或 partial）的採購單可以退貨
        if (!in_array($po->attributes['status'], [
            PurchaseOrder::STATUS_RECEIVED,
            PurchaseOrder::STATUS_PARTIAL,
        ], true)) {
            throw new \DomainException('只有已到貨的採購單可以申請退貨');
        }

        if (empty($data['lines'])) {
            throw new \DomainException('退貨單至少需要一筆明細');
        }

        // 驗證退貨數量不超過已驗收數量
        $poLines = $this->poRepo->findLines($purchaseOrderId);
        $poLineMap = [];
        foreach ($poLines as $line) {
            $poLineMap[$line->id] = $line;
        }

        foreach ($data['lines'] as $returnLine) {
            $lineId  = (int) $returnLine['purchase_order_line_id'];
            $poLine  = $poLineMap[$lineId] ?? null;

            if ($poLine === null) {
                throw new \DomainException("採購單明細 #{$lineId} 不存在");
            }
            if ((int) $poLine->purchase_order_id !== $purchaseOrderId) {
                throw new \DomainException("明細 #{$lineId} 不屬於此採購單");
            }

            $returnQty = (float) $returnLine['return_qty'];
            if ($returnQty <= 0) {
                throw new \DomainException("退貨數量必須大於零");
            }
            if ($returnQty > (float) $poLine->received_qty) {
                throw new \DomainException(
                    "明細 #{$lineId} 退貨數量 {$returnQty} 超過已驗收數量 {$poLine->received_qty}"
                );
            }
        }

        $this->db->transStart();

        $return = new PurchaseReturn();
        $return->fill([
            'return_number'     => $this->generateReturnNumber(),
            'purchase_order_id' => $purchaseOrderId,
            'status'            => PurchaseReturn::STATUS_DRAFT,
            'reason'            => $data['reason'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'created_by'        => $createdBy,
        ]);
        $this->returnModel->save($return);
        $returnId = $this->returnModel->getInsertID();

        foreach ($data['lines'] as $lineData) {
            $line = new PurchaseReturnLine();
            $line->fill([
                'purchase_return_id'      => $returnId,
                'purchase_order_line_id'  => (int) $lineData['purchase_order_line_id'],
                'sku_id'                  => (int) $lineData['sku_id'],
                'return_qty'              => (float) $lineData['return_qty'],
                'unit_cost'               => isset($lineData['unit_cost']) ? (float) $lineData['unit_cost'] : null,
                'return_reason'           => $lineData['return_reason'] ?? null,
                'batch_number'            => $lineData['batch_number'] ?? null,
                'notes'                   => $lineData['notes'] ?? null,
            ]);
            $this->returnLineModel->save($line);
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('建立退貨單 DB 交易失敗');
        }

        return $this->returnModel->find($returnId);
    }

    /**
     * 確認退貨單 → 扣減庫存
     */
    public function confirm(int $returnId, int $confirmedBy): PurchaseReturn
    {
        $return = $this->returnModel->find($returnId);
        if ($return === null) {
            throw new \RuntimeException("找不到退貨單 #{$returnId}");
        }

        $return->confirm($confirmedBy);  // 會在非 draft 時拋 DomainException

        $po = $this->poRepo->findById($return->purchase_order_id);
        if ($po === null) {
            throw new \RuntimeException("找不到採購單 #{$return->purchase_order_id}");
        }

        $lines = $this->returnLineModel
            ->where('purchase_return_id', $returnId)
            ->findAll();

        if (empty($lines)) {
            throw new \DomainException('退貨單無明細，無法確認');
        }

        $this->db->transStart();

        // 儲存狀態變更
        $this->returnModel->save($return);

        // 扣減庫存：退貨即出庫
        foreach ($lines as $line) {
            $this->inventoryService->deductStock(
                skuId:         $line->sku_id,
                warehouseId:   $po->warehouse_id,
                qty:           (float) $line->return_qty,
                sourceType:    'purchase_return',
                sourceId:      $returnId,
                operatorId:    $confirmedBy,
                allowNegative: false,
            );
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('確認退貨單 DB 交易失敗');
        }

        return $this->returnModel->find($returnId);
    }

    /**
     * 取消退貨單（只能取消草稿狀態）
     */
    public function cancel(int $returnId): PurchaseReturn
    {
        $return = $this->returnModel->find($returnId);
        if ($return === null) {
            throw new \RuntimeException("找不到退貨單 #{$returnId}");
        }

        $return->cancel();
        $this->returnModel->save($return);
        return $return;
    }

    /**
     * 產生退貨單號（格式：PR-YYYYMMDD-NNNN）
     */
    private function generateReturnNumber(): string
    {
        $today  = date('Ymd');
        $prefix = "PR-{$today}-";

        $last = $this->returnModel
            ->like('return_number', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        if ($last === null) {
            $seq = 1;
        } else {
            $parts = explode('-', $last->return_number);
            $seq   = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
