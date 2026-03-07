<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * AuthFilter — JWT Bearer Token 驗證
 *
 * 驗證通過後將 payload 寫入 $request->jwt 供 Controller 讀取。
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader) || ! str_starts_with($authHeader, 'Bearer ')) {
            return api_error('未提供 Authorization Token', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $token = substr($authHeader, 7);

        try {
            $secret  = env('JWT_SECRET', 'change-me');
            $payload = JWT::decode($token, new Key($secret, 'HS256'));

            // 掛載 payload 至 request，Controller 使用 $request->jwt
            $request->jwt = $payload;
        } catch (\Firebase\JWT\ExpiredException $e) {
            return api_error('Token 已過期，請重新登入', ResponseInterface::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            return api_error('Token 無效', ResponseInterface::HTTP_UNAUTHORIZED);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}
