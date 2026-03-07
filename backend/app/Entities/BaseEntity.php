<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * BaseEntity
 *
 * 所有 Entity 的基底類別。
 * 提供所有 Entity 共用的行為：toApiArray、isNew、時間欄位處理。
 *
 * 架構說明：
 * Entity 是代表領域物件的 PHP 物件，由 Repository 從 Model 取得後回傳。
 * Entity 不直接操作資料庫，透過 Repository 進行持久化。
 */
abstract class BaseEntity extends Entity
{
    /**
     * 子類別可覆寫此屬性，指定哪些欄位不應出現在 API 輸出中
     */
    protected array $hidden = ['deleted_at'];

    /**
     * 轉換為 API 輸出用陣列（排除敏感欄位）
     */
    public function toApiArray(): array
    {
        $data = $this->toArray();

        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * 判斷此 Entity 是否為尚未持久化的新物件
     */
    public function isNew(): bool
    {
        return empty($this->attributes['id']);
    }

    /**
     * 取得已變更欄位清單（用於 PATCH 更新）
     */
    public function getChangedFields(): array
    {
        $changed = [];
        foreach ($this->original as $key => $value) {
            if (array_key_exists($key, $this->attributes) && $this->attributes[$key] !== $value) {
                $changed[$key] = $this->attributes[$key];
            }
        }
        return $changed;
    }
}
