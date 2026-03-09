<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * CustomerAddress Entity — 客戶收貨地址
 */
class CustomerAddress extends BaseEntity
{
    protected $casts = [
        'id'          => 'integer',
        'customer_id' => 'integer',
        'is_default'  => 'boolean',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
}
