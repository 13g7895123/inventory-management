<?php

declare(strict_types=1);

namespace App\Events;

/**
 * PurchaseOrderApproved — 採購單核准事件
 *
 * 當採購單從 PENDING 轉為 APPROVED 時觸發。
 * Listener 可據此更新庫存的 on_order_qty。
 */
final class PurchaseOrderApproved
{
    public function __construct(
        public readonly int    $purchaseOrderId,
        public readonly string $poNumber,
        public readonly int    $supplierId,
        public readonly int    $approvedBy,
        public readonly array  $lineItems,  // [{sku_id, qty, unit_price}]
        public readonly string $expectedArrivalDate,
        public readonly string $occurredAt,
    ) {}
}
