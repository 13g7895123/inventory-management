<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use CodeIgniter\RESTful\ResourceController;

/**
 * BaseApiController — 所有 API Controller 的基礎類別
 *
 * - 統一載入 response_helper
 * - 提供驗證失敗的快速回應
 * - 從 JWT payload 取得當前使用者 ID
 */
abstract class BaseApiController extends ResourceController
{
    protected ?object $jwtPayload = null;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);

        helper('response');

        // JWT payload 由 AuthFilter 掛載至 $request->jwt
        $this->jwtPayload = $request->jwt ?? null;
    }

    /**
     * 取得目前登入者 ID
     */
    protected function currentUserId(): int
    {
        return (int) ($this->jwtPayload?->sub ?? 0);
    }

    /**
     * 驗證並在失敗時回傳 422
     */
    protected function validateOrFail(array $rules, ?array $messages = null): bool
    {
        if (! $this->validate($rules, $messages ?? [])) {
            return false;
        }

        return true;
    }

    /**
     * 取得已驗證的 JSON Body（陣列）
     *
     * @return array<string, mixed>
     */
    protected function jsonBody(): array
    {
        return (array) $this->request->getJSON(assoc: true);
    }
}
