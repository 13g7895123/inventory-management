<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\App as BaseApp;

/**
 * 主要應用程式設定
 *
 * 此設定優先讀取環境變數（透過 env() 函式）。
 * 實際值由 docker/.env 或 .env 檔案提供。
 */
class App extends BaseApp
{
    // ── 基本設定 ─────────────────────────────────────────────────────

    /** 應用程式基礎 URL（後端 API 用） */
    public string $baseURL = '';

    /** 預設時區 */
    public string $appTimezone = 'Asia/Taipei';

    /** 字元集 */
    public string $charset = 'UTF-8';

    /** 強制 HTTPS（生產環境建議啟用） */
    public bool $forceGlobalSecureRequests = false;

    /** 反向代理 IP 白名單 */
    public string $proxyIPs = '';

    // ── URL 設定 ──────────────────────────────────────────────────────

    /** index.php 是否顯示於 URL（置空以美化 URL） */
    public string $indexPage = '';

    /** URI 偵測方式 */
    public string $uriProtocol = 'REQUEST_URI';

    // ── Session（API 不使用，維持最小設定）────────────────────────────

    public string $sessionDriver            = 'CodeIgniter\Session\Handlers\FileHandler';
    public string $sessionCookieName        = 'ci_session';
    public int    $sessionExpiration        = 7200;
    public string $sessionSavePath         = WRITABLEPATH . 'session';
    public bool   $sessionMatchIP           = false;
    public int    $sessionTimeToUpdate      = 300;
    public bool   $sessionRegenerateDestroy = false;

    // ── Cookie ────────────────────────────────────────────────────────

    public string $cookiePrefix   = '';
    public string $cookieDomain   = '';
    public string $cookiePath     = '/';
    public bool   $cookieSecure   = false;
    public bool   $cookieHTTPOnly = false;
    public string $cookieSameSite = 'Lax';

    // ── 加密 ──────────────────────────────────────────────────────────

    /** 加密金鑰（Session 等使用；可由 JWT_SECRET 共用或另設） */
    public string $encryptionKey = '';

    // ── Content Security Policy ───────────────────────────────────────

    public bool $CSPEnabled = false;

    public function __construct()
    {
        parent::__construct();

        // 優先從環境變數讀取
        $this->baseURL        = env('APP_BASEURL', 'http://localhost/');
        $this->encryptionKey  = env('APP_ENCRYPTION_KEY', str_repeat('x', 32));
    }
}
