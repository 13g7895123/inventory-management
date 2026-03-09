<?php

declare(strict_types=1);

namespace Config;

/**
 * CI4 目錄路徑設定
 *
 * bootstrap.php 使用此類別定位 system / app / writable 等目錄。
 * ROOTPATH 由 public/index.php 定義為專案根目錄。
 */
class Paths
{
    /** CI4 framework system 目錄 */
    public string $systemDirectory = __DIR__ . '/../../vendor/codeigniter4/framework/system';

    /** Application 目錄 */
    public string $appDirectory = __DIR__ . '/..';

    /** 可寫入目錄（logs / cache / session / uploads） */
    public string $writableDirectory = __DIR__ . '/../../writable';

    /** 測試目錄 */
    public string $testsDirectory = __DIR__ . '/../../tests';

    /** View 目錄（API 不使用，仍需定義） */
    public string $viewDirectory = __DIR__ . '/../../app/Views';
}
