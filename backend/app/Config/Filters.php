<?php

declare(strict_types=1);

namespace Config;

use App\Filters\AuthFilter;
use App\Filters\CorsFilter;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\PageCache;

class Filters extends BaseConfig
{
    /**
     * 別名 → Filter 類別對應
     */
    public array $aliases = [
        'csrf'      => CSRF::class,
        'toolbar'   => \CodeIgniter\Filters\DebugToolbar::class,
        'honeypot'  => \CodeIgniter\Filters\Honeypot::class,
        'invalidchars' => \CodeIgniter\Filters\InvalidChars::class,
        'secureheaders' => \CodeIgniter\Filters\SecureHeaders::class,
        'auth'      => AuthFilter::class,
        'cors'      => CorsFilter::class,
        'pagecache' => PageCache::class,
        'performance' => \CodeIgniter\Filters\PerformanceMetrics::class,
    ];

    /**
     * 全域 Before Filters（所有請求皆套用）
     */
    public array $globals = [
        'before' => [
            'cors',            // CORS 必須最先執行
            // 'honeypot',
            // 'csrf',
        ],
        'after' => [
            'toolbar',
            // 'honeypot',
        ],
    ];

    /**
     * 依 HTTP Method 套用的 Filters
     */
    public array $methods = [];

    /**
     * 依路由路徑套用的 Filters
     */
    public array $filters = [];
}
