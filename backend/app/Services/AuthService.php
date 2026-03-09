<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\User;
use App\Libraries\JWT\JWTService;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;

/**
 * AuthService — 認證業務邏輯
 *
 * 職責：登入驗證、Token 核發、Refresh Token 輪換、登出
 */
class AuthService
{
    public function __construct(
        private readonly UserModel         $userModel,
        private readonly RefreshTokenModel $refreshTokenModel,
        private readonly JWTService        $jwtService,
    ) {}

    /**
     * 登入驗證，成功回傳 Token 組；失敗拋出例外
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array<string, mixed>}
     * @throws \RuntimeException
     */
    public function login(string $username, string $password): array
    {
        $user = $this->userModel->findActiveByUsername($username);

        if ($user === null || ! $user->verifyPassword($password)) {
            throw new \RuntimeException('帳號或密碼錯誤', 401);
        }

        $this->userModel->touchLastLogin($user->id);

        return $this->issueTokens($user);
    }

    /**
     * Refresh Token 輪換
     *
     * 舊 Refresh Token 立即撤銷，核發全新的 Access + Refresh Token 組。
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array<string, mixed>}
     * @throws \RuntimeException
     */
    public function refresh(string $rawRefreshToken): array
    {
        $record = $this->refreshTokenModel->findValid($rawRefreshToken);

        if ($record === null) {
            throw new \RuntimeException('Refresh Token 無效或已過期', 401);
        }

        // 撤銷舊 token（一次性使用）
        $this->refreshTokenModel->revoke($rawRefreshToken);

        $user = $this->userModel->find($record->user_id);

        if ($user === null || ! $user->isActive()) {
            throw new \RuntimeException('使用者帳號不存在或已停用', 401);
        }

        return $this->issueTokens($user);
    }

    /**
     * 登出：撤銷指定 Refresh Token
     */
    public function logout(string $rawRefreshToken): void
    {
        $this->refreshTokenModel->revoke($rawRefreshToken);
    }

    /**
     * 登出所有裝置：撤銷該使用者的所有 Refresh Token
     */
    public function logoutAll(int $userId): void
    {
        $this->refreshTokenModel->revokeAllForUser($userId);
    }

    /**
     * 核發 Access Token + Refresh Token
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, user: array<string, mixed>}
     */
    private function issueTokens(User $user): array
    {
        $role = $this->resolveRoleName($user->role_id);

        $accessToken  = $this->jwtService->generateAccessToken($user->id, $role);
        $refreshToken = $this->jwtService->generateRefreshToken();

        $this->refreshTokenModel->store(
            $user->id,
            $refreshToken,
            $this->jwtService->getRefreshTtl()
        );

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in'    => $this->jwtService->getTtl(),
            'user'          => [
                'id'       => $user->id,
                'username' => $user->username,
                'name'     => $user->name,
                'role'     => $role,
            ],
        ];
    }

    /**
     * 取得 role name（由 DB 協助查詢）
     */
    private function resolveRoleName(int $roleId): string
    {
        return $this->userModel->findRoleName($roleId);
    }
}
