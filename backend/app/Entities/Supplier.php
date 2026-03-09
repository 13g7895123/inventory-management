<?php

declare(strict_types=1);

namespace App\Entities;

class Supplier extends BaseEntity
{
    protected $casts = [
        'id'             => 'integer',
        'lead_time_days' => 'integer',
        'is_active'      => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];

    public function isActive(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }
}
