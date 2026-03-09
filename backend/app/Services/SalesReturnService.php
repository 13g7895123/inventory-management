<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\SalesOrder;
use App\Entities\SalesReturn;
use App\Entities\SalesReturnLine;
use App\Models\SalesReturnLineModel;
use App\Models\SalesReturnModel;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use CodeIgniter\Database\BaseConnection;

/**
 * SalesReturnService — 銷售退貨業務邏輯
 *
 * 退貨流程：
 *   1. 建立草稿退貨單（draft）
 *   2. 確認退貨單（confirmed）→ 庫存入庫（replenish）
 *
 * 只有已出過貨的銷售訂單（confirmed/partial/shipped）才能申請退貨。
 */
class SalesReturnService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly SalesOrderRepositoryInterface $soRepo,
        private readonly InventoryService              $inventoryService,
        private readonly SalesReturnModel              $returnModel,
        private readonly SalesReturnLineModel          $returnLineModel,
    ) {
        $this->db = \Config\Database::connect();
    }

    /**
     * 取得銷售訂單的退貨記錄
     *
     * @return SalesReturn[]
     */
    public function listByOrder(int $salesOrderId): array
    {
        $so = $this->soRepo->findById($salesOrderId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售訂單 #{$salesOrderId}");
        }

        return $this->returnModel
            ->where('sales_order_id', $salesOrderId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * 取得退貨單（含明細）
     *
     * @return array{return: SalesReturn, lines: SalesReturnLine[]}
     */
    public function getWithLines(int $returnId): array
    {
        $return = $this->returnModel->find($returnId);
        if ($return === null) {
            throw new \RuntimeException("找不到退貨單 #{$returnId}");
        }

        $lines = $this->returnLineModel
            ->where('sales_return_id', $returnId)
            ->findAll();

        return ['return' => $return, 'lines' => $lines];
    }

    /**
     * 建立草稿退貨單
     *
     * @param array{
     *     warehouse_id: int,
     *     reason: ?string,
     *     refund_amount: ?float,
     *     notes: ?string,
     *     lines: array<array{
     *         sales_order_line_id: int,
     *         sku_id: int,
     *         return_qty: float,
     *         unit_price: ?float,
     *         return_reason: ?string,
     *         batch_number: ?string,
     *         notes: ?string
     *     }>
     * } $data
     */
    public function create(int $salesOrderId, array $data, int $createdBy): SalesReturn
    {
        $so = $this->soRepo->findById($salesOrderId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售訂單 #{$salesOrderId}");
        }

        // 只有已確認（含部分或全部出貨）的訂單可以退貨
        if (!in_array($so->attributes['status'], [
            SalesOrder::STATUS_CONFIRMED,
            SalesOrder::STATUS_PARTIAL,
            SalesOrder::STATUS_SHIPPED,
        ], true)) {
            throw new \DomainException('只有已確認的銷售訂單可以申請退貨');
        }

        if (empty($data['lines'])) {
            throw new \DomainException('退貨單至少需要一筆明細');
        }

        // 驗證退貨數量不超過已出貨數量
        $soLines    = $this->soRepo->findLines($salesOrderId);
        $soLineMap  = [];
        foreach ($soLines as $line) {
            $soLineMap[$line->id] = $line;
        }

        foreach ($data['lines'] as $returnLine) {
            $lineId  = (int) $returnLine['sales_order_line_id'];
            $soLine  = $soLineMap[$lineId] ?? null;

            if ($soLine === null) {
                throw new \DomainException("銷售訂單明細 #{$lineId} 不存在");
            }
            if ((int) $soLine->sales_order_id !== $salesOrderId) {
                throw new \DomainException("明細 #{$lineId} 不屬於此銷售訂單");
            }

            $returnQty  = (float) $returnLine['return_qty'];
            $shippedQty = (float) ($soLine->shipped_qty ?? 0);

            if ($returnQty <= 0) {
                throw new \DomainException('退貨數量必須大於零');
            }
            if ($returnQty > $shippedQty) {
                throw new \DomainException(
                    "明細 #{$lineId} 退貨數量 {$returnQty} 超過已出貨數量 {$shippedQty}"
                );
            }
        }

        $this->db->transStart();

        $return = new SalesReturn();
        $return->fill([
            'return_number'   => $this->generateReturnNumber(),
            'sales_order_id'  => $salesOrderId,
            'warehouse_id'    => (int) $data['warehouse_id'],
            'status'          => SalesReturn::STATUS_DRAFT,
            'reason'          => $data['reason'] ?? null,
            'refund_amount'   => (float) ($data['refund_amount'] ?? 0),
            'notes'           => $data['notes'] ?? null,
            'created_by'      => $createdBy,
        ]);
        $this->returnModel->save($return);
        $returnId = $this->returnModel->getInsertID();

        foreach ($data['lines'] as $lineData) {
            $line = new SalesReturnLine();
            $line->fill([
                'sales_return_id'     => $returnId,
                'sales_order_line_id' => (int) $lineData['sales_order_line_id'],
                'sku_id'              => (int) $lineData['sku_id'],
                'return_qty'          => (float) $lineData['return_qty'],
                'unit_price'          => isset($lineData['unit_price']) ? (float) $lineData['unit_price'] : null,
                'return_reason'       => $lineData['return_reason'] ?? null,
                'batch_number'        => $lineData['batch_number'] ?? null,
                'notes'               => $lineData['notes'] ?? null,
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
     * 確認退貨單 → 庫存入庫（退回品重新入庫）
     */
    public function confirm(int $returnId, int $confirmedBy): SalesReturn
    {
        $return = $this->returnModel->find($returnId);
        if ($return === null) {
            throw new \RuntimeException("找不到退貨單 #{$returnId}");
        }

        $return->confirm($confirmedBy);  // 會在非 draft 時拋 DomainException

        $lines = $this->returnLineModel
            ->where('sales_return_id', $returnId)
            ->findAll();

        if (empty($lines)) {
            throw new \DomainException('退貨單無明細，無法確認');
        }

        $this->db->transStart();

        // 儲存狀態變更
        $this->returnModel->save($return);

        // 退貨入庫：補充庫存
        foreach ($lines as $line) {
            $this->inventoryService->replenishStock(
                skuId:      $line->sku_id,
                warehouseId: $return->warehouse_id,
                qty:        (float) $line->return_qty,
                unitCost:   (float) ($line->unit_price ?? 0),
                sourceType: 'sales_return',
                sourceId:   $returnId,
                operatorId: $confirmedBy,
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
    public function cancel(int $returnId): SalesReturn
    {
        $return = $this->returnModel->find($returnId);
        if ($return === null) {
            throw new \RuntimeException("找不到退貨單 #{$returnId}");
        }

        $return->cancel();  // 會在 confirmed 時拋 DomainException

        $this->returnModel->save($return);

        return $return;
    }

    // ── 私有輔助 ──────────────────────────────────────────────────────

    /**
     * 產生退貨單號：SR-YYYYMMDD-NNNN
     */
    private function generateReturnNumber(): string
    {
        $date   = date('Ymd');
        $prefix = "SR-{$date}-";

        $last = $this->returnModel
            ->like('return_number', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        if ($last === null) {
            $seq = 1;
        } else {
            $seq = (int) substr($last->return_number, -4) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
