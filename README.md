# 進銷存系統

基於 CodeIgniter 4 + Nuxt 3 的進銷存管理系統。

## 技術棧

| 層級 | 技術 |
|------|------|
| 後端 API | PHP 8.2 + CodeIgniter 4.5 |
| 前端 SPA | Nuxt 3 + Tailwind CSS（Bun） |
| 資料庫 | MySQL 8.0 |
| 快取 | Redis 7 |
| 物件儲存 | MinIO |
| 容器化 | Docker Compose |

## 快速開始

### 1. 設定環境

```bash
# 複製環境設定檔
cp docker/envs/.env.development docker/.env

# 依需求修改 docker/.env（資料庫密碼、JWT_SECRET 等）
```

### 2. 啟動服務

```bash
# 開發環境（後端）
./scripts/deploy.sh development

# 正式環境（後端 + 前端）
./scripts/deploy.sh production
```

### 3. 初始化資料庫

```bash
./scripts/migrate.sh        # 執行所有 Migration
./scripts/seed.sh           # 匯入預設資料（角色、管理員帳號）
```

預設管理員帳號：

| 帳號 | 密碼 |
|------|------|
| `admin` | `Admin@12345` |

### 4. 執行測試

```bash
./scripts/test.sh                          # 全部測試
./scripts/test.sh --testsuite unit         # 單元測試
./scripts/test.sh --testsuite feature      # 功能測試
```

## 目錄結構

```
.
├── backend/          # CodeIgniter 4 後端
├── frontend/         # Nuxt 3 前端
├── docker/           # Docker Compose 設定與環境檔
│   ├── envs/         # 環境設定範本
│   ├── mysql/        # MySQL init script
│   ├── nginx/        # Nginx 設定
│   └── php/          # PHP-FPM Dockerfile
├── scripts/          # 工具腳本
│   ├── deploy.sh     # 部署
│   ├── migrate.sh    # 執行 Migration
│   ├── seed.sh       # 執行 Seeder
│   └── test.sh       # 執行測試
└── docs/             # 需求與規格文件
```

## 詳細說明

- [後端開發說明](backend/README.md)
- [需求規格書](docs/進銷存系統需求規格書.md)
- [Phase 1 執行計畫](docs/Phase1執行計畫書.md)
