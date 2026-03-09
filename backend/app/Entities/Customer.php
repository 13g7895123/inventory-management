<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Customer Entity — 客戶領域物件
 */
class Customer extends BaseEntity
{
    protected $casts = [
        'id'           => 'integer',
        'credit_limit' => 'float',
        'is_active'    => 'boolean',
        'created_by'   => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];

    public function isActive(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }
}
