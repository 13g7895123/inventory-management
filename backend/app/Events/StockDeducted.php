<?php

declare(strict_types=1);

namespace App\Events;

/**
 * StockDeducted — 庫存出庫事件
 *
 * 在 InventoryService 完成出庫後觸發。
 * 使用 CI4 Events::trigger() 派送此事件物件。
 *
 * 此類別為不可變的值物件（Value Object），只帶資料。
 */
final class StockDeducted
{
    public function __construct(
        public readonly int    $skuId,
        public readonly int    $warehouseId,
        public readonly float  $deductedQuantity,
        public readonly float  $remainingOnHand,
        public readonly float  $remainingAvailable,
        public readonly string $sourceType,   // 'sales_order' | 'transfer' | 'adjustment'
        public readonly int    $sourceId,
        public readonly int    $operatorId,
        public readonly string $occurredAt,
    ) {}
}
