<?php

declare(strict_types=1);

namespace Config;

use App\Listeners\LogInventoryTransaction;
use App\Listeners\SendLowStockAlert;
use App\Libraries\JWT\JWTService;
use App\Models\InventoryModel;
use App\Models\InventoryTransactionModel;
use App\Models\ItemModel;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Repositories\InventoryRepository;
use App\Repositories\ItemRepository;
use App\Services\AuthService;
use App\Services\InventoryService;
use App\Services\ItemService;
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

        return new ItemService(static::itemRepository());
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
