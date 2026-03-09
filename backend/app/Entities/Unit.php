<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Unit Entity — 計量單位
 */
class Unit extends BaseEntity
{
    protected $casts = [
        'id'         => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
