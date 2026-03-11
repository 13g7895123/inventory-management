<?php

declare(strict_types=1);

namespace Config;

use App\Listeners\LogInventoryTransaction;
use App\Listeners\SendLowStockAlert;
use App\Libraries\JWT\JWTService;
use App\Libraries\ImageUploadService;
use App\Models\BatchSerialModel;
use App\Models\CategoryModel;
use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Models\GoodsReceiptLineModel;
use App\Models\GoodsReceiptModel;
use App\Models\InventoryModel;
use App\Models\InventoryTransactionModel;
use App\Models\ItemModel;
use App\Models\ItemSkuModel;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\RefreshTokenModel;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Models\ShipmentLineModel;
use App\Models\ShipmentModel;
use App\Models\PurchasePaymentModel;
use App\Models\PurchaseReturnLineModel;
use App\Models\PurchaseReturnModel;
use App\Models\SalesPaymentModel;
use App\Models\SalesReturnLineModel;
use App\Models\SalesReturnModel;
use App\Models\StocktakeLineModel;
use App\Models\StocktakeModel;
use App\Models\StockTransferLineModel;
use App\Models\StockTransferModel;
use App\Models\SupplierModel;
use App\Models\UnitModel;
use App\Models\UserModel;
use App\Models\WarehouseModel;
use App\Repositories\CategoryRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\ItemRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Repositories\SalesOrderRepository;
use App\Repositories\ShipmentRepository;
use App\Repositories\SkuRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\UnitRepository;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\CustomerService;
use App\Services\GoodsReceiptService;
use App\Services\ImportService;
use App\Services\InventoryService;
use App\Services\ItemService;
use App\Services\PurchaseOrderPdfService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReturnService;
use App\Services\ReportService;
use App\Services\SalesOrderPdfService;
use App\Services\SalesOrderService;
use App\Services\SalesPaymentService;
use App\Services\SalesReturnService;
use App\Services\ShipmentService;
use App\Services\SkuService;
use App\Services\StocktakeService;
use App\Services\StockTransferService;
use App\Services\SupplierPaymentService;
use App\Services\SupplierService;
use App\Services\UnitService;
use App\Services\WarehouseService;
use CodeIgniter\Config\BaseService;

/**
 * CI4 Services 容器
 *
 * 所有自訂 Service / Repository / Listener 在此集中管理。
 * 使用 service('xxx') 或 \Config\Services::xxx() 取得實例。
 */
class Services extends BaseService
{
    // ── Auth ─────────────────────────────────────────────────────────

    public static function jwtService(bool $getShared = true): JWTService
    {
        if ($getShared) {
            return static::getSharedInstance('jwtService');
        }

        return new JWTService();
    }

    public static function authService(bool $getShared = true): AuthService
    {
        if ($getShared) {
            return static::getSharedInstance('authService');
        }

        return new AuthService(
            new UserModel(),
            new RefreshTokenModel(),
            static::jwtService(),
        );
    }

    // ── Repositories ─────────────────────────────────────────────────

    public static function categoryRepository(bool $getShared = true): CategoryRepository
    {
        if ($getShared) {
            return static::getSharedInstance('categoryRepository');
        }

        return new CategoryRepository(new CategoryModel());
    }

    public static function unitRepository(bool $getShared = true): UnitRepository
    {
        if ($getShared) {
            return static::getSharedInstance('unitRepository');
        }

        return new UnitRepository(new UnitModel());
    }

    public static function skuRepository(bool $getShared = true): SkuRepository
    {
        if ($getShared) {
            return static::getSharedInstance('skuRepository');
        }

        return new SkuRepository(new ItemSkuModel());
    }

    public static function itemRepository(bool $getShared = true): ItemRepository
    {
        if ($getShared) {
            return static::getSharedInstance('itemRepository');
        }

        return new ItemRepository(new ItemModel());
    }

    public static function inventoryRepository(bool $getShared = true): InventoryRepository
    {
        if ($getShared) {
            return static::getSharedInstance('inventoryRepository');
        }

        return new InventoryRepository(new InventoryModel());
    }

    // ── Services ─────────────────────────────────────────────────────

    public static function categoryService(bool $getShared = true): CategoryService
    {
        if ($getShared) {
            return static::getSharedInstance('categoryService');
        }

        return new CategoryService(static::categoryRepository());
    }

    public static function unitService(bool $getShared = true): UnitService
    {
        if ($getShared) {
            return static::getSharedInstance('unitService');
        }

        return new UnitService(static::unitRepository());
    }

    public static function inventoryService(bool $getShared = true): InventoryService
    {
        if ($getShared) {
            return static::getSharedInstance('inventoryService');
        }

        return new InventoryService(
            static::inventoryRepository(),
            static::itemRepository(),
            new InventoryTransactionModel(),
        );
    }

    public static function itemService(bool $getShared = true): ItemService
    {
        if ($getShared) {
            return static::getSharedInstance('itemService');
        }

        return new ItemService(
            static::itemRepository(),
            static::skuRepository(),
        );
    }

    public static function skuService(bool $getShared = true): SkuService
    {
        if ($getShared) {
            return static::getSharedInstance('skuService');
        }

        return new SkuService(
            static::skuRepository(),
            static::itemRepository(),
        );
    }

    public static function importService(bool $getShared = true): ImportService
    {
        if ($getShared) {
            return static::getSharedInstance('importService');
        }

        return new ImportService();
    }

    public static function imageUploadService(bool $getShared = true): ImageUploadService
    {
        if ($getShared) {
            return static::getSharedInstance('imageUploadService');
        }

        return new ImageUploadService();
    }

    // ── Sprint 5: 採購管理 ──────────────────────────────────────────

    public static function supplierRepository(bool $getShared = true): SupplierRepository
    {
        if ($getShared) {
            return static::getSharedInstance('supplierRepository');
        }

        return new SupplierRepository(new SupplierModel());
    }

    public static function purchaseOrderRepository(bool $getShared = true): PurchaseOrderRepository
    {
        if ($getShared) {
            return static::getSharedInstance('purchaseOrderRepository');
        }

        return new PurchaseOrderRepository(
            new PurchaseOrderModel(),
            new PurchaseOrderLineModel(),
        );
    }

    public static function supplierService(bool $getShared = true): SupplierService
    {
        if ($getShared) {
            return static::getSharedInstance('supplierService');
        }

        return new SupplierService(static::supplierRepository());
    }

    public static function purchaseOrderService(bool $getShared = true): PurchaseOrderService
    {
        if ($getShared) {
            return static::getSharedInstance('purchaseOrderService');
        }

        return new PurchaseOrderService(
            static::purchaseOrderRepository(),
            static::supplierRepository(),
        );
    }

    public static function goodsReceiptService(bool $getShared = true): GoodsReceiptService
    {
        if ($getShared) {
            return static::getSharedInstance('goodsReceiptService');
        }

        return new GoodsReceiptService(
            static::purchaseOrderRepository(),
            static::inventoryService(),
            new GoodsReceiptModel(),
            new GoodsReceiptLineModel(),
            new BatchSerialModel(),
        );
    }

    public static function purchaseOrderPdfService(bool $getShared = true): PurchaseOrderPdfService
    {
        if ($getShared) {
            return static::getSharedInstance('purchaseOrderPdfService');
        }

        return new PurchaseOrderPdfService(
            static::purchaseOrderRepository(),
            static::supplierRepository(),
        );
    }

    // ── Sprint 8: 銷售管理 ──────────────────────────────────────────

    public static function customerRepository(bool $getShared = true): CustomerRepository
    {
        if ($getShared) {
            return static::getSharedInstance('customerRepository');
        }

        return new CustomerRepository(
            new CustomerModel(),
            new CustomerAddressModel(),
        );
    }

    public static function salesOrderRepository(bool $getShared = true): SalesOrderRepository
    {
        if ($getShared) {
            return static::getSharedInstance('salesOrderRepository');
        }

        return new SalesOrderRepository(
            new SalesOrderModel(),
            new SalesOrderLineModel(),
        );
    }

    public static function shipmentRepository(bool $getShared = true): ShipmentRepository
    {
        if ($getShared) {
            return static::getSharedInstance('shipmentRepository');
        }

        return new ShipmentRepository(
            new ShipmentModel(),
            new ShipmentLineModel(),
        );
    }

    public static function customerService(bool $getShared = true): CustomerService
    {
        if ($getShared) {
            return static::getSharedInstance('customerService');
        }

        return new CustomerService(static::customerRepository());
    }

    public static function salesOrderService(bool $getShared = true): SalesOrderService
    {
        if ($getShared) {
            return static::getSharedInstance('salesOrderService');
        }

        return new SalesOrderService(
            static::salesOrderRepository(),
            static::customerRepository(),
            static::inventoryService(),
        );
    }

    public static function shipmentService(bool $getShared = true): ShipmentService
    {
        if ($getShared) {
            return static::getSharedInstance('shipmentService');
        }

        return new ShipmentService(
            static::shipmentRepository(),
            static::salesOrderRepository(),
            static::inventoryService(),
        );
    }

    public static function salesOrderPdfService(bool $getShared = true): SalesOrderPdfService
    {
        if ($getShared) {
            return static::getSharedInstance('salesOrderPdfService');
        }

        return new SalesOrderPdfService(
            static::salesOrderRepository(),
            static::customerRepository(),
        );
    }

    // ── Sprint 11: 庫存管理 ─────────────────────────────────────────

    public static function warehouseService(bool $getShared = true): WarehouseService
    {
        if ($getShared) {
            return static::getSharedInstance('warehouseService');
        }

        return new WarehouseService(new WarehouseModel());
    }

    public static function stockTransferService(bool $getShared = true): StockTransferService
    {
        if ($getShared) {
            return static::getSharedInstance('stockTransferService');
        }

        return new StockTransferService(
            new StockTransferModel(),
            new StockTransferLineModel(),
            new WarehouseModel(),
            static::inventoryService(),
        );
    }

    public static function stocktakeService(bool $getShared = true): StocktakeService
    {
        if ($getShared) {
            return static::getSharedInstance('stocktakeService');
        }

        return new StocktakeService(
            new StocktakeModel(),
            new StocktakeLineModel(),
            static::inventoryService(),
        );
    }

    // ── Sprint 12/13: 退貨 & 付款 & 報表 ────────────────────────────

    public static function purchaseReturnService(bool $getShared = true): PurchaseReturnService
    {
        if ($getShared) {
            return static::getSharedInstance('purchaseReturnService');
        }

        return new PurchaseReturnService(
            static::purchaseOrderRepository(),
            static::inventoryService(),
            new PurchaseReturnModel(),
            new PurchaseReturnLineModel(),
        );
    }

    public static function supplierPaymentService(bool $getShared = true): SupplierPaymentService
    {
        if ($getShared) {
            return static::getSharedInstance('supplierPaymentService');
        }

        return new SupplierPaymentService(
            static::purchaseOrderRepository(),
            new PurchasePaymentModel(),
            new PurchaseOrderModel(),
        );
    }

    public static function salesReturnService(bool $getShared = true): SalesReturnService
    {
        if ($getShared) {
            return static::getSharedInstance('salesReturnService');
        }

        return new SalesReturnService(
            static::salesOrderRepository(),
            static::inventoryService(),
            new SalesReturnModel(),
            new SalesReturnLineModel(),
        );
    }

    public static function salesPaymentService(bool $getShared = true): SalesPaymentService
    {
        if ($getShared) {
            return static::getSharedInstance('salesPaymentService');
        }

        return new SalesPaymentService(
            static::salesOrderRepository(),
            new SalesPaymentModel(),
            new SalesOrderModel(),
        );
    }

    public static function reportService(bool $getShared = true): ReportService
    {
        if ($getShared) {
            return static::getSharedInstance('reportService');
        }

        return new ReportService();
    }

    // ── Listeners ────────────────────────────────────────────────────

    public static function sendLowStockAlert(bool $getShared = true): SendLowStockAlert
    {
        if ($getShared) {
            return static::getSharedInstance('sendLowStockAlert');
        }

        return new SendLowStockAlert(
            static::inventoryRepository(),
            static::itemRepository(),
        );
    }

    public static function logInventoryTransaction(bool $getShared = true): LogInventoryTransaction
    {
        if ($getShared) {
            return static::getSharedInstance('logInventoryTransaction');
        }

        return new LogInventoryTransaction(\Config\Database::connect());
    }
}
