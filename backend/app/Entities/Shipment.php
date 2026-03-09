<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Shipment Entity — 出貨單領域物件
 */
class Shipment extends BaseEntity
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_SHIPPED   = 'shipped';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'id'              => 'integer',
        'sales_order_id'  => 'integer',
        'warehouse_id'    => 'integer',
        'created_by'      => 'integer',
        'shipped_at'      => '?datetime',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
        'deleted_at'      => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];

    public function isPending(): bool
    {
        return $this->attributes['status'] === self::STATUS_PENDING;
    }

    public function isShipped(): bool
    {
        return $this->attributes['status'] === self::STATUS_SHIPPED;
    }

    /**
     * 標記為已出貨
     *
     * @throws \DomainException
     */
    public function markShipped(): static
    {
        if (!$this->isPending()) {
            throw new \DomainException('只有待出貨狀態的出貨單可標記為已出貨');
        }
        $this->attributes['status']     = self::STATUS_SHIPPED;
        $this->attributes['shipped_at'] = date('Y-m-d H:i:s');
        return $this;
    }

    /**
     * 取消出貨單
     *
     * @throws \DomainException
     */
    public function cancel(): static
    {
        if ($this->isShipped()) {
            throw new \DomainException('已出貨的出貨單無法取消');
        }
        $this->attributes['status'] = self::STATUS_CANCELLED;
        return $this;
    }
}
