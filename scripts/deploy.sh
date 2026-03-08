#!/usr/bin/env bash
# =============================================================
# deploy.sh — 進銷存系統部署腳本
# 用法：./scripts/deploy.sh [environment] [docker compose 額外參數]
#   environment: production（預設）、development 等
# 範例：
#   ./scripts/deploy.sh production
#   ./scripts/deploy.sh development --build
# =============================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DOCKER_DIR="$PROJECT_DIR/docker"

ENV="${1:-production}"
shift || true  # 移除第一個參數，剩餘的傳給 docker compose

ENV_FILE="$DOCKER_DIR/.env"
ENV_TEMPLATE="$DOCKER_DIR/envs/.env.$ENV"

# ─────────────────────────────────────────────
# 檢查 docker/.env 是否存在
# ─────────────────────────────────────────────
if [ ! -f "$ENV_FILE" ]; then
  if [ ! -f "$ENV_TEMPLATE" ]; then
    echo "錯誤：找不到環境設定檔 ${ENV_TEMPLATE}"
    echo "請先建立 docker/envs/.env.${ENV}，或手動建立 docker/.env"
    exit 1
  fi
  echo "✓ 從 ${ENV_TEMPLATE} 複製環境設定到 ${ENV_FILE}"
  cp "$ENV_TEMPLATE" "$ENV_FILE"
else
  echo "✓ 使用現有的 ${ENV_FILE}"
fi

echo "▶ 啟動環境：${ENV}"

# production 額外疊加 docker-compose.prod.yml（含 nuxt）
COMPOSE_FILES=("-f" "$DOCKER_DIR/docker-compose.yml")
if [ "$ENV" = "production" ]; then
  COMPOSE_FILES+=("-f" "$DOCKER_DIR/docker-compose.prod.yml")
fi

docker compose \
  --project-directory "$DOCKER_DIR" \
  --env-file "$ENV_FILE" \
  "${COMPOSE_FILES[@]}" \
  up -d --build "$@"

echo "✓ 部署完成（環境：${ENV}）"
