<?php

declare(strict_types=1);

namespace App\Entities;

class GoodsReceipt extends BaseEntity
{
    protected $casts = [
        'id'                 => 'integer',
        'purchase_order_id'  => 'integer',
        'warehouse_id'       => 'integer',
        'received_by'        => 'integer',
        'received_at'        => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];
}
