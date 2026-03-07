<?php

declare(strict_types=1);

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CorsFilter — 跨域資源共享標頭
 *
 * 在所有 API 回應加上 CORS 標頭，並處理 OPTIONS preflight。
 */
class CorsFilter implements FilterInterface
{
    private array $allowedOrigins;

    public function __construct()
    {
        $origin = env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000');
        $this->allowedOrigins = array_map('trim', explode(',', $origin));
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request->getMethod() === 'options') {
            $response = response();
            $this->addCorsHeaders($request, $response);

            return $response->setStatusCode(ResponseInterface::HTTP_NO_CONTENT);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $this->addCorsHeaders($request, $response);
    }

    private function addCorsHeaders(RequestInterface $request, ResponseInterface $response): void
    {
        $origin = $request->getHeaderLine('Origin');

        if (in_array($origin, $this->allowedOrigins, true) || in_array('*', $this->allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin ?: '*');
        }

        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With');
        $response->setHeader('Access-Control-Max-Age', '86400');
    }
}
