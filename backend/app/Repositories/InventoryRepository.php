<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Inventory;
use App\Entities\BaseEntity;
use App\Models\InventoryModel;
use App\Repositories\Contracts\InventoryRepositoryInterface;

/**
 * InventoryRepository — 庫存 Repository 實作
 */
class InventoryRepository extends BaseRepository implements InventoryRepositoryInterface
{
    public function __construct(InventoryModel $model)
    {
        parent::__construct($model);
    }

    public function findById(int $id): ?Inventory
    {
        return $this->model->find($id);
    }

    /**
     * 依 SKU + 倉庫查詢庫存（最頻繁使用的查詢）
     */
    public function findBySkuAndWarehouse(int $skuId, int $warehouseId): ?Inventory
    {
        return $this->model
            ->where('sku_id', $skuId)
            ->where('warehouse_id', $warehouseId)
            ->first();
    }

    /**
     * 取得某 SKU 在所有倉庫的庫存
     */
    public function findAllBySkuId(int $skuId): array
    {
        return $this->model
            ->select('inventory.*, warehouses.name as warehouse_name')
            ->join('warehouses', 'warehouses.id = inventory.warehouse_id')
            ->where('sku_id', $skuId)
            ->findAll() ?: [];
    }

    /**
     * 取得某倉庫所有庫存（含商品資訊）
     */
    public function findAllByWarehouse(int $warehouseId, array $options = []): array
    {
        return $this->findAll(['inventory.warehouse_id' => $warehouseId], $options);
    }

    /**
     * 取得低於安全庫存的品項
     * 透過 Raw Query 比較計算欄位
     */
    public function findBelowSafetyStock(): array
    {
        return $this->model
            ->select('inventory.*, item_skus.sku_code, items.name as item_name')
            ->join('item_skus', 'item_skus.id = inventory.sku_id')
            ->join('items', 'items.id = item_skus.item_id')
            ->where('items.safety_stock >', 0)
            ->where('inventory.on_hand_qty <=', 'items.safety_stock', false)
            ->findAll() ?: [];
    }

    /**
     * SELECT FOR UPDATE — 鎖定庫存列（在 DB Transaction 內使用，防止競態條件）
     */
    public function findAndLock(int $skuId, int $warehouseId): ?Inventory
    {
        // CI4 Query Builder 使用 FOR UPDATE
        $result = $this->model->db
            ->query(
                "SELECT * FROM inventory WHERE sku_id = ? AND warehouse_id = ? LIMIT 1 FOR UPDATE",
                [$skuId, $warehouseId]
            )
            ->getRow();

        if ($result === null) {
            return null;
        }

        // 手動將結果水化為 Inventory Entity
        $entity = new Inventory();
        $entity->fill((array) $result);
        return $entity;
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
