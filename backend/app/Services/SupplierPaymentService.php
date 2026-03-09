<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\PurchaseOrder;
use App\Entities\PurchasePayment;
use App\Models\PurchaseOrderModel;
use App\Models\PurchasePaymentModel;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;

/**
 * SupplierPaymentService — 供應商採購付款業務邏輯
 *
 * 付款狀態：
 *   unpaid  → paid_amount = 0
 *   partial → 0 < paid_amount < total_amount
 *   paid    → paid_amount >= total_amount
 */
class SupplierPaymentService
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepo,
        private readonly PurchasePaymentModel             $paymentModel,
        private readonly PurchaseOrderModel               $poModel,
    ) {}

    /**
     * 取得採購單的付款記錄（最新到最舊）
     *
     * @return PurchasePayment[]
     */
    public function listPayments(int $purchaseOrderId): array
    {
        $po = $this->poRepo->findById($purchaseOrderId);
        if ($po === null) {
            throw new \RuntimeException("找不到採購單 #{$purchaseOrderId}");
        }

        return $this->paymentModel
            ->where('purchase_order_id', $purchaseOrderId)
            ->orderBy('payment_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * 新增付款記錄，並自動更新採購單的付款狀態與已付金額
     *
     * @param array{
     *     amount: float,
     *     payment_date: string,
     *     payment_method: string,
     *     reference_no: ?string,
     *     notes: ?string,
     * } $data
     */
    public function addPayment(int $purchaseOrderId, array $data, int $createdBy): PurchasePayment
    {
        $po = $this->poRepo->findById($purchaseOrderId);
        if ($po === null) {
            throw new \RuntimeException("找不到採購單 #{$purchaseOrderId}");
        }

        if ($po->attributes['status'] === PurchaseOrder::STATUS_CANCELLED) {
            throw new \DomainException('已取消的採購單無法新增付款記錄');
        }

        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new \DomainException('付款金額必須大於零');
        }

        $currentPaid = (float) ($po->attributes['paid_amount'] ?? 0);
        $totalAmount = (float) ($po->attributes['total_amount'] ?? 0);

        if ($currentPaid + $amount > $totalAmount * 1.001) {  // 容許 0.1% 浮點誤差
            throw new \DomainException(
                sprintf(
                    '累計付款金額（%.2f）不得超過採購單總金額（%.2f）',
                    $currentPaid + $amount,
                    $totalAmount
                )
            );
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // 建立付款記錄
        $payment = new PurchasePayment();
        $payment->fill([
            'purchase_order_id' => $purchaseOrderId,
            'amount'            => $amount,
            'payment_date'      => $data['payment_date'],
            'payment_method'    => $data['payment_method'] ?? 'bank_transfer',
            'reference_no'      => $data['reference_no'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'created_by'        => $createdBy,
        ]);
        $this->paymentModel->save($payment);
        $paymentId = $this->paymentModel->getInsertID();

        // 更新採購單付款狀態
        $newPaidAmount = $currentPaid + $amount;
        $newStatus     = $this->resolvePaymentStatus($newPaidAmount, $totalAmount);

        $this->poModel->update($purchaseOrderId, [
            'paid_amount'    => $newPaidAmount,
            'payment_status' => $newStatus,
        ]);

        $db->transComplete();

        if (!$db->transStatus()) {
            throw new \RuntimeException('新增付款記錄 DB 交易失敗');
        }

        return $this->paymentModel->find($paymentId);
    }

    /**
     * 依已付金額與總金額判斷付款狀態
     */
    private function resolvePaymentStatus(float $paidAmount, float $totalAmount): string
    {
        if ($paidAmount <= 0) {
            return 'unpaid';
        }
        if ($paidAmount < $totalAmount * 0.999) {  // 容許浮點誤差
            return 'partial';
        }
        return 'paid';
    }
}
