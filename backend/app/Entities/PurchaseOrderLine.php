<?php

declare(strict_types=1);

namespace App\Entities;

class PurchaseOrderLine extends BaseEntity
{
    protected $casts = [
        'id'                 => 'integer',
        'purchase_order_id'  => 'integer',
        'sku_id'             => 'integer',
        'ordered_qty'        => 'float',
        'received_qty'       => 'float',
        'unit_price'         => 'float',
        'line_total'         => 'float',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    /**
     * 未到貨數量
     */
    public function getPendingQty(): float
    {
        return max(0, $this->ordered_qty - $this->received_qty);
    }

    public function isFullyReceived(): bool
    {
        return $this->received_qty >= $this->ordered_qty;
    }
}
