#!/usr/bin/env bash
# =============================================================
# seed.sh — 執行資料庫 Seeder
# 用法：./scripts/seed.sh [SeederClass] [--env=testing]
# 範例：
#   ./scripts/seed.sh                  # 執行 DatabaseSeeder（預設）
#   ./scripts/seed.sh RoleSeeder
#   ./scripts/seed.sh DatabaseSeeder --env=testing
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

SEEDER="${1:-DatabaseSeeder}"
shift || true

echo "▶ 執行 Seeder：${SEEDER}..."
docker compose \
  --project-directory "$DOCKER_DIR" \
  --env-file "$ENV_FILE" \
  -f "$DOCKER_DIR/docker-compose.yml" \
  exec php-fpm php spark db:seed "$SEEDER" "$@"

echo "✓ Seed 完成（${SEEDER}）"
