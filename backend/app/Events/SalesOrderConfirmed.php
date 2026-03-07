<?php

declare(strict_types=1);

namespace App\Events;

/**
 * SalesOrderConfirmed — 銷售單確認事件
 *
 * 當 SO 從 DRAFT 轉為 CONFIRMED 時觸發。
 * Listener 可據此執行庫存預留、觸發倉儲揀貨任務等。
 */
final class SalesOrderConfirmed
{
    public function __construct(
        public readonly int    $salesOrderId,
        public readonly string $soNumber,
        public readonly int    $customerId,
        public readonly int    $confirmedBy,
        public readonly array  $lineItems,  // [{sku_id, warehouse_id, qty, unit_price}]
        public readonly string $requestedShipDate,
        public readonly string $occurredAt,
    ) {}
}
