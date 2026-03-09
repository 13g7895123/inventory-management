<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * SalesOrderLine Entity — 銷售訂單明細
 */
class SalesOrderLine extends BaseEntity
{
    protected $casts = [
        'id'              => 'integer',
        'sales_order_id'  => 'integer',
        'sku_id'          => 'integer',
        'ordered_qty'     => 'float',
        'shipped_qty'     => 'float',
        'unit_price'      => 'float',
        'discount_rate'   => 'float',
        'line_total'      => 'float',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    /**
     * 尚未出貨的數量
     */
    public function pendingQty(): float
    {
        return max(0.0, (float) ($this->attributes['ordered_qty'] ?? 0)
            - (float) ($this->attributes['shipped_qty'] ?? 0));
    }

    /**
     * 計算行小計（折扣後）
     */
    public function calculateLineTotal(): float
    {
        $qty          = (float) ($this->attributes['ordered_qty'] ?? 0);
        $price        = (float) ($this->attributes['unit_price'] ?? 0);
        $discountRate = (float) ($this->attributes['discount_rate'] ?? 0);
        return $qty * $price * (1 - $discountRate / 100);
    }
}
