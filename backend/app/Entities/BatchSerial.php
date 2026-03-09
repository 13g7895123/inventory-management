<?php

declare(strict_types=1);

namespace App\Entities;

class BatchSerial extends BaseEntity
{
    public const TYPE_BATCH  = 'batch';
    public const TYPE_SERIAL = 'serial';

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RESERVED  = 'reserved';
    public const STATUS_CONSUMED  = 'consumed';
    public const STATUS_EXPIRED   = 'expired';

    protected $casts = [
        'id'                     => 'integer',
        'sku_id'                 => 'integer',
        'warehouse_id'           => 'integer',
        'goods_receipt_line_id'  => '?integer',
        'quantity'               => 'float',
        'unit_cost'              => 'float',
        'manufactured_date'      => '?datetime',
        'expiry_date'            => '?datetime',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    public function isExpired(): bool
    {
        if ($this->expiry_date === null) {
            return false;
        }
        return $this->expiry_date < new \DateTime();
    }
}
