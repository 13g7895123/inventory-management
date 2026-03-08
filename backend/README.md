# Backend — CodeIgniter 4 API

PHP 8.2 + CodeIgniter 4.5 所建立的 REST API 後端。

## 目錄結構

```
backend/
├── app/
│   ├── Config/
│   │   ├── Events.php        # Event ↔ Listener 綁定
│   │   ├── Filters.php       # auth / cors Filter 別名與全域設定
│   │   ├── Routes.php        # API 路由定義
│   │   └── Services.php      # DI 容器（service() 函式入口）
│   ├── Controllers/
│   │   └── Api/V1/           # 所有 API Controller
│   ├── Database/
│   │   ├── Migrations/       # 資料庫版本控制
│   │   └── Seeds/            # 初始資料
│   ├── Entities/             # CI4 Entity（欄位 cast、mutator）
│   ├── Events/               # 領域事件（StockDeducted 等）
│   ├── Filters/              # HTTP Filter（AuthFilter、CorsFilter）
│   ├── Helpers/
│   │   └── response_helper.php  # api_success() / api_error() 輔助函式
│   ├── Libraries/
│   │   └── JWT/JWTService.php   # Access + Refresh Token 管理
│   ├── Listeners/            # 事件監聽器（記錄交易、低庫存通知）
│   ├── Models/               # CI4 Model（DB 查詢）
│   ├── Repositories/         # Repository 模式（隔離 Model 查詢邏輯）
│   │   └── Contracts/        # Repository Interface
│   ├── Services/             # 業務邏輯層
│   └── Validation/           # 自訂驗證規則
└── tests/
    ├── unit/                 # 單元測試（Mock DB）
    └── feature/              # 功能測試（需連接 DB）
```

## 架構分層

```
HTTP Request
    │
    ▼
Filter (AuthFilter / CorsFilter)
    │
    ▼
Controller  ──►  Service  ──►  Repository  ──►  Model  ──►  DB
                    │                               │
                    │                               ▼
                    │                            Entity
                    ▼
                 Event ──► Listener
```

| 層 | 職責 |
|----|------|
| **Controller** | 接收請求、驗證輸入、回傳 JSON |
| **Service** | 業務邏輯（交易、狀態流轉）|
| **Repository** | 封裝查詢邏輯，實作 Interface |
| **Model** | CI4 ORM，對應資料表 |
| **Entity** | 資料列物件，提供 cast / mutator |
| **Event / Listener** | 非同步後置作業（庫存記錄、通知）|

## 環境需求

- PHP 8.2+
- Composer 2.x
- MySQL 8.0

（建議直接使用 Docker，免安裝本機環境）

## 環境變數

| 變數 | 說明 | 預設值 |
|------|------|--------|
| `CI_ENVIRONMENT` | `development` / `production` / `testing` | `development` |
| `APP_BASEURL` | API Base URL | `http://localhost/` |
| `JWT_SECRET` | JWT 簽名密鑰（請改為 256-bit 隨機字串） | `change-me` |
| `JWT_TTL` | Access Token 有效秒數 | `3600`（1 小時）|
| `JWT_REFRESH_TTL` | Refresh Token 有效秒數 | `604800`（7 天）|

> 環境變數存放於 `docker/.env`，範本見 `docker/envs/.env.example`。

## 認證機制

採用 **JWT（HS256）+ Refresh Token 輪換** 機制。

```
POST /api/v1/auth/login
  → 回傳 access_token（1 小時）+ refresh_token（7 天）

POST /api/v1/auth/refresh
  → 舊 refresh_token 立即失效，核發新的 token 組

POST /api/v1/auth/logout
  → 撤銷指定 refresh_token

GET  /api/v1/auth/me  [需 Bearer Token]
  → 回傳目前登入者資訊
```

**Request 範例**

```bash
# 登入
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "Admin@12345"}'

# 呼叫受保護 API
curl http://localhost/api/v1/auth/me \
  -H "Authorization: Bearer <access_token>"
```

**Response 格式**

```json
{
  "success": true,
  "message": "登入成功",
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "abc123...",
    "expires_in": 3600,
    "user": {
      "id": 1,
      "username": "admin",
      "name": "System Administrator",
      "role": "admin"
    }
  }
}
```

## API 路由一覽

所有路由前綴為 `/api/v1`，除登入與 refresh 外均需帶 `Authorization: Bearer <token>`。

### 認證

| Method | 路徑 | 說明 |
|--------|------|------|
| POST | `/auth/login` | 登入 |
| POST | `/auth/refresh` | Refresh Token 換新 Token |
| POST | `/auth/logout` | 登出 |
| GET | `/auth/me` | 取得目前使用者資訊 |

### 商品管理

| Method | 路徑 | 說明 |
|--------|------|------|
| GET | `/items` | 商品列表 |
| POST | `/items` | 新增商品 |
| GET | `/items/:id` | 商品詳情 |
| PUT | `/items/:id` | 更新商品 |
| DELETE | `/items/:id` | 刪除商品 |
| GET | `/items/:id/skus` | SKU 列表 |
| POST | `/items/:id/skus` | 新增 SKU |
| PUT | `/skus/:id` | 更新 SKU |
| DELETE | `/skus/:id` | 刪除 SKU |

### 庫存

| Method | 路徑 | 說明 |
|--------|------|------|
| GET | `/inventories` | 庫存列表 |
| GET | `/inventories/low-stock` | 低庫存商品 |
| GET | `/skus/:id/inventories` | 指定 SKU 庫存 |
| POST | `/inventories/adjust` | 手動調整庫存 |

### 採購

| Method | 路徑 | 說明 |
|--------|------|------|
| GET | `/purchase-orders` | 採購單列表 |
| POST | `/purchase-orders` | 建立採購單 |
| GET | `/purchase-orders/:id` | 採購單詳情 |
| POST | `/purchase-orders/:id/submit` | 送審 |
| POST | `/purchase-orders/:id/approve` | 審核通過 |
| POST | `/purchase-orders/:id/cancel` | 取消 |
| POST | `/purchase-orders/:id/receive` | 收貨入庫 |

### 銷售

| Method | 路徑 | 說明 |
|--------|------|------|
| GET | `/sales-orders` | 銷售單列表 |
| POST | `/sales-orders` | 建立銷售單 |
| GET | `/sales-orders/:id` | 銷售單詳情 |
| POST | `/sales-orders/:id/confirm` | 確認 |
| POST | `/sales-orders/:id/ship` | 出貨 |
| POST | `/sales-orders/:id/cancel` | 取消 |

### 倉庫 / 基礎資料 / 報表

| Method | 路徑 | 說明 |
|--------|------|------|
| GET/POST | `/warehouses` | 倉庫 CRUD |
| GET/POST | `/categories` | 商品類別 |
| GET | `/units` | 單位列表 |
| GET/POST | `/suppliers` | 供應商 |
| GET | `/reports/inventory-valuation` | 庫存估值 |
| GET | `/reports/stock-movement` | 庫存異動 |
| GET | `/reports/turnover-rate` | 庫存周轉率 |

## 資料庫

### Migration

```bash
# 執行（透過 Docker 腳本）
./scripts/migrate.sh

# 指定環境
./scripts/migrate.sh --env=testing

# Rollback 最後一批
./scripts/migrate.sh rollback --batch=1
```

### Seeder

```bash
./scripts/seed.sh                  # 執行 DatabaseSeeder（全部）
./scripts/seed.sh RoleSeeder       # 只執行指定 Seeder
```

預設 Seeder 建立的資料：

| 資料 | 內容 |
|------|------|
| 角色 | admin / manager / purchase_staff / sales_staff / warehouse_staff |
| 管理員帳號 | `admin` / `Admin@12345` |

## 測試

```bash
# 全部測試
./scripts/test.sh

# 只跑單元測試（不需 DB）
./scripts/test.sh --testsuite unit

# 只跑功能測試（需連接 DB）
./scripts/test.sh --testsuite feature

# 指定測試檔案
./scripts/test.sh tests/unit/Services/AuthServiceTest.php

# 指定測試方法
./scripts/test.sh --filter testLoginSuccessReturnsTokens
```

功能測試使用獨立的 `inventory_test` 資料庫，設定於 `phpunit.xml`。

## 開發工具

```bash
# Code Style 修正（PSR-12）
docker compose --project-directory docker exec php-fpm composer cs-fix

# Code Style 檢查（不修改）
docker compose --project-directory docker exec php-fpm composer cs-check
```
