<?php

declare(strict_types=1);

namespace App\Libraries\JWT;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

/**
 * JWTService — Access Token 與 Refresh Token 管理
 *
 * Access Token：短效（預設 1 小時），包含 sub / role / iat / exp
 * Refresh Token：長效（預設 7 天），隨機 bytes，存入 DB（hash）
 */
class JWTService
{
    private string $secret;
    private int    $ttl;
    private int    $refreshTtl;
    private string $algo = 'HS256';

    public function __construct()
    {
        $this->secret     = (string) env('JWT_SECRET', 'change-me');
        $this->ttl        = (int) env('JWT_TTL', 3600);
        $this->refreshTtl = (int) env('JWT_REFRESH_TTL', 604800);
    }

    /**
     * 產生 Access Token
     *
     * @param array<string, mixed> $extra 自訂 payload 欄位
     */
    public function generateAccessToken(int $userId, string $role, array $extra = []): string
    {
        $now = time();

        $payload = array_merge($extra, [
            'iss' => base_url(),
            'sub' => $userId,
            'role' => $role,
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ]);

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * 產生 Refresh Token（隨機 bytes，Base64 URL-safe 編碼）
     */
    public function generateRefreshToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }

    /**
     * 驗證並解碼 Access Token
     *
     * @throws \Firebase\JWT\ExpiredException
     * @throws \UnexpectedValueException
     */
    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->secret, $this->algo));
    }

    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }
}
