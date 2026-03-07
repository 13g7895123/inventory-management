<?php

declare(strict_types=1);

if (! function_exists('api_success')) {
    /**
     * 統一成功回應格式
     *
     * @param mixed $data
     */
    function api_success(mixed $data = null, string $message = 'OK', int $statusCode = 200): \CodeIgniter\HTTP\Response
    {
        return response()->setStatusCode($statusCode)->setJSON([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'errors'  => null,
        ]);
    }
}

if (! function_exists('api_error')) {
    /**
     * 統一錯誤回應格式
     *
     * @param array<string, string[]>|null $errors 欄位驗證錯誤
     */
    function api_error(string $message, int $statusCode = 400, ?array $errors = null): \CodeIgniter\HTTP\Response
    {
        return response()->setStatusCode($statusCode)->setJSON([
            'success' => false,
            'message' => $message,
            'data'    => null,
            'errors'  => $errors,
        ]);
    }
}

if (! function_exists('api_paginated')) {
    /**
     * 統一分頁回應格式
     *
     * @param array<mixed> $items
     */
    function api_paginated(array $items, int $total, int $page, int $perPage, string $message = 'OK'): \CodeIgniter\HTTP\Response
    {
        return response()->setStatusCode(200)->setJSON([
            'success' => true,
            'message' => $message,
            'data'    => $items,
            'errors'  => null,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'total_pages'  => (int) ceil($total / max($perPage, 1)),
            ],
        ]);
    }
}
