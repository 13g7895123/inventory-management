<?php

declare(strict_types=1);

namespace App\Entities;

class Warehouse extends BaseEntity
{
    protected $casts = [
        'id'        => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $hidden = ['deleted_at'];
}
