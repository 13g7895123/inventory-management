<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\Inventory;

/**
 * InventoryRepositoryInterface — 庫存 Repository 合約
 */
interface InventoryRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?Inventory;

    /**
     * 依 SKU + 倉庫取得庫存（最常用的查詢）
     */
    public function findBySkuAndWarehouse(int $skuId, int $warehouseId): ?Inventory;

    /**
     * 取得某 SKU 在所有倉庫的庫存
     *
     * @return Inventory[]
     */
    public function findAllBySkuId(int $skuId): array;

    /**
     * 取得某倉庫所有庫存
     *
     * @return array{data: Inventory[], total: int}
     */
    public function findAllByWarehouse(int $warehouseId, array $options = []): array;

    /**
     * 取得低於安全庫存的品項
     *
     * @return Inventory[]
     */
    public function findBelowSafetyStock(): array;

    /**
     * 使用 SELECT FOR UPDATE 鎖定並取得庫存（防 Race Condition）
     */
    public function findAndLock(int $skuId, int $warehouseId): ?Inventory;

    /**
     * 儲存庫存
     */
    public function save(\App\Entities\BaseEntity $entity): bool;
}
