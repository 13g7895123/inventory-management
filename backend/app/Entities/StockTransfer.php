<?php

declare(strict_types=1);

namespace App\Entities;

class StockTransfer extends BaseEntity
{
    const STATUS_DRAFT     = 'draft';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'id'                 => 'integer',
        'from_warehouse_id'  => 'integer',
        'to_warehouse_id'    => 'integer',
        'created_by'         => 'integer',
        'confirmed_by'       => 'integer',
    ];

    protected array $hidden = ['deleted_at'];

    public function confirm(int $confirmedBy): static
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('只有草稿狀態的調撥單可確認');
        }
        $this->status       = self::STATUS_CONFIRMED;
        $this->confirmed_by = $confirmedBy;
        $this->confirmed_at = date('Y-m-d H:i:s');
        return $this;
    }

    public function cancel(): static
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('只有草稿狀態的調撥單可取消');
        }
        $this->status = self::STATUS_CANCELLED;
        return $this;
    }
}
