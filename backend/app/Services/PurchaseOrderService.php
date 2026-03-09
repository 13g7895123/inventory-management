<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\PurchaseOrder;
use App\Entities\PurchaseOrderLine;
use App\Events\PurchaseOrderApproved;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;

/**
 * PurchaseOrderService — 採購單業務邏輯
 *
 * 狀態流程：草稿 → 待審核 → 已核准 → 部分到貨 → 全部到貨
 *                                   ↘ 取消
 */
class PurchaseOrderService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepo,
        private readonly SupplierRepositoryInterface      $supplierRepo,
    ) {
        $this->db = \Config\Database::connect();
    }

    // ── 建立採購單 ────────────────────────────────────────────────

    /**
     * 建立草稿採購單
     *
     * @param array{
     *     supplier_id: int,
     *     warehouse_id: int,
     *     expected_date: ?string,
     *     notes: ?string,
     *     tax_rate: ?float,
     *     lines: array<array{sku_id: int, ordered_qty: float, unit_price: float, notes: ?string}>
     * } $data
     */
    public function create(array $data, int $createdBy): PurchaseOrder
    {
        $supplier = $this->supplierRepo->findById($data['supplier_id']);
        if ($supplier === null) {
            throw new \DomainException("找不到供應商 #{$data['supplier_id']}");
        }
        if (!$supplier->isActive()) {
            throw new \DomainException('供應商已停用，無法建立採購單');
        }
        if (empty($data['lines'])) {
            throw new \DomainException('採購單至少需要一筆明細');
        }

        $this->db->transStart();

        $po = new PurchaseOrder();
        $po->fill([
            'po_number'    => $this->poRepo->generatePoNumber(),
            'supplier_id'  => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
            'status'       => PurchaseOrder::STATUS_DRAFT,
            'expected_date'=> $data['expected_date'] ?? null,
            'notes'        => $data['notes'] ?? null,
            'tax_rate'     => $data['tax_rate'] ?? 0.05,
            'created_by'   => $createdBy,
        ]);

        // 計算金額
        [$subtotal, $taxAmount, $totalAmount] = $this->calculateTotals(
            $data['lines'],
            (float) ($data['tax_rate'] ?? 0.05),
        );
        $po->fill([
            'subtotal'     => $subtotal,
            'tax_amount'   => $taxAmount,
            'total_amount' => $totalAmount,
        ]);

        $this->poRepo->save($po);

        // 插入明細
        foreach ($data['lines'] as $lineData) {
            $line = new PurchaseOrderLine();
            $line->fill([
                'purchase_order_id' => $po->id,
                'sku_id'            => $lineData['sku_id'],
                'ordered_qty'       => $lineData['ordered_qty'],
                'received_qty'      => 0,
                'unit_price'        => $lineData['unit_price'],
                'line_total'        => $lineData['ordered_qty'] * $lineData['unit_price'],
                'notes'             => $lineData['notes'] ?? null,
            ]);
            $this->poRepo->saveLine($line);
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('建立採購單 DB 交易失敗');
        }

        return $this->poRepo->findById($po->id);
    }

    // ── 更新草稿 ──────────────────────────────────────────────────

    /**
     * 更新草稿採購單（僅允許 draft 狀態）
     */
    public function update(int $id, array $data): PurchaseOrder
    {
        $po = $this->findOrFail($id);

        if (!$po->isDraft()) {
            throw new \DomainException('只有草稿狀態的採購單可以修改');
        }

        $this->db->transStart();

        $updateFields = array_intersect_key($data, array_flip([
            'warehouse_id', 'expected_date', 'notes', 'tax_rate',
        ]));
        if (!empty($updateFields)) {
            $po->fill($updateFields);
        }

        if (isset($data['lines'])) {
            // 重新計算金額
            $taxRate = (float) ($data['tax_rate'] ?? $po->attributes['tax_rate'] ?? 0.05);
            [$subtotal, $taxAmount, $totalAmount] = $this->calculateTotals($data['lines'], $taxRate);
            $po->fill([
                'subtotal'     => $subtotal,
                'tax_amount'   => $taxAmount,
                'total_amount' => $totalAmount,
            ]);
        }

        $this->poRepo->save($po);

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('更新採購單 DB 交易失敗');
        }

        return $this->poRepo->findById($po->id);
    }

    // ── 狀態流程 ──────────────────────────────────────────────────

    /**
     * 提交審核（草稿 → 待審核）
     */
    public function submit(int $id): PurchaseOrder
    {
        $po = $this->findOrFail($id);
        $po->submit();   // 會在非 draft 時拋 DomainException

        $this->poRepo->save($po);
        return $po;
    }

    /**
     * 核准採購單（待審核 → 已核准），並觸發 Domain Event
     */
    public function approve(int $id, int $approvedBy): PurchaseOrder
    {
        $po = $this->findOrFail($id);
        $po->approve($approvedBy);   // 會在非 pending 時拋 DomainException

        $this->poRepo->save($po);

        // 取得明細，供 Event 使用
        $lines = $this->poRepo->findLines($po->id);

        Events::trigger('purchase_order.approved', new PurchaseOrderApproved(
            purchaseOrderId:     $po->id,
            poNumber:            $po->po_number,
            supplierId:          $po->supplier_id,
            approvedBy:          $approvedBy,
            lineItems:           array_map(fn(PurchaseOrderLine $l) => [
                'sku_id'      => $l->sku_id,
                'ordered_qty' => $l->ordered_qty,
                'unit_price'  => $l->unit_price,
            ], $lines),
            expectedArrivalDate: $po->expected_date ? (string) $po->expected_date : null,
            occurredAt:          date('Y-m-d H:i:s'),
        ));

        return $po;
    }

    /**
     * 取消採購單
     */
    public function cancel(int $id): PurchaseOrder
    {
        $po = $this->findOrFail($id);
        $po->cancel();   // 會在無法取消時拋 DomainException

        $this->poRepo->save($po);
        return $po;
    }

    // ── 查詢 ──────────────────────────────────────────────────────

    public function getById(int $id): PurchaseOrder
    {
        return $this->findOrFail($id);
    }

    /**
     * @return array{data: PurchaseOrder[], total: int, lines: PurchaseOrderLine[][]}
     */
    public function list(array $criteria = [], array $options = []): array
    {
        $result = $this->poRepo->findAll($criteria, $options);
        return $result;
    }

    /**
     * 取得採購單（含明細）
     */
    public function getWithLines(int $id): array
    {
        $po    = $this->findOrFail($id);
        $lines = $this->poRepo->findLines($id);

        return [
            'purchase_order' => $po,
            'lines'          => $lines,
        ];
    }

    // ── 私有輔助 ──────────────────────────────────────────────────

    private function findOrFail(int $id): PurchaseOrder
    {
        $po = $this->poRepo->findById($id);
        if ($po === null) {
            throw new \RuntimeException("找不到採購單 #{$id}");
        }
        return $po;
    }

    /**
     * @param array<array{ordered_qty: float, unit_price: float}> $lines
     * @return array{float, float, float} [subtotal, taxAmount, totalAmount]
     */
    private function calculateTotals(array $lines, float $taxRate): array
    {
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += (float) $line['ordered_qty'] * (float) $line['unit_price'];
        }
        $taxAmount   = round($subtotal * $taxRate, 4);
        $totalAmount = round($subtotal + $taxAmount, 4);

        return [$subtotal, $taxAmount, $totalAmount];
    }
}
