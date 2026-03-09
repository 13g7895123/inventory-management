<?php

declare(strict_types=1);

namespace App\Entities;

/**
 * Category Entity — 商品分類（支援樹狀結構）
 */
class Category extends BaseEntity
{
    protected $casts = [
        'id'         => 'integer',
        'parent_id'  => '?integer',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => '?datetime',
    ];

    protected array $hidden = ['deleted_at'];

    /**
     * 判斷是否為根分類
     */
    public function isRoot(): bool
    {
        return empty($this->attributes['parent_id']);
    }

    /**
     * 啟用分類
     */
    public function activate(): static
    {
        $this->attributes['is_active'] = true;
        return $this;
    }

    /**
     * 停用分類
     */
    public function deactivate(): static
    {
        $this->attributes['is_active'] = false;
        return $this;
    }
}
