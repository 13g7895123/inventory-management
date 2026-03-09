<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * SalesReturnLine Entity — 銷售退貨明細
 */
class SalesReturnLine extends BaseEntity
{
    protected $casts = [
        'id'                  => 'integer',
        'sales_return_id'     => 'integer',
        'sales_order_line_id' => 'integer',
        'sku_id'              => 'integer',
        'return_qty'          => 'float',
        'unit_price'          => '?float',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];
}
