<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class RefreshTokenModel extends Model
{
    protected $table            = 'refresh_tokens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'user_id',
        'token_hash',
        'expires_at',
        'revoked',
        'created_at',
    ];

    /**
     * 儲存 Refresh Token（hash 後存入）
     */
    public function store(int $userId, string $rawToken, int $ttlSeconds): void
    {
        $this->insert([
            'user_id'    => $userId,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
            'revoked'    => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 以 raw token 查找有效的 refresh token 記錄
     */
    public function findValid(string $rawToken): ?object
    {
        return $this
            ->where('token_hash', hash('sha256', $rawToken))
            ->where('revoked', 0)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->first();
    }

    /**
     * 撤銷指定 token
     */
    public function revoke(string $rawToken): void
    {
        $this->where('token_hash', hash('sha256', $rawToken))
             ->set('revoked', 1)
             ->update();
    }

    /**
     * 撤銷某使用者的所有 token（登出所有裝置）
     */
    public function revokeAllForUser(int $userId): void
    {
        $this->where('user_id', $userId)
             ->set('revoked', 1)
             ->update();
    }
}
