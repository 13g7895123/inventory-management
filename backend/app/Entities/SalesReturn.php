<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * SalesReturn Entity — 銷售退貨單
 */
class SalesReturn extends BaseEntity
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'id'             => 'integer',
        'sales_order_id' => 'integer',
        'warehouse_id'   => 'integer',
        'refund_amount'  => 'float',
        'created_by'     => 'integer',
        'confirmed_by'   => '?integer',
        'confirmed_at'   => '?datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];

    public function isDraft(): bool
    {
        return $this->attributes['status'] === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->attributes['status'] === self::STATUS_CONFIRMED;
    }

    public function confirm(int $confirmedBy): static
    {
        if (!$this->isDraft()) {
            throw new \DomainException('只有草稿狀態的退貨單可以確認');
        }
        $this->attributes['status']       = self::STATUS_CONFIRMED;
        $this->attributes['confirmed_by'] = $confirmedBy;
        $this->attributes['confirmed_at'] = date('Y-m-d H:i:s');
        return $this;
    }

    public function cancel(): static
    {
        if ($this->isConfirmed()) {
            throw new \DomainException('已確認的退貨單無法取消');
        }
        $this->attributes['status'] = self::STATUS_CANCELLED;
        return $this;
    }
}
