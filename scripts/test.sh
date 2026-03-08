#!/usr/bin/env bash
# =============================================================
# test.sh — 執行單元測試（PHPUnit）
# 用法：./scripts/test.sh [phpunit 額外參數]
# 範例：
#   ./scripts/test.sh                                    # 執行全部測試
#   ./scripts/test.sh --testsuite unit                   # 只跑 unit tests
#   ./scripts/test.sh --testsuite feature                # 只跑 feature tests
#   ./scripts/test.sh tests/unit/Services/AuthServiceTest.php
#   ./scripts/test.sh --filter testLoginSuccessReturnsTokens
# =============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DOCKER_DIR="$PROJECT_DIR/docker"
ENV_FILE="$DOCKER_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "錯誤：找不到 docker/.env，請先執行 ./scripts/deploy.sh 初始化環境"
  exit 1
fi

echo "▶ 執行單元測試..."
docker compose \
  --project-directory "$DOCKER_DIR" \
  --env-file "$ENV_FILE" \
  -f "$DOCKER_DIR/docker-compose.yml" \
  exec php-fpm ./vendor/bin/phpunit "$@"

echo "✓ 測試執行完成"
