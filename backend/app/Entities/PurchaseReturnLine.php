<?php

declare(strict_types=1);

namespace App\Entities;

class PurchaseReturnLine extends BaseEntity
{
    protected $casts = [
        'id'                      => 'integer',
        'purchase_return_id'      => 'integer',
        'purchase_order_line_id'  => 'integer',
        'sku_id'                  => 'integer',
        'return_qty'              => 'float',
        'unit_cost'               => '?float',
        'created_at'              => 'datetime',
        'updated_at'              => 'datetime',
    ];
}
