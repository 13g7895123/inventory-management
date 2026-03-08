#!/usr/bin/env bash
# =============================================================
# migrate.sh — 執行資料庫 Migration
# 用法：./scripts/migrate.sh [--env=testing] [其他 spark 參數]
# 範例：
#   ./scripts/migrate.sh
#   ./scripts/migrate.sh --env=testing
#   ./scripts/migrate.sh rollback --batch=1
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

echo "▶ 執行 Migration..."
docker compose \
  --project-directory "$DOCKER_DIR" \
  --env-file "$ENV_FILE" \
  -f "$DOCKER_DIR/docker-compose.yml" \
  exec php-fpm php spark migrate --all "$@"

echo "✓ Migration 完成"
