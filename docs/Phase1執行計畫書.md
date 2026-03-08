# 進銷存系統 Phase 1 — 技術執行計畫書

**文件版本**：v1.0  
**建立日期**：2026-03-07  
**適用範圍**：Phase 1 MVP  
**技術棧**：Nuxt 3（前端）+ CodeIgniter 4（後端 API）

---

## 目錄

1. [技術架構總覽](#1-技術架構總覽)
2. [後端：CodeIgniter 4 規範與架構](#2-後端codeigniter-4-規範與架構)
3. [前端：Nuxt 3 規範與架構](#3-前端nuxt-3-規範與架構)
4. [資料庫設計](#4-資料庫設計)
5. [API 設計規範](#5-api-設計規範)
6. [開發環境與工具](#6-開發環境與工具)
7. [專案目錄結構](#7-專案目錄結構)
8. [Sprint 開發計畫](#8-sprint-開發計畫)
9. [測試策略](#9-測試策略)
10. [部署策略](#10-部署策略)
11. [開發規範與 Git 流程](#11-開發規範與-git-流程)

---

## 1. 技術架構總覽

### 1.1 系統架構圖

```
┌─────────────────────────────────────────────────────┐
│                    使用者瀏覽器                        │
│              Nuxt 3 SPA / SSR (前端)                  │
│         Vue 3 + Pinia + Tailwind CSS + shadcn/vue    │
└──────────────────┬──────────────────────────────────┘
                   │ HTTPS / REST API (JSON)
                   │ Bearer Token (JWT)
┌──────────────────▼──────────────────────────────────┐
│              CodeIgniter 4 RESTful API               │
│          (PHP 8.2+ / Nginx / PHP-FPM)               │
│  ┌─────────┐  ┌──────────┐  ┌──────────────────┐   │
│  │ Routes  │  │Middleware│  │  Controllers      │   │
│  └─────────┘  └──────────┘  └──────────────────┘   │
│  ┌──────────────┐  ┌────────────────────────────┐   │
│  │  Services    │  │  Models (Query Builder)    │   │
│  └──────────────┘  └────────────────────────────┘   │
└──────────────────┬──────────────────────────────────┘
                   │
┌──────────────────▼──────────────────────────────────┐
│              MySQL 8.0+ (主資料庫)                    │
│              Redis 7+ (Session / Cache / Queue)      │
│              MinIO / S3 (檔案儲存)                    │
└─────────────────────────────────────────────────────┘
```

### 1.2 技術選型說明

| 層級 | 技術 | 版本 | 選型原因 |
|------|------|------|----------|
| 前端框架 | Nuxt 3 | 3.x (latest) | Vue 3 生態、SSR/SPA 彈性、內建路由/狀態管理 |
| 前端 UI | shadcn-vue + Tailwind CSS | latest | 高品質元件庫、完全可客製化、無樣式鎖定 |
| 前端狀態管理 | Pinia | 2.x | Vue 3 官方推薦、輕量、TypeScript 支援 |
| 前端 HTTP Client | ofetch / $fetch | 內建 | Nuxt 內建、支援 SSR/CSR 自動切換 |
| 後端框架 | CodeIgniter 4 | 4.5+ | 輕量、高效能、RESTful 支援完整、PHP 專案常見 |
| 後端 PHP | PHP | 8.2+ | JIT 效能、型別宣告、enum 支援 |
| 資料庫 | MySQL | 8.0+ | 成熟穩定、JSON 欄位支援、全文搜尋 |
| 快取/佇列 | Redis | 7+ | Session 儲存、API 快取、背景job queue |
| 檔案儲存 | MinIO (本地) / S3 (雲端) | - | 商品圖片、PDF 文件儲存 |
| 容器化 | Docker + Docker Compose | - | 開發/測試/生產環境一致性 |
| 反向代理 | Nginx | 1.25+ | 靜態資源服務、API 代理、SSL 終止 |

### 1.3 前後端分離架構

- 後端僅提供 **REST API**，不渲染任何 HTML
- 前端以 Nuxt 3 的 **SPA 模式**（`ssr: false`）開發後台管理系統
- 前後端透過 **JWT Bearer Token** 進行身份驗證
- 跨域（CORS）由後端統一管理白名單

---

## 2. 後端：CodeIgniter 4 規範與架構

### 2.1 目錄結構

```
backend/
├── app/
│   ├── Config/
│   │   ├── App.php
│   │   ├── Auth.php              # JWT 設定
│   │   ├── Cors.php              # CORS 白名單設定
│   │   ├── Database.php
│   │   ├── Events.php            # 事件與監聽器綁定
│   │   ├── Services.php          # DI 容器服務綁定
│   │   └── Routes.php
│   ├── Controllers/
│   │   └── Api/
│   │       ├── V1/
│   │       │   ├── Auth/
│   │       │   │   └── AuthController.php
│   │       │   ├── Items/
│   │       │   │   ├── ItemController.php
│   │       │   │   ├── CategoryController.php
│   │       │   │   └── UnitController.php
│   │       │   ├── Purchase/
│   │       │   │   ├── SupplierController.php
│   │       │   │   ├── PurchaseOrderController.php
│   │       │   │   └── GoodsReceiptController.php
│   │       │   ├── Sales/
│   │       │   │   ├── CustomerController.php
│   │       │   │   ├── SalesOrderController.php
│   │       │   │   └── ShipmentController.php
│   │       │   ├── Inventory/
│   │       │   │   ├── InventoryController.php
│   │       │   │   └── StocktakeController.php
│   │       │   ├── Warehouse/
│   │       │   │   └── WarehouseController.php
│   │       │   └── Reports/
│   │       │       └── ReportController.php
│   │       └── BaseApiController.php
│   ├── Database/
│   │   ├── Migrations/           # 所有 Migration 檔案
│   │   └── Seeds/                # 初始資料
│   ├── Entities/                 # CI4 Entity：資料列物件（含型別轉換與商業屬性）
│   │   ├── BaseEntity.php        # 共用 Entity 基底
│   │   ├── Item.php
│   │   ├── ItemSku.php
│   │   ├── Inventory.php
│   │   ├── PurchaseOrder.php
│   │   └── SalesOrder.php
│   ├── Events/                   # CI4 Events：業務領域事件（解耦副作用）
│   │   ├── StockReplenished.php  # 庫存入庫事件（採購驗收後觸發）
│   │   ├── StockDeducted.php     # 庫存出庫事件（出貨後觸發）
│   │   ├── PurchaseOrderApproved.php
│   │   └── SalesOrderConfirmed.php
│   ├── Filters/
│   │   ├── AuthFilter.php        # JWT 驗證 Filter
│   │   ├── CorsFilter.php        # CORS 處理
│   │   └── RateLimitFilter.php   # API 限速
│   ├── Helpers/
│   │   ├── response_helper.php   # 統一 API 回應格式
│   │   └── number_helper.php
│   ├── Libraries/
│   │   ├── JWT/
│   │   │   └── JWTService.php
│   │   └── Upload/
│   │       └── FileUploadService.php
│   ├── Listeners/                # 事件監聽器：處理事件的副作用
│   │   ├── LogInventoryTransaction.php  # 寫入異動日誌
│   │   └── SendLowStockAlert.php        # 低庫存通知
│   ├── Models/                   # CI4 Model：資料庫存取層（Query Builder）
│   │   ├── ItemModel.php
│   │   ├── ItemSkuModel.php
│   │   ├── SupplierModel.php
│   │   ├── PurchaseOrderModel.php
│   │   ├── PurchaseOrderLineModel.php
│   │   ├── GoodsReceiptModel.php
│   │   ├── CustomerModel.php
│   │   ├── SalesOrderModel.php
│   │   ├── SalesOrderLineModel.php
│   │   ├── ShipmentModel.php
│   │   ├── InventoryModel.php
│   │   ├── InventoryTransactionModel.php
│   │   ├── WarehouseModel.php
│   │   ├── StocktakeModel.php
│   │   └── UserModel.php
│   ├── Repositories/             # Repository：封裝查詢邏輯，Service 不直接碰 Model
│   │   ├── Contracts/
│   │   │   ├── RepositoryInterface.php
│   │   │   ├── ItemRepositoryInterface.php
│   │   │   └── InventoryRepositoryInterface.php
│   │   ├── BaseRepository.php
│   │   ├── ItemRepository.php
│   │   └── InventoryRepository.php
│   ├── Services/                 # Service：業務邏輯核心，協調 Repository 與 Events
│   │   ├── ItemService.php
│   │   ├── PurchaseOrderService.php
│   │   ├── GoodsReceiptService.php
│   │   ├── SalesOrderService.php
│   │   ├── ShipmentService.php
│   │   ├── InventoryService.php
│   │   ├── StocktakeService.php
│   │   └── ReportService.php
│   └── Validation/               # 自訂驗證規則
│       └── CustomRules.php
├── tests/
│   ├── unit/
│   └── feature/
├── writable/
│   ├── cache/
│   ├── logs/
│   └── uploads/
├── .env.example
├── composer.json
└── phpunit.xml
```

### 2.2 最佳實踐：完整分層架構

CI4 採用 **Controller → Service → Repository → Model → Entity** 五層分離，搭配 **Events / Listeners** 解耦副作用：

```
HTTP Request
     │
     ▼
┌─────────────────────────────────────────────────┐
│   Controller  (API Layer)                        │
│   只負責：請求接收、輸入驗證、回應格式化              │
│   不含任何業務邏輯                                 │
└─────────────────┬───────────────────────────────┘
                  │ 呼叫
                  ▼
┌─────────────────────────────────────────────────┐
│   Service  (Business Layer)                      │
│   業務邏輯核心：流程控制、DB Transaction、          │
│   觸發 Events、協調多個 Repository               │
└────────┬──────────────────────────┬─────────────┘
         │ 呼叫                      │ 觸發
         ▼                           ▼
┌─────────────────┐       ┌─────────────────────┐
│   Repository    │       │   Events / Listeners │
│   封裝查詢邏輯   │       │   副作用：日誌、通知、  │
│   介面與實作分離 │       │   快取失效、佇列任務   │
└────────┬────────┘       └─────────────────────┘
         │ 呼叫
         ▼
┌─────────────────────────────────────────────────┐
│   Model  (Data Layer)                            │
│   CI4 Model：資料庫 CRUD、Query Builder           │
│   定義 returnType = Entity 類別                   │
└─────────────────┬───────────────────────────────┘
                  │ 回傳
                  ▼
┌─────────────────────────────────────────────────┐
│   Entity  (Data Object)                          │
│   代表一筆資料列；型別轉換（cast）、              │
│   唯讀計算屬性（virtual field）                   │
└─────────────────────────────────────────────────┘
```

**各層職責說明：**

| 層級 | 職責 | CI4 對應 |
|------|------|----------|
| **Controller** | 接收請求、驗證格式、呼叫 Service、回傳 JSON | `app/Controllers/` |
| **Service** | 業務流程、DB Transaction、觸發事件 | `app/Services/`（手工類別） |
| **Repository** | 封裝查詢邏輯，提供語意化方法；介面與實作分離 | `app/Repositories/` |
| **Model** | CI4 Query Builder 存取層，設定 Entity 回傳型別 | `app/Models/` |
| **Entity** | 強型別資料物件；`$casts` 型別轉換；計算屬性 | `app/Entities/`，繼承 `CodeIgniter\Entity\Entity` |
| **Events** | 業務領域事件物件（純資料結構） | `app/Events/`，搭配 `Events::trigger()` |
| **Listeners** | 響應事件的副作用處理（日誌、通知等） | `app/Listeners/`，綁定於 `app/Config/Events.php` |

---

**範例 — Entity（Inventory.php）：**

```php
// app/Entities/Inventory.php
<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Inventory extends Entity
{
    protected $casts = [
        'id'            => 'integer',
        'sku_id'        => 'integer',
        'warehouse_id'  => 'integer',
        'on_hand_qty'   => 'float',
        'reserved_qty'  => 'float',
        'on_order_qty'  => 'float',
        'avg_cost'      => 'float',
    ];

    /**
     * 可用庫存（計算屬性）= 在庫 − 預留
     */
    public function getAvailableQty(): float
    {
        return $this->on_hand_qty - $this->reserved_qty;
    }

    /**
     * 是否低於安全庫存
     */
    public function isBelowSafetyStock(float $safetyStock): bool
    {
        return $this->getAvailableQty() < $safetyStock;
    }
}
```

---

**範例 — Repository Interface + 實作（InventoryRepository.php）：**

```php
// app/Repositories/Contracts/InventoryRepositoryInterface.php
<?php

namespace App\Repositories\Contracts;

use App\Entities\Inventory;

interface InventoryRepositoryInterface
{
    public function findBySkuAndWarehouse(int $skuId, int $warehouseId): ?Inventory;
    public function lockForUpdate(int $skuId, int $warehouseId): ?Inventory;
    public function updateQuantities(int $id, float $onHandQty, float $reservedQty): bool;
    public function findLowStock(float $threshold): array;
}
```

```php
// app/Repositories/InventoryRepository.php
<?php

namespace App\Repositories;

use App\Entities\Inventory;
use App\Models\InventoryModel;
use App\Repositories\Contracts\InventoryRepositoryInterface;

class InventoryRepository extends BaseRepository implements InventoryRepositoryInterface
{
    public function __construct(private readonly InventoryModel $model) {}

    public function findBySkuAndWarehouse(int $skuId, int $warehouseId): ?Inventory
    {
        return $this->model
            ->where('sku_id', $skuId)
            ->where('warehouse_id', $warehouseId)
            ->first();
    }

    public function lockForUpdate(int $skuId, int $warehouseId): ?Inventory
    {
        return $this->model
            ->where('sku_id', $skuId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();
    }

    public function updateQuantities(int $id, float $onHandQty, float $reservedQty): bool
    {
        return $this->model->update($id, [
            'on_hand_qty'  => $onHandQty,
            'reserved_qty' => $reservedQty,
        ]);
    }

    public function findLowStock(float $threshold): array
    {
        return $this->model
            ->where('(on_hand_qty - reserved_qty) <', $threshold)
            ->findAll();
    }
}
```

---

**範例 — Events + Listeners：**

```php
// app/Events/StockDeducted.php
<?php

namespace App\Events;

/**
 * 庫存出庫事件（出貨後由 InventoryService 觸發）
 */
readonly class StockDeducted
{
    public function __construct(
        public int    $skuId,
        public int    $warehouseId,
        public float  $quantity,
        public string $sourceType,
        public int    $sourceId,
        public int    $operatorId,
    ) {}
}
```

```php
// app/Config/Events.php — 綁定事件與監聽器
<?php

namespace Config;

use App\Events\StockDeducted;
use App\Events\StockReplenished;
use App\Listeners\LogInventoryTransaction;
use App\Listeners\SendLowStockAlert;
use CodeIgniter\Events\Events;

Events::on(StockDeducted::class, [LogInventoryTransaction::class, 'handle']);
Events::on(StockDeducted::class, [SendLowStockAlert::class, 'handle']);
Events::on(StockReplenished::class, [LogInventoryTransaction::class, 'handle']);
```

```php
// app/Listeners/SendLowStockAlert.php
<?php

namespace App\Listeners;

use App\Events\StockDeducted;
use App\Repositories\Contracts\InventoryRepositoryInterface;

class SendLowStockAlert
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepo
    ) {}

    public function handle(StockDeducted $event): void
    {
        $inventory = $this->inventoryRepo->findBySkuAndWarehouse(
            $event->skuId,
            $event->warehouseId
        );

        if ($inventory === null) {
            return;
        }

        // TODO: 以實際安全庫存值替換（從 item_skus 取得）
        $safetyStock = 10.0;

        if ($inventory->isBelowSafetyStock($safetyStock)) {
            // 發送低庫存通知（Email / Webhook / Redis Queue）
            log_message('warning', "低庫存警示：SKU {$event->skuId} 倉庫 {$event->warehouseId} 可用庫存 {$inventory->getAvailableQty()}");
        }
    }
}
```

---

**範例 — Service 整合所有層（InventoryService）：**

```php
// app/Services/InventoryService.php
<?php

namespace App\Services;

use App\Events\StockDeducted;
use App\Models\InventoryTransactionModel;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use CodeIgniter\Events\Events;

class InventoryService
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepo,
        private readonly InventoryTransactionModel    $txModel,
    ) {}

    /**
     * 執行庫存出庫（附 DB Transaction 保護）
     *
     * @throws \RuntimeException 庫存不足時拋出
     */
    public function deductStock(
        int    $skuId,
        int    $warehouseId,
        float  $quantity,
        string $sourceType,
        int    $sourceId,
        int    $operatorId
    ): void {
        $db = db_connect();
        $db->transStart();

        try {
            $inventory = $this->inventoryRepo->lockForUpdate($skuId, $warehouseId);

            if ($inventory === null || $inventory->getAvailableQty() < $quantity) {
                throw new \RuntimeException('庫存不足，無法出庫');
            }

            $this->inventoryRepo->updateQuantities(
                $inventory->id,
                $inventory->on_hand_qty - $quantity,
                $inventory->reserved_qty - $quantity,
            );

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        // Transaction 完成後才觸發事件（Listener 負責寫日誌與通知）
        Events::trigger(StockDeducted::class, new StockDeducted(
            skuId:       $skuId,
            warehouseId: $warehouseId,
            quantity:    $quantity,
            sourceType:  $sourceType,
            sourceId:    $sourceId,
            operatorId:  $operatorId,
        ));
    }
}
```

---

**Repository 的 DI 綁定（app/Config/Services.php）：**

```php
// app/Config/Services.php
<?php

namespace Config;

use App\Models\InventoryModel;
use App\Models\ItemModel;
use App\Repositories\InventoryRepository;
use App\Repositories\ItemRepository;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use CodeIgniter\Config\BaseService;

class Services extends BaseService
{
    public static function inventoryRepository(bool $getShared = true): InventoryRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('inventoryRepository');
        }
        return new InventoryRepository(new InventoryModel());
    }

    public static function itemRepository(bool $getShared = true): ItemRepositoryInterface
    {
        if ($getShared) {
            return static::getSharedInstance('itemRepository');
        }
        return new ItemRepository(new ItemModel());
    }
}
```

### 2.3 統一 API 回應格式

所有 API 回應必須遵循以下 JSON 結構：

```php
// app/Helpers/response_helper.php

function api_success(mixed $data = null, string $message = 'OK', int $statusCode = 200): ResponseInterface
{
    return service('response')
        ->setStatusCode($statusCode)
        ->setJSON([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
}

function api_error(string $message, int $statusCode = 400, mixed $errors = null): ResponseInterface
{
    return service('response')
        ->setStatusCode($statusCode)
        ->setJSON([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ]);
}

function api_paginated(array $data, array $pagination): ResponseInterface
{
    return service('response')
        ->setStatusCode(200)
        ->setJSON([
            'success'    => true,
            'message'    => 'OK',
            'data'       => $data,
            'pagination' => [
                'current_page' => $pagination['current_page'],
                'per_page'     => $pagination['per_page'],
                'total'        => $pagination['total'],
                'total_pages'  => $pagination['total_pages'],
            ],
        ]);
}
```

### 2.4 JWT 認證實作

使用 `firebase/php-jwt` 套件：

```php
// app/Filters/AuthFilter.php
<?php

namespace App\Filters;

use App\Libraries\JWT\JWTService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return api_error('未授權：缺少 Token', 401);
        }

        $token = substr($authHeader, 7);

        try {
            $payload = (new JWTService())->verify($token);
            // 將 userId 注入 Request，供後續 Controller 使用
            $request->userId = $payload->sub;
            $request->userRole = $payload->role;
        } catch (\Exception $e) {
            return api_error('未授權：Token 無效或已過期', 401);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
```

### 2.5 Model 最佳實踐

```php
// app/Models/ItemModel.php
<?php

namespace App\Models;

use CodeIgniter\Model;

class ItemModel extends Model
{
    protected $table            = 'items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;      // 軟刪除
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';

    // 明確宣告允許大量賦值的欄位（防 Mass Assignment）
    protected $allowedFields = [
        'category_id', 'name', 'description', 'unit_id',
        'tax_type', 'reorder_point', 'safety_stock', 'is_active',
    ];

    // 表單驗證規則
    protected $validationRules = [
        'name'        => 'required|min_length[1]|max_length[255]',
        'category_id' => 'required|integer|is_not_unique[categories.id]',
        'unit_id'     => 'required|integer|is_not_unique[units.id]',
        'tax_type'    => 'required|in_list[taxable,zero,exempt]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => '商品名稱為必填',
        ],
    ];

    // 查詢 Scope：僅取啟用商品
    public function active(): static
    {
        return $this->where('is_active', true);
    }

    // 帶分類資訊的查詢
    public function withCategory(): static
    {
        return $this->select('items.*, categories.name as category_name')
                    ->join('categories', 'categories.id = items.category_id');
    }
}
```

### 2.6 路由規範

```php
// app/Config/Routes.php
$routes->group('api/v1', ['filter' => 'cors'], static function ($routes) {

    // 公開路由（無需 JWT）
    $routes->post('auth/login',   'Api\V1\Auth\AuthController::login');
    $routes->post('auth/refresh', 'Api\V1\Auth\AuthController::refresh');

    // 需要 JWT 的路由群組
    $routes->group('', ['filter' => 'auth'], static function ($routes) {

        // 商品管理
        $routes->resource('items', [
            'controller' => 'Api\V1\Items\ItemController',
            'placeholder' => '(:num)',
        ]);
        $routes->post('items/import',         'Api\V1\Items\ItemController::import');
        $routes->get('items/(:num)/skus',     'Api\V1\Items\ItemController::skus/$1');

        // 採購管理
        $routes->resource('suppliers',        ['controller' => 'Api\V1\Purchase\SupplierController']);
        $routes->resource('purchase-orders',  ['controller' => 'Api\V1\Purchase\PurchaseOrderController']);
        $routes->post('purchase-orders/(:num)/approve', 'Api\V1\Purchase\PurchaseOrderController::approve/$1');
        $routes->resource('goods-receipts',   ['controller' => 'Api\V1\Purchase\GoodsReceiptController']);

        // 銷售管理
        $routes->resource('customers',        ['controller' => 'Api\V1\Sales\CustomerController']);
        $routes->resource('sales-orders',     ['controller' => 'Api\V1\Sales\SalesOrderController']);
        $routes->post('sales-orders/(:num)/confirm', 'Api\V1\Sales\SalesOrderController::confirm/$1');
        $routes->resource('shipments',        ['controller' => 'Api\V1\Sales\ShipmentController']);

        // 庫存管理
        $routes->get('inventory',             'Api\V1\Inventory\InventoryController::index');
        $routes->get('inventory/(:num)/transactions', 'Api\V1\Inventory\InventoryController::transactions/$1');
        $routes->post('inventory/transfer',   'Api\V1\Inventory\InventoryController::transfer');
        $routes->resource('stocktakes',       ['controller' => 'Api\V1\Inventory\StocktakeController']);

        // 倉庫管理
        $routes->resource('warehouses',       ['controller' => 'Api\V1\Warehouse\WarehouseController']);

        // 報表
        $routes->get('reports/inventory-summary', 'Api\V1\Reports\ReportController::inventorySummary');
        $routes->get('reports/sales',             'Api\V1\Reports\ReportController::sales');
        $routes->get('reports/purchase',          'Api\V1\Reports\ReportController::purchase');
        $routes->get('reports/profit',            'Api\V1\Reports\ReportController::profit');
    });
});
```

### 2.7 後端套件清單（composer.json）

```json
{
    "require": {
        "php": "^8.2",
        "codeigniter4/framework": "^4.5",
        "firebase/php-jwt": "^6.10",
        "vlucas/phpdotenv": "^5.6",
        "league/flysystem": "^3.0",
        "league/flysystem-aws-s3-v3": "^3.0",
        "phpoffice/phpspreadsheet": "^3.0",
        "dompdf/dompdf": "^2.0",
        "predis/predis": "^2.2"
    },
    "require-dev": {
        "codeigniter4/devkit": "^1.2",
        "fakerphp/faker": "^1.23",
        "phpunit/phpunit": "^10.5"
    }
}
```

---

## 3. 前端：Nuxt 3 規範與架構

### 3.1 目錄結構

```
frontend/
├── app/
│   ├── assets/
│   │   ├── css/
│   │   │   └── main.css          # Tailwind CSS 入口
│   │   └── images/
│   ├── components/
│   │   ├── ui/                   # shadcn-vue 元件（自動匯入）
│   │   ├── common/               # 通用元件
│   │   │   ├── AppDataTable.vue  # 通用資料表格（含搜尋/排序/分頁）
│   │   │   ├── AppFormModal.vue  # 通用表單 Modal
│   │   │   ├── AppPageHeader.vue # 頁面標題列
│   │   │   ├── AppStatusBadge.vue
│   │   │   └── AppConfirmDialog.vue
│   │   ├── items/
│   │   │   ├── ItemForm.vue
│   │   │   └── ItemSkuTable.vue
│   │   ├── purchase/
│   │   │   ├── PurchaseOrderForm.vue
│   │   │   └── PurchaseOrderLineTable.vue
│   │   ├── sales/
│   │   │   ├── SalesOrderForm.vue
│   │   │   └── SalesOrderLineTable.vue
│   │   └── inventory/
│   │       ├── StocktakeSheet.vue
│   │       └── InventoryTransactionTimeline.vue
│   ├── composables/
│   │   ├── useAuth.ts            # 登入/登出/Token 管理
│   │   ├── useApi.ts             # 封裝 $fetch，統一錯誤處理
│   │   ├── useToast.ts           # 通知提示
│   │   ├── useConfirm.ts         # 確認對話框
│   │   ├── usePagination.ts      # 分頁狀態管理
│   │   └── usePermission.ts      # 權限檢查
│   ├── layouts/
│   │   ├── default.vue           # 主要後台 Layout（側邊欄 + 頂部列）
│   │   └── auth.vue              # 登入頁 Layout
│   ├── middleware/
│   │   ├── auth.ts               # 路由守衛：未登入導向登入頁
│   │   └── permission.ts         # 路由守衛：無權限頁面處理
│   ├── pages/
│   │   ├── index.vue             # 首頁（重新導向至 dashboard）
│   │   ├── auth/
│   │   │   └── login.vue
│   │   ├── dashboard/
│   │   │   └── index.vue
│   │   ├── items/
│   │   │   ├── index.vue         # 商品列表
│   │   │   ├── [id].vue          # 商品詳情/編輯
│   │   │   └── create.vue        # 建立商品
│   │   ├── purchase/
│   │   │   ├── suppliers/
│   │   │   │   ├── index.vue
│   │   │   │   └── [id].vue
│   │   │   ├── orders/
│   │   │   │   ├── index.vue
│   │   │   │   ├── create.vue
│   │   │   │   └── [id].vue
│   │   │   └── receipts/
│   │   │       ├── index.vue
│   │   │       └── [id].vue
│   │   ├── sales/
│   │   │   ├── customers/
│   │   │   │   ├── index.vue
│   │   │   │   └── [id].vue
│   │   │   ├── orders/
│   │   │   │   ├── index.vue
│   │   │   │   ├── create.vue
│   │   │   │   └── [id].vue
│   │   │   └── shipments/
│   │   │       ├── index.vue
│   │   │       └── [id].vue
│   │   ├── inventory/
│   │   │   ├── index.vue         # 即時庫存查詢
│   │   │   ├── transfer.vue      # 庫存調撥
│   │   │   └── stocktake/
│   │   │       ├── index.vue
│   │   │       └── [id].vue
│   │   ├── warehouses/
│   │   │   ├── index.vue
│   │   │   └── [id].vue
│   │   └── reports/
│   │       ├── inventory-summary.vue
│   │       ├── sales.vue
│   │       ├── purchase.vue
│   │       └── profit.vue
│   ├── plugins/
│   │   └── api.ts                # 全域 $api 實例初始化
│   ├── stores/
│   │   ├── auth.ts               # Pinia：登入狀態、使用者資訊
│   │   ├── app.ts                # Pinia：全域 UI 狀態（側邊欄開合等）
│   │   └── notification.ts       # Pinia：全域通知佇列
│   └── types/
│       ├── api.ts                # API 回應通用型別
│       ├── item.ts
│       ├── purchase.ts
│       ├── sales.ts
│       └── inventory.ts
├── public/
│   └── favicon.ico
├── nuxt.config.ts
├── tailwind.config.ts
├── tsconfig.json
└── package.json
```

### 3.2 nuxt.config.ts 設定

```typescript
// nuxt.config.ts
export default defineNuxtConfig({
  ssr: false,                          // 後台管理系統採 SPA 模式

  devtools: { enabled: true },

  modules: [
    '@pinia/nuxt',
    '@nuxtjs/tailwindcss',
    'shadcn-nuxt',
    '@vueuse/nuxt',
    '@nuxtjs/i18n',                    // 多語言（預備）
  ],

  shadcn: {
    prefix: '',
    componentDir: './app/components/ui',
  },

  pinia: {
    storesDirs: ['./app/stores/**'],
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8080/api/v1',
    },
  },

  // 自動匯入 composables 與 components
  imports: {
    dirs: ['app/composables/**', 'app/types/**'],
  },

  typescript: {
    strict: true,
    typeCheck: true,
  },

  app: {
    head: {
      title: '進銷存管理系統',
      meta: [{ name: 'viewport', content: 'width=device-width, initial-scale=1' }],
    },
  },
})
```

### 3.3 核心 Composable：useApi

```typescript
// app/composables/useApi.ts
import type { FetchOptions } from 'ofetch'

interface ApiResponse<T> {
  success: boolean
  message: string
  data: T
  errors?: Record<string, string[]>
  pagination?: {
    current_page: number
    per_page: number
    total: number
    total_pages: number
  }
}

export function useApi() {
  const config = useRuntimeConfig()
  const authStore = useAuthStore()
  const toast = useToast()

  async function request<T>(
    endpoint: string,
    options: FetchOptions = {}
  ): Promise<ApiResponse<T>> {
    const token = authStore.token

    try {
      const response = await $fetch<ApiResponse<T>>(endpoint, {
        baseURL: config.public.apiBase,
        headers: {
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        ...options,
      })
      return response
    } catch (error: any) {
      const status = error?.response?.status
      const body = error?.response?._data

      // Token 過期 → 嘗試 refresh，失敗則登出
      if (status === 401) {
        const refreshed = await authStore.refreshToken()
        if (!refreshed) {
          await navigateTo('/auth/login')
          throw error
        }
        // 重試原始請求
        return request<T>(endpoint, options)
      }

      // 顯示錯誤通知
      toast.error(body?.message || '發生未知錯誤，請稍後再試')
      throw error
    }
  }

  return {
    get:    <T>(url: string, params?: Record<string, any>) =>
              request<T>(url, { method: 'GET', params }),
    post:   <T>(url: string, body?: unknown) =>
              request<T>(url, { method: 'POST', body }),
    put:    <T>(url: string, body?: unknown) =>
              request<T>(url, { method: 'PUT', body }),
    patch:  <T>(url: string, body?: unknown) =>
              request<T>(url, { method: 'PATCH', body }),
    delete: <T>(url: string) =>
              request<T>(url, { method: 'DELETE' }),
  }
}
```

### 3.4 Pinia Store 範例（auth.ts）

```typescript
// app/stores/auth.ts
import { defineStore } from 'pinia'

interface User {
  id: number
  name: string
  email: string
  role: string
  permissions: string[]
}

interface AuthState {
  user: User | null
  token: string | null
  refreshToken: string | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user:         null,
    token:        null,
    refreshToken: null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    can: (state) => (permission: string) =>
      state.user?.permissions.includes(permission) ?? false,
  },

  actions: {
    async login(email: string, password: string): Promise<void> {
      const { $fetch } = useNuxtApp()
      const config = useRuntimeConfig()

      const res = await $fetch<any>('/auth/login', {
        baseURL: config.public.apiBase,
        method: 'POST',
        body: { email, password },
      })

      this.token        = res.data.access_token
      this.refreshToken = res.data.refresh_token
      this.user         = res.data.user
    },

    async refreshToken(): Promise<boolean> {
      try {
        const config = useRuntimeConfig()
        const res = await $fetch<any>('/auth/refresh', {
          baseURL: config.public.apiBase,
          method: 'POST',
          body: { refresh_token: this.refreshToken },
        })
        this.token = res.data.access_token
        return true
      } catch {
        this.logout()
        return false
      }
    },

    logout(): void {
      this.user         = null
      this.token        = null
      this.refreshToken = null
    },
  },

  persist: {
    storage: persistedState.localStorage,  // 使用 nuxt-pinia-plugin-persistedstate
    pick: ['token', 'refreshToken', 'user'],
  },
})
```

### 3.5 Route Middleware（auth.ts）

```typescript
// app/middleware/auth.ts
export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()

  if (!authStore.isAuthenticated) {
    return navigateTo('/auth/login', {
      redirectCode: 302,
      query: { redirect: to.fullPath },
    })
  }
})
```

### 3.6 前端套件清單（package.json）

```json
{
  "dependencies": {
    "@nuxtjs/tailwindcss": "^6.x",
    "@pinia/nuxt": "^0.10.x",
    "@vueuse/nuxt": "^11.x",
    "nuxt": "^3.x",
    "pinia": "^2.x",
    "pinia-plugin-persistedstate": "^4.x",
    "shadcn-nuxt": "^0.x",
    "vue": "^3.x",
    "vue-router": "^4.x",
    "@tanstack/vue-table": "^8.x",
    "vue-sonner": "^1.x",
    "vee-validate": "^4.x",
    "@vee-validate/zod": "^4.x",
    "zod": "^3.x",
    "date-fns": "^4.x",
    "recharts": "latest",
    "@iconify/vue": "^4.x"
  },
  "devDependencies": {
    "@nuxt/devtools": "latest",
    "@nuxt/types": "latest",
    "typescript": "^5.x",
    "vue-tsc": "^2.x",
    "vitest": "^2.x",
    "@vue/test-utils": "^2.x",
    "eslint": "^9.x",
    "@nuxt/eslint": "latest",
    "prettier": "^3.x"
  }
}
```

---

## 4. 資料庫設計

### 4.1 資料庫設計原則

- 所有表格使用 `BIGINT UNSIGNED` 作為自動遞增主鍵
- 所有表格加入 `created_at`、`updated_at`、`deleted_at`（軟刪除）
- 金額欄位使用 `DECIMAL(15,4)`，避免浮點誤差
- 外鍵關係明確定義（搭配 `ON DELETE RESTRICT`，防止誤刪父資料）
- 所有 Migration 可回滾（實作 `down()` 方法）

### 4.2 Phase 1 資料表總覽

```
使用者與權限
├── users                  # 使用者帳號
├── roles                  # 角色（admin, manager, purchase...）
└── role_permissions       # 角色權限對應

商品管理
├── categories             # 商品分類（自參照樹狀）
├── units                  # 計量單位（個、箱、打）
├── unit_conversions       # 單位換算比例
├── items                  # 商品主檔
├── item_skus              # SKU 變體
├── item_barcodes          # 條碼（多對一 SKU）
└── item_images            # 商品圖片

採購管理
├── suppliers              # 供應商主檔
├── supplier_items         # 供應商可供應品項及報價
├── purchase_orders        # 採購單主表
├── purchase_order_lines   # 採購單明細
├── goods_receipts         # 進貨驗收單主表
└── goods_receipt_lines    # 進貨驗收單明細

銷售管理
├── customers              # 客戶主檔
├── customer_addresses     # 客戶收貨地址（一對多）
├── sales_orders           # 銷售訂單主表
├── sales_order_lines      # 銷售訂單明細
├── shipments              # 出貨單主表
└── shipment_lines         # 出貨單明細

庫存管理
├── warehouses             # 倉庫主檔
├── inventory              # 即時庫存（每個 sku+warehouse 一筆）
├── inventory_transactions # 庫存異動日誌（只增不改刪）
├── stock_transfers        # 庫存調撥單
├── stock_transfer_lines   # 庫存調撥明細
├── stocktakes             # 盤點任務主表
├── stocktake_lines        # 盤點明細（帳面量 vs 實盤量）
└── batch_serials          # 批號/序號記錄

財務（Phase 1 基礎）
├── invoices               # 發票（應收）
└── bills                  # 帳單（應付）

系統
└── settings               # 系統設定（Key-Value）
```

### 4.3 核心資料表 Migration 範例

```php
// app/Database/Migrations/2026-03-07-000001_CreateItemsTable.php
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateItemsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'category_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'unit_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'tax_type' => [
                'type'       => 'ENUM',
                'constraint' => ['taxable', 'zero', 'exempt'],
                'default'    => 'taxable',
            ],
            'reorder_point' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
            ],
            'safety_stock' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('unit_id', 'units', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addKey(['name'], false, false, 'idx_items_name');

        $this->forge->createTable('items');
    }

    public function down(): void
    {
        $this->forge->dropTable('items', true);
    }
}
```

```php
// inventory 庫存表設計（核心）
'inventory' 資料表欄位：
- id, sku_id (FK), warehouse_id (FK)
- on_hand_qty    DECIMAL(15,4)   # 實際在庫
- reserved_qty   DECIMAL(15,4)   # 已預留（SO 預留）
- on_order_qty   DECIMAL(15,4)   # 在途（已採購未到）
- avg_cost       DECIMAL(15,4)   # 加權平均成本
- UNIQUE KEY (sku_id, warehouse_id)

// inventory_transactions 庫存異動日誌（只增，不可改刪）
- id, sku_id (FK), warehouse_id (FK)
- transaction_type  ENUM('IN','OUT','TRANSFER_IN','TRANSFER_OUT','ADJUST','COUNT')
- quantity          DECIMAL(15,4)   # 正=入庫, 負=出庫
- unit_cost         DECIMAL(15,4)
- source_document_type VARCHAR(50)  # 'purchase_order', 'sales_order', 'transfer'...
- source_document_id   BIGINT
- batch_serial_id      BIGINT NULL
- notes               TEXT NULL
- performed_by         BIGINT (FK users.id)
- performed_at         DATETIME
```

---

## 5. API 設計規範

### 5.1 URL 命名規則

| 動作 | HTTP Method | URL 範例 |
|------|-------------|---------|
| 取得列表（分頁） | GET | `/api/v1/items?page=1&per_page=20&q=ABC` |
| 取得單筆 | GET | `/api/v1/items/123` |
| 建立 | POST | `/api/v1/items` |
| 完整更新 | PUT | `/api/v1/items/123` |
| 部分更新 | PATCH | `/api/v1/items/123` |
| 刪除 | DELETE | `/api/v1/items/123` |
| 自訂動作 | POST | `/api/v1/purchase-orders/123/approve` |

### 5.2 通用查詢參數

| 參數 | 說明 | 範例 |
|------|------|------|
| `page` | 頁碼（從 1 開始） | `?page=2` |
| `per_page` | 每頁筆數（最大 100） | `?per_page=50` |
| `q` | 關鍵字全文搜尋 | `?q=蘋果` |
| `sort` | 排序欄位 | `?sort=created_at` |
| `order` | 排序方向 | `?order=desc` |
| `filter[status]` | 欄位篩選 | `?filter[status]=active` |

### 5.3 標準回應結構

```json
// 成功（單筆）
{
  "success": true,
  "message": "OK",
  "data": { "id": 1, "name": "iPhone 15" }
}

// 成功（分頁列表）
{
  "success": true,
  "message": "OK",
  "data": [...],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 157,
    "total_pages": 8
  }
}

// 驗證錯誤
{
  "success": false,
  "message": "輸入資料有誤",
  "errors": {
    "name": ["商品名稱為必填"],
    "category_id": ["分類不存在"]
  }
}

// 伺服器錯誤
{
  "success": false,
  "message": "伺服器內部錯誤",
  "errors": null
}
```

### 5.4 HTTP 狀態碼使用規範

| 狀態碼 | 使用情境 |
|--------|---------|
| 200 | 查詢成功、更新成功 |
| 201 | 建立成功 |
| 204 | 刪除成功（無回應內容） |
| 400 | 請求格式錯誤、業務邏輯錯誤（如庫存不足） |
| 401 | 未認證（Token 無效/過期） |
| 403 | 已認證但無權限 |
| 404 | 資源不存在 |
| 422 | 表單驗證失敗 |
| 429 | 請求過於頻繁（Rate Limit） |
| 500 | 伺服器內部錯誤 |

---

## 6. 開發環境與工具

### 6.1 Docker Compose 開發環境

```yaml
# docker/docker-compose.yml（development — 僅後端）
services:
  nginx:
    image: nginx:1.25-alpine
    ports:
      - "${APP_PORT:-80}:80"
      - "${PMA_PORT:-8080}:8080"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d:ro
      - ../backend:/var/www/backend:ro
    depends_on:
      - php-fpm
      - phpmyadmin

  php-fpm:
    build:
      context: ..
      dockerfile: docker/php/Dockerfile
    volumes:
      - ../backend:/var/www/backend
    env_file:
      - .env
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_started

  mysql:
    image: mysql:8.0
    volumes:
      - mysql_data:/var/lib/mysql
      - ./mysql/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: ${MYSQL_DATABASE}
      MYSQL_USER: ${MYSQL_USER}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD}
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data

  minio:
    image: minio/minio:latest
    volumes:
      - minio_data:/data
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD}
    command: server /data --console-address ":9001"

volumes:
  mysql_data:
  redis_data:
  minio_data:
```

```yaml
# docker/docker-compose.prod.yml（production 疊加 — 含 nuxt）
services:
  nuxt:
    build:
      context: ..
      dockerfile: docker/frontend/Dockerfile.dev
    volumes:
      - ../frontend:/app
      - /app/node_modules
    environment:
      - NUXT_PUBLIC_API_BASE=${NUXT_PUBLIC_API_BASE}

  nginx:
    depends_on:
      - nuxt
```

**啟動方式（透過 deploy.sh）：**

```bash
./scripts/deploy.sh development   # 僅啟動後端（nginx, php-fpm, mysql, redis, minio）
./scripts/deploy.sh production    # 後端 + nuxt
```

### 6.2 開發工具建議

| 工具 | 用途 |
|------|------|
| VS Code | 主要 IDE |
| Vue - Official (Volar) | Vue 3 / Nuxt 語法支援 |
| PHP Intelephense | PHP 語言支援 |
| ESLint + Prettier | 前端程式碼規範 |
| PHP CS Fixer | PHP 程式碼規範（PSR-12） |
| TablePlus / DBeaver | 資料庫 GUI |
| Postman / Bruno | API 測試 |
| Redis Insight | Redis GUI |

---

## 7. 專案目錄結構

```
09_inventory-management/
├── backend/                    # CodeIgniter 4 後端
│   └── app/
│       ├── Config/             # 設定（Routes, Events, Services, ...）
│       ├── Controllers/        # API 控制器
│       ├── Entities/           # CI4 Entity（強型別資料列物件）
│       ├── Events/             # 業務領域事件
│       ├── Filters/            # HTTP Filter（Auth, CORS, RateLimit）
│       ├── Helpers/            # 全域 Helper 函式
│       ├── Libraries/          # 第三方包裝（JWT, Upload）
│       ├── Listeners/          # 事件監聽器
│       ├── Models/             # CI4 Model（Query Builder）
│       ├── Repositories/       # Repository 介面與實作
│       │   └── Contracts/      # Interface 定義
│       ├── Services/           # 業務邏輯服務層
│       └── Validation/         # 自訂驗證規則
├── frontend/                   # Nuxt 3 前端
├── docker/
│   ├── docker-compose.yml      # 共用後端服務（development 預設）
│   ├── docker-compose.prod.yml # 疊加 nuxt（production 專用）
│   ├── envs/
│   │   ├── .env.example
│   │   ├── .env.development
│   │   └── .env.production
│   ├── php/
│   │   ├── Dockerfile
│   │   └── php.ini
│   ├── frontend/
│   │   └── Dockerfile.dev
│   ├── mysql/
│   │   └── init.sql
│   └── nginx/
│       └── conf.d/
│           └── default.conf
├── scripts/
│   └── deploy.sh               # 部署腳本（./scripts/deploy.sh production）
├── docs/
│   ├── Phase1執行計畫書.md
│   └── 進銷存系統需求規格書.md
└── .gitignore
```

---

## 8. Sprint 開發計畫

### 總覽（預計 16 週 / 4 個月）

```
Sprint 1–2   基礎建設 (4週)
Sprint 3–4   商品管理 (4週)
Sprint 5–7   採購管理 (6週)
Sprint 8–10  銷售管理 (6週)
Sprint 11–13 庫存管理 (6週)
Sprint 14–15 報表與儀表板 (4週)
Sprint 16    整合測試 / UAT / 上線準備 (2週)
```

---

### Sprint 1（Week 1–2）：基礎建設 — 後端

**目標**：後端可運行的 CI4 骨架，通過認證可取得 JWT Token

| Task | 負責 | 說明 |
|------|------|------|
| T1-1 | 後端 | Docker 環境建立（Nginx + PHP-FPM + MySQL + Redis） |
| T1-2 | 後端 | CI4 專案初始化，設定 CORS、錯誤處理、Log |
| T1-3 | 後端 | `users`、`roles`、`role_permissions` Migration + Seed |
| T1-4 | 後端 | JWT 認證實作（登入、Refresh Token、登出） |
| T1-5 | 後端 | AuthFilter、CorsFilter 實作與測試 |
| T1-6 | 後端 | 統一 Response Helper、BaseApiController |
| T1-7 | 後端 | 撰寫 AuthController 單元測試 |

**完成標準**：`POST /api/v1/auth/login` 可回傳 JWT Token；所有受保護路由無 Token 回傳 401

---

### Sprint 2（Week 3–4）：基礎建設 — 前端

**目標**：前端骨架可登入，側邊欄導覽可用

| Task | 負責 | 說明 |
|------|------|------|
| T2-1 | 前端 | Nuxt 3 專案初始化，安裝所有套件 |
| T2-2 | 前端 | Tailwind CSS + shadcn-vue 設定、主題色設定 |
| T2-3 | 前端 | Default Layout（側邊欄、頂部列、麵包屑） |
| T2-4 | 前端 | Auth Layout（登入頁） |
| T2-5 | 前端 | Pinia auth store + useApi composable |
| T2-6 | 前端 | auth route middleware（未登入導向登入頁） |
| T2-7 | 前端 | Dashboard 空頁面 + 側邊欄選單結構 |
| T2-8 | 前端 | AppDataTable 通用元件（含搜尋、排序、分頁） |

**完成標準**：可登入並看到後台首頁側邊欄，未登入強制導向登入頁

---

### Sprint 3（Week 5–6）：商品管理 — 後端

**目標**：商品主檔 CRUD API 完整可用

| Task | 負責 | 說明 |
|------|------|------|
| T3-1 | 後端 | `categories`、`units`、`unit_conversions` Migration |
| T3-2 | 後端 | `items`、`item_skus`、`item_barcodes`、`item_images` Migration |
| T3-3 | 後端 | CategoryModel、UnitModel、ItemModel、ItemSkuModel |
| T3-4 | 後端 | ItemService（含 SKU 自動展開邏輯） |
| T3-5 | 後端 | ItemController（CRUD + 批次匯入） |
| T3-6 | 後端 | CategoryController、UnitController |
| T3-7 | 後端 | CSV/Excel 批次匯入商品（使用 PhpSpreadsheet） |
| T3-8 | 後端 | 商品圖片上傳 API（MinIO/S3 整合） |
| T3-9 | 後端 | ItemService 單元測試 |

**完成標準**：商品 CRUD API 全數通過 Postman 測試；批次匯入 1000 筆 < 30 秒

---

### Sprint 4（Week 7–8）：商品管理 — 前端

**目標**：商品管理 UI 完整可操作

| Task | 負責 | 說明 |
|------|------|------|
| T4-1 | 前端 | 商品分類管理頁（樹狀分類 CRUD） |
| T4-2 | 前端 | 商品列表頁（篩選、排序、分頁、停用） |
| T4-3 | 前端 | 商品建立/編輯頁（含 SKU 變體設定）|
| T4-4 | 前端 | SKU 列表與條碼管理 |
| T4-5 | 前端 | 商品圖片上傳（拖曳上傳、預覽） |
| T4-6 | 前端 | CSV 批次匯入 UI（含進度條、錯誤回報） |
| T4-7 | 前端 | 計量單位管理頁 |

**完成標準**：商品完整 CRUD 在前端可正常操作，表單驗證正確顯示

---

### Sprint 5（Week 9–10）：採購管理 — 後端

**目標**：採購單 + 進貨驗收 API 完整

| Task | 負責 | 說明 |
|------|------|------|
| T5-1 | 後端 | `suppliers`、`supplier_items` Migration + Model |
| T5-2 | 後端 | `purchase_orders`、`purchase_order_lines` Migration + Model |
| T5-3 | 後端 | `goods_receipts`、`goods_receipt_lines` Migration + Model |
| T5-4 | 後端 | `batch_serials` Migration + Model |
| T5-5 | 後端 | PurchaseOrderService（含採購單審核流程） |
| T5-6 | 後端 | GoodsReceiptService（含庫存入庫、批號記錄） |
| T5-7 | 後端 | SupplierController、PurchaseOrderController、GoodsReceiptController |
| T5-8 | 後端 | 採購單 PDF 產生（DomPDF） |
| T5-9 | 後端 | PurchaseOrderService 單元測試 |

**完成標準**：採購單建立 → 審核 → 進貨驗收 → 庫存自動入庫完整流程可跑通

---

### Sprint 6（Week 11–12）：採購管理 — 前端

| Task | 負責 | 說明 |
|------|------|------|
| T6-1 | 前端 | 供應商列表/建立/編輯頁 |
| T6-2 | 前端 | 採購單列表頁（依狀態篩選） |
| T6-3 | 前端 | 採購單建立頁（選品、數量、單價、預計交期） |
| T6-4 | 前端 | 採購單詳情頁（含審核/拒絕操作） |
| T6-5 | 前端 | 進貨驗收建立頁（對應採購單、掃描/手動輸入） |
| T6-6 | 前端 | 進貨驗收詳情頁（批號/序號輸入） |
| T6-7 | 前端 | 採購單 PDF 預覽與列印 |

---

### Sprint 7（Week 13）：採購管理優化

| Task | 說明 |
|------|------|
| T7-1 | 供應商付款狀態追蹤（後端 + 前端） |
| T7-2 | 採購退貨流程（後端 + 前端） |
| T7-3 | 採購相關 E2E 測試 |

---

### Sprint 8（Week 14–15）：銷售管理 — 後端

| Task | 負責 | 說明 |
|------|------|------|
| T8-1 | 後端 | `customers`、`customer_addresses` Migration + Model |
| T8-2 | 後端 | `sales_orders`、`sales_order_lines` Migration + Model |
| T8-3 | 後端 | `shipments`、`shipment_lines` Migration + Model |
| T8-4 | 後端 | SalesOrderService（含庫存預留邏輯） |
| T8-5 | 後端 | ShipmentService（含庫存出庫邏輯） |
| T8-6 | 後端 | CustomerController、SalesOrderController、ShipmentController |
| T8-7 | 後端 | 發票 PDF 產生 |
| T8-8 | 後端 | SalesOrderService + ShipmentService 單元測試 |

**完成標準**：SO 建立 → 庫存預留 → 出貨 → 庫存扣減 流程可跑通；防止超賣驗證通過

---

### Sprint 9（Week 16–17）：銷售管理 — 前端

| Task | 負責 | 說明 |
|------|------|------|
| T9-1 | 前端 | 客戶列表/建立/編輯頁（含多收貨地址） |
| T9-2 | 前端 | 銷售訂單列表頁 |
| T9-3 | 前端 | 銷售訂單建立頁（商品搜尋、即時庫存顯示） |
| T9-4 | 前端 | 銷售訂單詳情頁（確認、取消、出貨觸發） |
| T9-5 | 前端 | 出貨單列表/詳情頁 |
| T9-6 | 前端 | 包裝單（Packing Slip）列印頁 |
| T9-7 | 前端 | 發票 PDF 預覽與列印 |

---

### Sprint 10（Week 18）：銷售管理優化

| Task | 說明 |
|------|------|
| T10-1 | 收款紀錄（後端 + 前端） |
| T10-2 | 銷售退貨基礎流程（後端 + 前端） |
| T10-3 | 銷售相關 E2E 測試 |

---

### Sprint 11（Week 19–20）：庫存管理 — 後端

| Task | 負責 | 說明 |
|------|------|------|
| T11-1 | 後端 | `warehouses` Migration + Model |
| T11-2 | 後端 | `stock_transfers`、`stock_transfer_lines` Migration + Model |
| T11-3 | 後端 | `stocktakes`、`stocktake_lines` Migration + Model |
| T11-4 | 後端 | InventoryService（即時庫存查詢、庫存調撥） |
| T11-5 | 後端 | StocktakeService（盤點凍結快照、盤點確認） |
| T11-6 | 後端 | InventoryController、StocktakeController |
| T11-7 | 後端 | InventoryService + StocktakeService 單元測試 |

---

### Sprint 12（Week 21–22）：庫存管理 — 前端

| Task | 負責 | 說明 |
|------|------|------|
| T12-1 | 前端 | 倉庫管理頁（CRUD） |
| T12-2 | 前端 | 即時庫存查詢頁（多欄位篩選、庫存詳情） |
| T12-3 | 前端 | 庫存異動日誌頁（時間軸顯示） |
| T12-4 | 前端 | 庫存調撥建立頁 |
| T12-5 | 前端 | 盤點任務建立頁 |
| T12-6 | 前端 | 盤點作業頁（輸入實盤量、差異高亮顯示） |
| T12-7 | 前端 | 盤點確認頁（盈虧明細、審核） |

---

### Sprint 13（Week 23）：庫存優化

| Task | 說明 |
|------|------|
| T13-1 | 安全庫存 / 再訂購點設定（前端） |
| T13-2 | 低庫存警示顯示（Dashboard 徽章） |
| T13-3 | 批號/序號追蹤查詢頁 |

---

### Sprint 14–15（Week 24–27）：報表與儀表板

**後端 Task：**

| Task | 說明 |
|------|------|
| T14-1 | ReportService：進銷存彙總表（含期初/期末庫存計算） |
| T14-2 | ReportService：銷售業績報表 |
| T14-3 | ReportService：採購報表 |
| T14-4 | ReportService：毛利分析報表 |
| T14-5 | ReportService：庫存週轉率報表 |
| T14-6 | 報表 Excel 匯出（PhpSpreadsheet） |

**前端 Task：**

| Task | 說明 |
|------|------|
| T15-1 | Dashboard 儀表板（KPI 卡片：待出貨、低庫存數、本月銷售額） |
| T15-2 | 折線圖：近 30 天銷售趨勢（recharts） |
| T15-3 | 進銷存彙總報表頁（日期範圍篩選、Excel 匯出） |
| T15-4 | 銷售業績報表頁（圖表 + 表格） |
| T15-5 | 毛利分析報表頁 |
| T15-6 | 採購報表頁 |

---

### Sprint 16（Week 28）：整合測試 / UAT / 上線準備

| Task | 說明 |
|------|------|
| T16-1 | 完整 E2E 測試（採購到出貨完整流程） |
| T16-2 | 效能測試（庫存查詢 P95 < 1 秒） |
| T16-3 | 安全性檢查（OWASP Top 10 掃描） |
| T16-4 | 使用者驗收測試（UAT） |
| T16-5 | 生產環境 Docker 建置 |
| T16-6 | 系統備份機制確認（每日自動備份驗證） |
| T16-7 | 上線 Runbook 文件撰寫 |

---

## 9. 測試策略

### 9.1 後端測試（CodeIgniter 4 + PHPUnit）

```
tests/
├── unit/
│   ├── Services/
│   │   ├── InventoryServiceTest.php   # 庫存扣減、預留邏輯測試
│   │   ├── PurchaseOrderServiceTest.php
│   │   └── SalesOrderServiceTest.php
│   └── Models/
│       └── ItemModelTest.php
└── feature/
    ├── Api/
    │   ├── AuthTest.php               # 登入/Token 測試
    │   ├── ItemApiTest.php            # CRUD + 驗證測試
    │   ├── PurchaseOrderApiTest.php
    │   └── SalesOrderApiTest.php
    └── Workflow/
        └── PurchaseToShipmentTest.php # 完整流程整合測試
```

**測試覆蓋率目標**：Service 層 > 80%

**關鍵測試案例（InventoryService）：**

```php
// tests/unit/Services/InventoryServiceTest.php

public function testDeductStockThrowsWhenInsufficientStock(): void
{
    // 準備：在庫 5 個
    $this->seedInventory(skuId: 1, warehouseId: 1, qty: 5);

    // 執行 + 驗證：扣 10 個應拋出例外
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('庫存不足，無法出庫');
    $this->inventoryService->deductStock(1, 1, 10, 'sales_order', 1, 1);
}

public function testDeductStockSuccessAndWritesTransaction(): void
{
    $this->seedInventory(skuId: 1, warehouseId: 1, qty: 10);
    $this->inventoryService->deductStock(1, 1, 3, 'sales_order', 1, 1);

    // 驗證庫存數量正確
    $inv = $this->inventoryModel->where('sku_id', 1)->first();
    $this->assertEquals(7, $inv->on_hand_qty);

    // 驗證日誌已寫入
    $txCount = $this->txModel->where('sku_id', 1)->countAllResults();
    $this->assertEquals(1, $txCount);
}
```

### 9.2 前端測試（Vitest + @vue/test-utils）

```
tests/
├── unit/
│   ├── composables/
│   │   ├── useApi.test.ts        # API 錯誤處理、Token 刷新
│   │   └── useAuth.test.ts
│   └── components/
│       └── AppDataTable.test.ts  # 排序、分頁、搜尋行為
└── e2e/                          # (Phase 2 加入 Playwright)
```

### 9.3 API 測試

使用 **Bruno** 版控 API 測試集：

```
backend/tests/bruno/
├── auth/
│   ├── login.bru
│   └── refresh.bru
├── items/
│   ├── list-items.bru
│   ├── create-item.bru
│   └── update-item.bru
└── ...
```

---

## 10. 部署策略

### 10.1 環境規劃

| 環境 | 說明 | 觸發方式 |
|------|------|---------|
| development | Docker Compose 本地開發 | 手動 |
| staging | 仿正式環境，用於 UAT | PR 合併至 `develop` 自動部署 |
| production | 正式上線環境 | `main` 分支 Tag 手動觸發 |

### 10.2 生產環境架構（最小化）

```
Nginx (Reverse Proxy + SSL)
    ├── / → Nuxt 3 靜態檔案 或 Node.js 伺服器
    └── /api → PHP-FPM (CI4)

MySQL 8.0（主資料庫，建議另外備份至 S3）
Redis 7（Session + Cache）
MinIO（檔案儲存）
```

### 10.3 後端設定（.env.example）

```ini
# CodeIgniter 4 環境設定
CI_ENVIRONMENT=production
app.baseURL=https://api.your-domain.com/

# 資料庫
database.default.hostname=mysql
database.default.database=inventory_db
database.default.username=app
database.default.password=CHANGE_ME
database.default.DBDriver=MySQLi

# Redis
cache.handler=redis
cache.redis.host=redis
cache.redis.port=6379

# JWT
jwt.secret=CHANGE_ME_TO_RANDOM_256BIT_SECRET
jwt.ttl=3600
jwt.refresh_ttl=604800

# 檔案儲存
storage.driver=s3
storage.s3.endpoint=http://minio:9000
storage.s3.bucket=inventory-files
storage.s3.key=minioadmin
storage.s3.secret=CHANGE_ME
```

### 10.4 前端設定（.env.example）

```ini
NUXT_PUBLIC_API_BASE=https://api.your-domain.com/api/v1
```

---

## 11. 開發規範與 Git 流程

### 11.1 分支策略（Git Flow 簡化版）

```
main          ← 正式上線版本（只接受來自 develop 的 PR）
develop       ← 整合測試分支
feature/xxx   ← 功能開發分支（從 develop 開）
fix/xxx       ← 錯誤修復分支
```

**分支命名規則：**

```
feature/sprint1-auth-backend
feature/sprint3-item-crud-api
feature/sprint4-item-frontend
fix/inventory-deduct-race-condition
```

### 11.2 Commit Message 規範（Conventional Commits）

```
<type>(<scope>): <subject>

type:
  feat     新增功能
  fix      修復錯誤
  refactor 重構（不影響功能）
  test     測試相關
  docs     文件更新
  chore    建置/工具更新

範例：
feat(inventory): add stock deduction with race condition protection
fix(auth): token refresh loop on 401 response
test(purchase): add goods receipt service unit tests
```

### 11.3 PR 規範

- PR 必須通過 CI 自動測試才可合併
- PR 描述需清楚說明：改了什麼、為什麼、如何測試
- 至少 1 位 Reviewer 核准後方可合併
- 合併方式使用 **Squash and Merge**（保持 commit log 乾淨）

### 11.4 程式碼規範

**後端（PHP）：**
- 遵循 PSR-12 程式碼風格
- 使用 PHP CS Fixer 自動格式化：`composer cs-fix`
- 命名：類別 PascalCase、方法/變數 camelCase、常數 UPPER_SNAKE_CASE
- 所有 Service 方法的 public 方式必須有型別宣告（PHP 8.2 強型別）

**前端（TypeScript）：**
- 遵循 ESLint + Prettier 設定
- 使用組合式 API（Composition API）+ `<script setup>` 語法
- 所有 props 必須有型別定義；避免使用 `any`
- 元件命名：PascalCase；composable 命名：`useXxx`；store 命名：`useXxxStore`

### 11.5 安全性開發要求（每位開發者必遵守）

| 規則 | 說明 |
|------|------|
| 禁止硬編碼密碼/金鑰 | 所有機密資料存 `.env`，不可出現在程式碼 |
| 禁止 `$_GET`/`$_POST` 直接使用 | 一律透過 CI4 Request 物件存取 |
| 所有 DB 查詢使用 Query Builder | 禁止字串拼接 SQL，防 SQL Injection |
| 輸出到前端的資料必須 Escape | Vue 3 預設 XSS 保護，避免 `v-html` 使用原始 API 資料 |
| 敏感 API 加入 Rate Limit | 登入 API 最多每分鐘 10 次；一般 API 每分鐘 300 次 |
| JWT 不儲存敏感資料 | Payload 只放 `sub`（userId）和 `role`，不放密碼或個資 |

---

## 附錄：開發啟動快速指南

### 後端初始化

```bash
cd backend
cp .env.example .env
composer install
php spark migrate
php spark db:seed DatabaseSeeder
php spark serve
```

### 前端初始化

```bash
cd frontend
cp ../docker/envs/.env.example .env
bun install
bun run dev
```

### Docker 一鍵啟動

```bash
# 啟動開發環境（後端服務）
./scripts/deploy.sh development

# 執行 Migration
docker compose --project-directory docker exec php-fpm php spark migrate --all
docker compose --project-directory docker exec php-fpm php spark db:seed DatabaseSeeder

# 查看 Log
docker compose --project-directory docker logs -f php-fpm
```

### 後端測試

```bash
cd backend
composer test          # 執行全部測試
composer test:unit     # 只跑 unit tests
composer cs-check      # 檢查程式碼風格
```

### 前端測試

```bash
cd frontend
bun run test           # Vitest
bun run type-check     # vue-tsc 型別檢查
bun run lint           # ESLint
```

---

*本計畫書依據進銷存系統需求規格書 v1.0 Phase 1 範疇撰寫。實際 Sprint 進度可依團隊人數調整。*
