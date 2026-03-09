<?php

declare(strict_types=1);

namespace App\Entities;

class GoodsReceiptLine extends BaseEntity
{
    protected $casts = [
        'id'                      => 'integer',
        'goods_receipt_id'        => 'integer',
        'purchase_order_line_id'  => 'integer',
        'sku_id'                  => 'integer',
        'received_qty'            => 'float',
        'unit_cost'               => 'float',
        'expiry_date'             => '?datetime',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];
}
