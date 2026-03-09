#!/bin/sh
# =============================================================
# entrypoint.sh — PHP-FPM 容器啟動腳本
# 負責：
#   1. 若 vendor/ 不存在，自動執行 composer install
#   2. 建立 writable/ 子目錄並設定權限
#   3. 啟動 php-fpm
# =============================================================

set -e

cd /var/www/backend

# ── 1. Composer 安裝 ──────────────────────────────────────────
if [ ! -f "vendor/autoload.php" ]; then
    echo "[entrypoint] vendor/ 不存在，執行 composer install..."
    composer install \
        --no-interaction \
        --optimize-autoloader \
        --no-progress
    echo "[entrypoint] composer install 完成"
else
    echo "[entrypoint] vendor/ 已存在，跳過 composer install"
fi

# ── 2. 建立 writable 目錄 ─────────────────────────────────────
for dir in cache logs session uploads debugbar; do
    mkdir -p "writable/${dir}"
done
chmod -R 0777 writable
echo "[entrypoint] writable/ 目錄準備完成"

# ── 3. 啟動 php-fpm ───────────────────────────────────────────
echo "[entrypoint] 啟動 php-fpm..."
exec php-fpm
