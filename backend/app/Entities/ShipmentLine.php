<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * ShipmentLine Entity — 出貨單明細
 */
class ShipmentLine extends BaseEntity
{
    protected $casts = [
        'id'                  => 'integer',
        'shipment_id'         => 'integer',
        'sales_order_line_id' => 'integer',
        'sku_id'              => 'integer',
        'shipped_qty'         => 'float',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];
}
