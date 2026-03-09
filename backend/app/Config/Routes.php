<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ── API v1 ────────────────────────────────────────────────────────
$routes->group('api/v1', ['namespace' => 'App\Controllers\Api\V1'], static function ($routes) {

    // 認證（無需 JWT）
    $routes->post('auth/login',   'Auth\AuthController::login');
    $routes->post('auth/refresh', 'Auth\AuthController::refresh');

    // 需要 JWT 的路由群組
    $routes->group('', ['filter' => 'auth'], static function ($routes) {

        $routes->post('auth/logout', 'Auth\AuthController::logout');
        $routes->get('auth/me',      'Auth\AuthController::me');

        // ── 商品管理 ──────────────────────────────────────
        $routes->get('items',           'Items\ItemController::index');
        $routes->post('items',          'Items\ItemController::create');
        $routes->get('items/(:num)',     'Items\ItemController::show/$1');
        $routes->put('items/(:num)',     'Items\ItemController::update/$1');
        $routes->delete('items/(:num)', 'Items\ItemController::remove/$1');

        // 商品圖片上傳
        $routes->post('items/(:num)/images', 'Items\ItemController::uploadImage/$1');

        // 批次匯入
        $routes->post('items/import', 'Items\ItemController::import');

        // SKU
        $routes->get('items/(:num)/skus',    'Items\SkuController::index/$1');
        $routes->post('items/(:num)/skus',   'Items\SkuController::create/$1');
        $routes->put('skus/(:num)',          'Items\SkuController::update/$1');
        $routes->delete('skus/(:num)',       'Items\SkuController::remove/$1');

        // ── 庫存查詢 ──────────────────────────────────────
        $routes->get('inventories',                    'Inventory\InventoryController::index');
        $routes->get('inventories/low-stock',          'Inventory\InventoryController::lowStock');
        $routes->get('skus/(:num)/inventories',        'Inventory\InventoryController::bySku/$1');
        $routes->post('inventories/adjust',            'Inventory\InventoryController::adjust');

        // ── 採購管理 ──────────────────────────────────────
        $routes->get('purchase-orders',              'Purchase\PurchaseOrderController::index');
        $routes->post('purchase-orders',             'Purchase\PurchaseOrderController::create');
        $routes->get('purchase-orders/(:num)',       'Purchase\PurchaseOrderController::show/$1');
        $routes->post('purchase-orders/(:num)/submit',   'Purchase\PurchaseOrderController::submit/$1');
        $routes->post('purchase-orders/(:num)/approve',  'Purchase\PurchaseOrderController::approve/$1');
        $routes->post('purchase-orders/(:num)/cancel',   'Purchase\PurchaseOrderController::cancel/$1');
        $routes->post('purchase-orders/(:num)/receive',  'Purchase\PurchaseOrderController::receive/$1');
        $routes->get('purchase-orders/(:num)/pdf',       'Purchase\PurchaseOrderController::pdf/$1');
        // 付款記錄
        $routes->get('purchase-orders/(:num)/payments',  'Purchase\PurchaseOrderController::listPayments/$1');
        $routes->post('purchase-orders/(:num)/payments', 'Purchase\PurchaseOrderController::addPayment/$1');

        // 採購退貨
        $routes->get('purchase-orders/(:num)/returns',   'Purchase\PurchaseReturnController::listByOrder/$1');
        $routes->post('purchase-orders/(:num)/returns',  'Purchase\PurchaseReturnController::create/$1');
        $routes->get('purchase-returns/(:num)',          'Purchase\PurchaseReturnController::show/$1');
        $routes->post('purchase-returns/(:num)/confirm', 'Purchase\PurchaseReturnController::confirm/$1');
        $routes->post('purchase-returns/(:num)/cancel',  'Purchase\PurchaseReturnController::cancel/$1');
        // ── 銷售管理 ──────────────────────────────────────
        // 客戶
        $routes->get('customers',                      'Sales\CustomerController::index');
        $routes->post('customers',                     'Sales\CustomerController::create');
        $routes->get('customers/(:num)',               'Sales\CustomerController::show/$1');
        $routes->put('customers/(:num)',               'Sales\CustomerController::update/$1');
        $routes->get('customers/(:num)/addresses',     'Sales\CustomerController::listAddresses/$1');
        $routes->post('customers/(:num)/addresses',    'Sales\CustomerController::addAddress/$1');
        // 銷售訂單
        $routes->get('sales-orders',                   'Sales\SalesOrderController::index');
        $routes->post('sales-orders',                  'Sales\SalesOrderController::create');
        $routes->get('sales-orders/(:num)',            'Sales\SalesOrderController::show/$1');
        $routes->post('sales-orders/(:num)/confirm',   'Sales\SalesOrderController::confirm/$1');
        $routes->post('sales-orders/(:num)/cancel',    'Sales\SalesOrderController::cancel/$1');
        $routes->get('sales-orders/(:num)/pdf',        'Sales\SalesOrderController::pdf/$1');
        // 出貨單
        $routes->get('sales-orders/(:num)/shipments',  'Sales\ShipmentController::listBySalesOrder/$1');
        $routes->post('sales-orders/(:num)/shipments', 'Sales\ShipmentController::create/$1');
        $routes->get('shipments/(:num)',               'Sales\ShipmentController::show/$1');

        // ── 倉庫管理 ──────────────────────────────────────
        $routes->get('warehouses',           'Warehouse\WarehouseController::index');
        $routes->post('warehouses',          'Warehouse\WarehouseController::create');
        $routes->get('warehouses/(:num)',    'Warehouse\WarehouseController::show/$1');
        $routes->put('warehouses/(:num)',    'Warehouse\WarehouseController::update/$1');

        // ── 基礎資料 ──────────────────────────────────────
        $routes->get('categories',           'Master\CategoryController::index');
        $routes->post('categories',          'Master\CategoryController::create');
        $routes->get('categories/(:num)',    'Master\CategoryController::show/$1');
        $routes->put('categories/(:num)',    'Master\CategoryController::update/$1');
        $routes->delete('categories/(:num)', 'Master\CategoryController::remove/$1');

        $routes->get('units',           'Master\UnitController::index');
        $routes->post('units',          'Master\UnitController::create');
        $routes->get('units/(:num)',    'Master\UnitController::show/$1');
        $routes->put('units/(:num)',    'Master\UnitController::update/$1');
        $routes->delete('units/(:num)', 'Master\UnitController::remove/$1');

        $routes->get('suppliers',           'Master\SupplierController::index');
        $routes->post('suppliers',          'Master\SupplierController::create');
        $routes->get('suppliers/(:num)',    'Master\SupplierController::show/$1');
        $routes->put('suppliers/(:num)',    'Master\SupplierController::update/$1');

        // ── 報表 ──────────────────────────────────────────
        $routes->get('reports/inventory-valuation', 'Report\ReportController::inventoryValuation');
        $routes->get('reports/stock-movement',      'Report\ReportController::stockMovement');
        $routes->get('reports/turnover-rate',       'Report\ReportController::turnoverRate');
    });
});
