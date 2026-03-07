<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Item;
use App\Entities\BaseEntity;
use App\Models\ItemModel;
use App\Repositories\Contracts\ItemRepositoryInterface;

/**
 * ItemRepository — 商品 Repository 實作
 *
 * 所有商品相關的資料庫操作集中於此。
 * Service 只與此 Repository 介面互動，不直接操作 Model。
 */
class ItemRepository extends BaseRepository implements ItemRepositoryInterface
{
    public function __construct(ItemModel $model)
    {
        parent::__construct($model);
    }

    /**
     * 依 ID 取得商品（含分類名稱、單位名稱）
     */
    public function findById(int $id): ?Item
    {
        return $this->model
            ->select('items.*, categories.name as category_name, units.name as unit_name')
            ->join('categories', 'categories.id = items.category_id', 'left')
            ->join('units', 'units.id = items.unit_id', 'left')
            ->find($id);
    }

    /**
     * 依條碼查找 SKU 對應的商品
     */
    public function findByBarcode(string $barcode): ?Item
    {
        return $this->model
            ->select('items.*')
            ->join('item_skus', 'item_skus.item_id = items.id')
            ->join('item_barcodes', 'item_barcodes.sku_id = item_skus.id')
            ->where('item_barcodes.barcode', $barcode)
            ->where('items.is_active', true)
            ->first();
    }

    /**
     * 依 SKU 代碼查找
     */
    public function findBySku(string $skuCode): ?Item
    {
        return $this->model
            ->select('items.*')
            ->join('item_skus', 'item_skus.item_id = items.id')
            ->where('item_skus.sku_code', $skuCode)
            ->where('items.is_active', true)
            ->first();
    }

    /**
     * 取得啟用中商品列表
     */
    public function findActive(array $options = []): array
    {
        return $this->findAll(['items.is_active' => true], $options);
    }

    /**
     * 依分類查找商品
     */
    public function findByCategory(int $categoryId, array $options = []): array
    {
        return $this->findAll(['items.category_id' => $categoryId], $options);
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
