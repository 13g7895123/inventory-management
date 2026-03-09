<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * SalesPayment Entity — 客戶收款紀錄
 */
class SalesPayment extends BaseEntity
{
    protected $casts = [
        'id'             => 'integer',
        'sales_order_id' => 'integer',
        'amount'         => 'float',
        'created_by'     => 'integer',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];
}
