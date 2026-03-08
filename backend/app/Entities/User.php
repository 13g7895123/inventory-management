<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * User Entity
 *
 * 代表 users 資料表的一筆資料列。
 * password 欄位透過 set 攔截自動 hash；toArray() 時自動排除 password。
 */
class User extends Entity
{
    protected $casts = [
        'id'        => 'integer',
        'role_id'   => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['password'];

    protected $datamap = [];

    /**
     * 設定密碼時自動 bcrypt hash
     */
    public function setPassword(string $password): static
    {
        $this->attributes['password'] = password_hash($password, PASSWORD_BCRYPT);
        return $this;
    }

    /**
     * 驗證密碼是否正確
     */
    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->attributes['password'] ?? '');
    }

    /**
     * 是否為啟用狀態
     */
    public function isActive(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? false);
    }
}
