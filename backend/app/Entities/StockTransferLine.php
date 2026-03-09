<?php

declare(strict_types=1);

namespace App\Entities;

class StockTransferLine extends BaseEntity
{
    protected $casts = [
        'id'                 => 'integer',
        'stock_transfer_id'  => 'integer',
        'sku_id'             => 'integer',
        'qty'                => 'float',
    ];
}
