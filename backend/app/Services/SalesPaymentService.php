<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\SalesOrder;
use App\Entities\SalesPayment;
use App\Models\SalesOrderModel;
use App\Models\SalesPaymentModel;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;

/**
 * SalesPaymentService — 客戶收款業務邏輯
 *
 * 付款狀態：
 *   unpaid  → paid_amount = 0
 *   partial → 0 < paid_amount < total_amount
 *   paid    → paid_amount >= total_amount
 */
class SalesPaymentService
{
    public function __construct(
        private readonly SalesOrderRepositoryInterface $soRepo,
        private readonly SalesPaymentModel             $paymentModel,
        private readonly SalesOrderModel               $soModel,
    ) {}

    /**
     * 取得銷售訂單的收款記錄（最新到最舊）
     *
     * @return SalesPayment[]
     */
    public function listPayments(int $salesOrderId): array
    {
        $so = $this->soRepo->findById($salesOrderId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售訂單 #{$salesOrderId}");
        }

        return $this->paymentModel
            ->where('sales_order_id', $salesOrderId)
            ->orderBy('payment_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * 新增收款記錄，並自動更新銷售訂單的付款狀態與已收金額
     *
     * @param array{
     *     amount: float,
     *     payment_date: string,
     *     payment_method: string,
     *     reference_no: ?string,
     *     notes: ?string,
     * } $data
     */
    public function addPayment(int $salesOrderId, array $data, int $createdBy): SalesPayment
    {
        $so = $this->soRepo->findById($salesOrderId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售訂單 #{$salesOrderId}");
        }

        if ($so->attributes['status'] === SalesOrder::STATUS_CANCELLED) {
            throw new \DomainException('已取消的銷售訂單無法新增收款記錄');
        }

        if ($so->attributes['status'] === SalesOrder::STATUS_DRAFT) {
            throw new \DomainException('草稿狀態的銷售訂單尚未確認，無法收款');
        }

        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw new \DomainException('收款金額必須大於零');
        }

        $currentPaid = (float) ($so->attributes['paid_amount'] ?? 0);
        $totalAmount = (float) ($so->attributes['total_amount'] ?? 0);

        if ($currentPaid + $amount > $totalAmount * 1.001) {  // 容許 0.1% 浮點誤差
            throw new \DomainException(
                sprintf(
                    '累計收款金額（%.2f）不得超過訂單總金額（%.2f）',
                    $currentPaid + $amount,
                    $totalAmount,
                )
            );
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // 建立收款記錄
        $payment = new SalesPayment();
        $payment->fill([
            'sales_order_id' => $salesOrderId,
            'amount'         => $amount,
            'payment_date'   => $data['payment_date'],
            'payment_method' => $data['payment_method'] ?? 'bank_transfer',
            'reference_no'   => $data['reference_no'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'created_by'     => $createdBy,
        ]);
        $this->paymentModel->save($payment);
        $paymentId = $this->paymentModel->getInsertID();

        // 更新銷售訂單付款狀態
        $newPaidAmount = $currentPaid + $amount;
        $newStatus     = $this->resolvePaymentStatus($newPaidAmount, $totalAmount);

        $this->soModel->update($salesOrderId, [
            'paid_amount'    => $newPaidAmount,
            'payment_status' => $newStatus,
        ]);

        $db->transComplete();

        if (!$db->transStatus()) {
            throw new \RuntimeException('新增收款記錄 DB 交易失敗');
        }

        return $this->paymentModel->find($paymentId);
    }

    // ── 私有輔助 ──────────────────────────────────────────────────────

    private function resolvePaymentStatus(float $paidAmount, float $totalAmount): string
    {
        if ($paidAmount <= 0) {
            return 'unpaid';
        }
        if ($paidAmount >= $totalAmount * 0.999) {  // 容許 0.1% 浮點誤差
            return 'paid';
        }
        return 'partial';
    }
}
