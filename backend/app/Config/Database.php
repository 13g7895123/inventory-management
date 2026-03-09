<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Database\Config;

/**
 * 資料庫連線設定
 *
 * 連線參數由環境變數覆蓋，預設值對應 Docker 開發環境。
 */
class Database extends Config
{
    public string $filesPath    = APPPATH . 'Database' . DIRECTORY_SEPARATOR;
    public string $defaultGroup = 'default';

    /** 預設（主要）資料庫連線 */
    public array $default = [
        'DSN'          => '',
        'hostname'     => 'mysql',
        'username'     => 'app',
        'password'     => 'secret',
        'database'     => 'inventory_db',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_unicode_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
    ];

    /** 測試環境資料庫（phpunit.xml 中以 PHP env 覆蓋） */
    public array $tests = [
        'DSN'          => '',
        'hostname'     => '127.0.0.1',
        'username'     => 'app',
        'password'     => 'secret',
        'database'     => 'inventory_test',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_unicode_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
    ];

    public function __construct()
    {
        parent::__construct();

        // 從 Docker 環境變數覆蓋連線設定
        $this->default['hostname'] = env('MYSQL_HOST', 'mysql');
        $this->default['username'] = env('MYSQL_USER', 'app');
        $this->default['password'] = env('MYSQL_PASSWORD', 'secret');
        $this->default['database'] = env('MYSQL_DATABASE', 'inventory_db');
    }
}
