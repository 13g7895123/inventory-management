<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Item Entity — 商品領域物件
 *
 * 代表一個商品的完整狀態與行為。
 * CI4 Entity 透過 $casts 自動轉型；
 * 透過 get/set Magic Method 實作計算屬性與封裝邏輯。
 */
class Item extends BaseEntity
{
    protected $casts = [
        'id'             => 'integer',
        'category_id'    => 'integer',
        'unit_id'        => 'integer',
        'reorder_point'  => 'float',
        'safety_stock'   => 'float',
        'is_active'      => 'boolean',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
        'deleted_at'     => '?datetime',
    ];

    /**
     * 欄位名稱映射（資料庫欄位名 → Entity 屬性名）
     * JOIN 查詢帶回的額外欄位可在此映射
     */
    protected $datamap = [
        'categoryName' => 'category_name',
        'unitName'     => 'unit_name',
    ];

    protected array $hidden = ['deleted_at'];

    // ──────────────────────────────────────────────
    // 行為方法（Business Behavior）
    // ──────────────────────────────────────────────

    /**
     * 啟用商品
     */
    public function activate(): static
    {
        $this->attributes['is_active'] = true;
        return $this;
    }

    /**
     * 停用商品（有歷史交易時只可停用不可刪除）
     */
    public function deactivate(): static
    {
        $this->attributes['is_active'] = false;
        return $this;
    }

    /**
     * 判斷此商品是否為啟用狀態
     */
    public function isActive(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }

    /**
     * 判斷某庫存量是否低於安全庫存
     */
    public function isBelowSafetyStock(float $currentQty): bool
    {
        return $currentQty < (float) ($this->attributes['safety_stock'] ?? 0);
    }

    /**
     * 判斷某庫存量是否達到再訂購點
     */
    public function hasReachedReorderPoint(float $currentQty): bool
    {
        return $currentQty <= (float) ($this->attributes['reorder_point'] ?? 0);
    }
}
