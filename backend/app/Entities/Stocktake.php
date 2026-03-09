<?php

declare(strict_types=1);

namespace App\Entities;

class Stocktake extends BaseEntity
{
    const STATUS_DRAFT       = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_CONFIRMED   = 'confirmed';
    const STATUS_CANCELLED   = 'cancelled';

    protected $casts = [
        'id'           => 'integer',
        'warehouse_id' => 'integer',
        'created_by'   => 'integer',
        'confirmed_by' => 'integer',
    ];

    protected array $hidden = ['deleted_at'];

    public function start(): static
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new \DomainException('只有草稿狀態的盤點可開始');
        }
        $this->status = self::STATUS_IN_PROGRESS;
        return $this;
    }

    public function confirm(int $confirmedBy): static
    {
        if (!in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true)) {
            throw new \DomainException('只有草稿或進行中狀態的盤點可確認');
        }
        $this->status       = self::STATUS_CONFIRMED;
        $this->confirmed_by = $confirmedBy;
        $this->confirmed_at = date('Y-m-d H:i:s');
        return $this;
    }

    public function cancel(): static
    {
        if ($this->status === self::STATUS_CONFIRMED) {
            throw new \DomainException('已確認的盤點單無法取消');
        }
        $this->status = self::STATUS_CANCELLED;
        return $this;
    }
}
