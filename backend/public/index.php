<?php

/**
 * public/index.php — CodeIgniter 4 前端控制器
 *
 * 此檔案是所有 HTTP 請求的入口點（Nginx fastcgi_pass 指向此處）。
 * 負責定義路徑常數、載入 Composer autoloader、啟動 CI4 框架。
 */

// 確保當前目錄指向此檔案所在目錄
if ($_SERVER['SCRIPT_FILENAME'] === __FILE__) {
    chdir(dirname(__FILE__));
}

// 前端控制器路徑（public/ 目錄）
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// 專案根目錄（public/ 的上一層，包含 app/、vendor/、writable/ 等）
define('ROOTPATH', realpath(FCPATH . '..') . DIRECTORY_SEPARATOR);

/*
 * 載入 Composer autoloader
 * ─────────────────────────
 * 若 vendor/ 不存在，顯示提示訊息（通常表示尚未執行 composer install）
 */
if (! file_exists(ROOTPATH . 'vendor/autoload.php')) {
    http_response_code(503);
    echo '<h1>503 Service Unavailable</h1>';
    echo '<p>Please run <strong>composer install</strong> inside the backend directory.</p>';
    exit(1);
}

require_once ROOTPATH . 'vendor/autoload.php';

/*
 * 啟動 CodeIgniter 4 框架（CI4 >= 4.5.0 新格式）
 */
require_once ROOTPATH . 'app/Config/Paths.php';

$paths = new Config\Paths();

CodeIgniter\Boot::bootWeb($paths);
