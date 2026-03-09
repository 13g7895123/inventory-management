<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\SalesOrder;
use App\Entities\SalesOrderLine;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use CodeIgniter\Database\BaseConnection;

/**
 * SalesOrderService — 銷售訂單業務邏輯
 *
 * 狀態流程：
 *   draft → confirmed（庫存預留）→ partial / shipped
 *   draft / confirmed → cancelled（庫存釋放）
 *
 * 防超賣：confirm 時對每行 SKU 呼叫 InventoryService::reserveStock()，
 *         若可用庫存不足則拋 DomainException 並 rollback。
 */
class SalesOrderService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly SalesOrderRepositoryInterface $soRepo,
        private readonly CustomerRepositoryInterface   $customerRepo,
        private readonly InventoryService              $inventoryService,
    ) {
        $this->db = \Config\Database::connect();
    }

    // ── 建立草稿銷售單 ────────────────────────────────────────────

    /**
     * @param array{
     *     customer_id: int,
     *     warehouse_id: int,
     *     order_date: ?string,
     *     expected_ship_date: ?string,
     *     tax_rate: ?float,
     *     discount_amount: ?float,
     *     shipping_address_id: ?int,
     *     shipping_name: ?string,
     *     shipping_phone: ?string,
     *     shipping_address: ?string,
     *     notes: ?string,
     *     is_dropship: ?bool,
     *     lines: array<array{sku_id: int, ordered_qty: float, unit_price: float, discount_rate: ?float, notes: ?string}>
     * } $data
     */
    public function create(array $data, int $createdBy): SalesOrder
    {
        $customer = $this->customerRepo->findById($data['customer_id']);
        if ($customer === null) {
            throw new \DomainException("找不到客戶 #{$data['customer_id']}");
        }
        if (!$customer->isActive()) {
            throw new \DomainException('客戶已停用，無法建立銷售單');
        }
        if (empty($data['lines'])) {
            throw new \DomainException('銷售單至少需要一筆明細');
        }

        $taxRate        = (float) ($data['tax_rate'] ?? 5.0);
        $discountAmount = (float) ($data['discount_amount'] ?? 0.0);

        // 計算金額
        $subtotal = 0.0;
        foreach ($data['lines'] as $line) {
            $discountRate = (float) ($line['discount_rate'] ?? 0.0);
            $subtotal    += (float) $line['ordered_qty'] * (float) $line['unit_price']
                           * (1 - $discountRate / 100);
        }
        $subtotal    -= $discountAmount;
        $taxAmount    = round($subtotal * $taxRate / 100, 4);
        $totalAmount  = round($subtotal + $taxAmount, 4);

        $this->db->transStart();

        $so = new SalesOrder();
        $so->fill([
            'so_number'           => $this->soRepo->generateSoNumber(),
            'customer_id'         => $data['customer_id'],
            'warehouse_id'        => $data['warehouse_id'],
            'status'              => SalesOrder::STATUS_DRAFT,
            'shipping_address_id' => $data['shipping_address_id'] ?? null,
            'shipping_name'       => $data['shipping_name'] ?? null,
            'shipping_phone'      => $data['shipping_phone'] ?? null,
            'shipping_address'    => $data['shipping_address'] ?? null,
            'order_date'          => $data['order_date'] ?? date('Y-m-d'),
            'expected_ship_date'  => $data['expected_ship_date'] ?? null,
            'tax_rate'            => $taxRate,
            'subtotal'            => $subtotal,
            'tax_amount'          => $taxAmount,
            'total_amount'        => $totalAmount,
            'discount_amount'     => $discountAmount,
            'is_dropship'         => (bool) ($data['is_dropship'] ?? false),
            'notes'               => $data['notes'] ?? null,
            'created_by'          => $createdBy,
        ]);
        $this->soRepo->save($so);

        // 重新取得以獲取 ID
        $so = $this->soRepo->findById((int) $this->db->insertID());

        foreach ($data['lines'] as $lineData) {
            $discountRate = (float) ($lineData['discount_rate'] ?? 0.0);
            $lineTotal    = (float) $lineData['ordered_qty'] * (float) $lineData['unit_price']
                           * (1 - $discountRate / 100);

            $line = new SalesOrderLine();
            $line->fill([
                'sales_order_id' => $so->id,
                'sku_id'         => $lineData['sku_id'],
                'ordered_qty'    => $lineData['ordered_qty'],
                'shipped_qty'    => 0,
                'unit_price'     => $lineData['unit_price'],
                'discount_rate'  => $discountRate,
                'line_total'     => $lineTotal,
                'notes'          => $lineData['notes'] ?? null,
            ]);
            $this->soRepo->saveLine($line);
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('建立銷售單 DB 交易失敗');
        }

        return $this->getWithLines($so->id)['sales_order'];
    }

    // ── 確認訂單（預留庫存）────────────────────────────────────────

    /**
     * 確認訂單並對每行 SKU 預留庫存
     *
     * @throws \DomainException  狀態不符或庫存不足
     */
    public function confirm(int $soId, int $confirmedBy): SalesOrder
    {
        $so = $this->soRepo->findById($soId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售單 #{$soId}");
        }

        $so->confirm();  // 狀態驗證（throws DomainException if not draft）
        $so->fill(['confirmed_by' => $confirmedBy]);

        $lines = $this->soRepo->findLines($soId);

        $this->db->transStart();

        $this->soRepo->save($so);

        // 逐行預留庫存（若庫存不足 reserveStock 會拋 DomainException）
        foreach ($lines as $line) {
            $this->inventoryService->reserveStock(
                $line->sku_id,
                $so->warehouse_id,
                $line->ordered_qty,
            );
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('確認銷售單 DB 交易失敗');
        }

        return $this->soRepo->findById($soId);
    }

    // ── 取消訂單（釋放預留庫存）──────────────────────────────────

    /**
     * @throws \DomainException  狀態不符
     */
    public function cancel(int $soId): SalesOrder
    {
        $so = $this->soRepo->findById($soId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售單 #{$soId}");
        }

        $wasConfirmed = $so->isConfirmed();
        $so->cancel();  // 狀態驗證

        $lines = $this->soRepo->findLines($soId);

        $this->db->transStart();

        $this->soRepo->save($so);

        // 若已預留庫存，取消時需釋放
        if ($wasConfirmed) {
            foreach ($lines as $line) {
                $pending = $line->pendingQty();  // 尚未出貨的比例要釋放
                if ($pending > 0) {
                    $this->inventoryService->releaseReservation(
                        $line->sku_id,
                        $so->warehouse_id,
                        $pending,
                    );
                }
            }
        }

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('取消銷售單 DB 交易失敗');
        }

        return $this->soRepo->findById($soId);
    }

    // ── 查詢 ─────────────────────────────────────────────────────

    /**
     * @return array{sales_order: SalesOrder, lines: SalesOrderLine[]}
     *
     * @throws \RuntimeException
     */
    public function getWithLines(int $soId): array
    {
        $so = $this->soRepo->findById($soId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售單 #{$soId}");
        }

        return [
            'sales_order' => $so,
            'lines'       => $this->soRepo->findLines($soId),
        ];
    }

    /**
     * 分頁列表
     *
     * @return array{data: SalesOrder[], total: int}
     */
    public function list(array $criteria = [], array $options = []): array
    {
        $result = $this->soRepo->findAll($criteria, $options);
        return [
            'data'  => $result['data']  ?? $result,
            'total' => $result['total'] ?? count($result),
        ];
    }
}
