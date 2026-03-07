<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * PurchaseOrder Entity — 採購單領域物件
 */
class PurchaseOrder extends BaseEntity
{
    // 採購單狀態常數
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_PENDING  = 'pending';   // 待審核
    public const STATUS_APPROVED = 'approved';  // 已核准
    public const STATUS_PARTIAL  = 'partial';   // 部分到貨
    public const STATUS_RECEIVED = 'received';  // 全部到貨
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'id'             => 'integer',
        'supplier_id'    => 'integer',
        'warehouse_id'   => 'integer',
        'subtotal'       => 'float',
        'tax_amount'     => 'float',
        'total_amount'   => 'float',
        'approved_by'    => '?integer',
        'approved_at'    => '?datetime',
        'expected_date'  => '?datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];

    // ── 狀態檢查 ──

    public function isDraft(): bool
    {
        return $this->attributes['status'] === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->attributes['status'] === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return in_array($this->attributes['status'], [
            self::STATUS_APPROVED,
            self::STATUS_PARTIAL,
            self::STATUS_RECEIVED,
        ], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->attributes['status'], [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
        ], true);
    }

    // ── 狀態轉換（State Transition）──

    /**
     * 提交審核
     *
     * @throws \DomainException 非草稿狀態時拋出
     */
    public function submit(): static
    {
        if (!$this->isDraft()) {
            throw new \DomainException('只有草稿狀態的採購單可提交審核');
        }
        $this->attributes['status'] = self::STATUS_PENDING;
        return $this;
    }

    /**
     * 核准採購單
     *
     * @throws \DomainException
     */
    public function approve(int $approvedBy): static
    {
        if (!$this->isPending()) {
            throw new \DomainException('只有待審核狀態的採購單可核准');
        }
        $this->attributes['status']      = self::STATUS_APPROVED;
        $this->attributes['approved_by'] = $approvedBy;
        $this->attributes['approved_at'] = date('Y-m-d H:i:s');
        return $this;
    }

    /**
     * 取消採購單
     *
     * @throws \DomainException
     */
    public function cancel(): static
    {
        if (!$this->isCancellable()) {
            throw new \DomainException('此狀態的採購單無法取消');
        }
        $this->attributes['status'] = self::STATUS_CANCELLED;
        return $this;
    }
}
