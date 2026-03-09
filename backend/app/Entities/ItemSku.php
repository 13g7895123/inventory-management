<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * ItemSku Entity — 商品 SKU 變體
 *
 * 一個商品（Item）可有多個 SKU（顏色+尺寸組合）。
 * SKU 是庫存追蹤的最小單位。
 */
class ItemSku extends BaseEntity
{
    protected $casts = [
        'id'            => 'integer',
        'item_id'       => 'integer',
        'cost_price'    => 'float',
        'selling_price' => 'float',
        'is_active'     => 'boolean',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];

    /**
     * 取得完整的 SKU 顯示名稱（商品名 + 屬性組合）
     * 需先透過 JOIN 載入 item_name 與 attributes
     */
    public function getDisplayName(): string
    {
        $itemName = $this->attributes['item_name'] ?? '';
        $attrs    = $this->attributes['attributes'] ?? '';

        return $attrs ? "{$itemName} — {$attrs}" : $itemName;
    }

    public function activate(): static
    {
        $this->attributes['is_active'] = true;
        return $this;
    }

    public function deactivate(): static
    {
        $this->attributes['is_active'] = false;
        return $this;
    }
}
