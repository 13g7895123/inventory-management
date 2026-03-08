<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Auth;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AuthController — JWT 認證相關 API
 *
 * POST /api/v1/auth/login    登入，回傳 Access + Refresh Token
 * POST /api/v1/auth/refresh  用 Refresh Token 換取新 Access Token
 * POST /api/v1/auth/logout   登出（撤銷 Refresh Token）
 * GET  /api/v1/auth/me       取得目前登入者資訊
 */
class AuthController extends BaseApiController
{
    private AuthService $authService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);
        $this->authService = service('authService');
    }

    // ────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/login
    // ────────────────────────────────────────────────────────────────
    public function login(): ResponseInterface
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[1]',
        ];

        if (! $this->validateOrFail($rules)) {
            return api_error('輸入資料有誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        $body = $this->request->getJSON(true);

        try {
            $tokens = $this->authService->login(
                trim($body['email'] ?? ''),
                $body['password'] ?? ''
            );
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), $e->getCode() ?: ResponseInterface::HTTP_UNAUTHORIZED);
        }

        return api_success($tokens, '登入成功', ResponseInterface::HTTP_OK);
    }

    // ────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/refresh
    // ────────────────────────────────────────────────────────────────
    public function refresh(): ResponseInterface
    {
        $rules = [
            'refresh_token' => 'required',
        ];

        if (! $this->validateOrFail($rules)) {
            return api_error('請提供 refresh_token', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        $body = $this->request->getJSON(true);

        try {
            $tokens = $this->authService->refresh($body['refresh_token'] ?? '');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), $e->getCode() ?: ResponseInterface::HTTP_UNAUTHORIZED);
        }

        return api_success($tokens, 'Token 已更新');
    }

    // ────────────────────────────────────────────────────────────────
    // POST /api/v1/auth/logout  （需要 JWT）
    // ────────────────────────────────────────────────────────────────
    public function logout(): ResponseInterface
    {
        $body = $this->request->getJSON(true);
        $refreshToken = $body['refresh_token'] ?? null;

        if ($refreshToken !== null) {
            $this->authService->logout((string) $refreshToken);
        }

        return api_success(null, '已登出');
    }

    // ────────────────────────────────────────────────────────────────
    // GET /api/v1/auth/me  （需要 JWT）
    // ────────────────────────────────────────────────────────────────
    public function me(): ResponseInterface
    {
        $payload = $this->jwtPayload;

        if ($payload === null) {
            return api_error('未授權', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $user = model(\App\Models\UserModel::class)->find((int) $payload->sub);

        if ($user === null) {
            return api_error('使用者不存在', ResponseInterface::HTTP_NOT_FOUND);
        }

        return api_success([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $payload->role ?? '',
        ]);
    }
}
