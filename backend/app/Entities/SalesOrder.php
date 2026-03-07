<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * SalesOrder Entity — 銷售訂單領域物件
 */
class SalesOrder extends BaseEntity
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_CONFIRMED = 'confirmed'; // 已確認，庫存已預留
    public const STATUS_PARTIAL   = 'partial';   // 部分出貨
    public const STATUS_SHIPPED   = 'shipped';   // 全部出貨
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'id'           => 'integer',
        'customer_id'  => 'integer',
        'warehouse_id' => 'integer',
        'subtotal'     => 'float',
        'tax_amount'   => 'float',
        'total_amount' => 'float',
        'is_dropship'  => 'boolean',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'deleted_at'   => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];

    // ── 狀態檢查 ──

    public function isDraft(): bool
    {
        return $this->attributes['status'] === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return in_array($this->attributes['status'], [
            self::STATUS_CONFIRMED,
            self::STATUS_PARTIAL,
        ], true);
    }

    public function isShippable(): bool
    {
        return in_array($this->attributes['status'], [
            self::STATUS_CONFIRMED,
            self::STATUS_PARTIAL,
        ], true);
    }

    public function isCancellable(): bool
    {
        return in_array($this->attributes['status'], [
            self::STATUS_DRAFT,
            self::STATUS_CONFIRMED,
        ], true);
    }

    // ── 狀態轉換 ──

    /**
     * 確認訂單（並預留庫存）
     *
     * @throws \DomainException
     */
    public function confirm(): static
    {
        if (!$this->isDraft()) {
            throw new \DomainException('只有草稿狀態的訂單可確認');
        }
        $this->attributes['status']       = self::STATUS_CONFIRMED;
        $this->attributes['confirmed_at'] = date('Y-m-d H:i:s');
        return $this;
    }

    /**
     * 標記為部分出貨
     */
    public function markPartialShipped(): static
    {
        $this->attributes['status'] = self::STATUS_PARTIAL;
        return $this;
    }

    /**
     * 標記為全部出貨完成
     */
    public function markFullyShipped(): static
    {
        $this->attributes['status']    = self::STATUS_SHIPPED;
        $this->attributes['closed_at'] = date('Y-m-d H:i:s');
        return $this;
    }

    /**
     * 取消訂單
     *
     * @throws \DomainException
     */
    public function cancel(): static
    {
        if (!$this->isCancellable()) {
            throw new \DomainException('此狀態的訂單無法取消');
        }
        $this->attributes['status'] = self::STATUS_CANCELLED;
        return $this;
    }

    /**
     * 判斷是否為代發（Drop Shipping）訂單
     */
    public function isDropship(): bool
    {
        return (bool) ($this->attributes['is_dropship'] ?? false);
    }
}
