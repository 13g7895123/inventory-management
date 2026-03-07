<?php

declare(strict_types=1);

namespace App\Events;

/**
 * StockReplenished — 庫存入庫事件
 *
 * 在 GoodsReceiptService 完成入庫後觸發。
 */
final class StockReplenished
{
    public function __construct(
        public readonly int    $skuId,
        public readonly int    $warehouseId,
        public readonly float  $addedQuantity,
        public readonly float  $newOnHand,
        public readonly float  $unitCost,
        public readonly string $sourceType,  // 'purchase_order' | 'transfer' | 'adjustment'
        public readonly int    $sourceId,
        public readonly int    $operatorId,
        public readonly string $occurredAt,
    ) {}
}
