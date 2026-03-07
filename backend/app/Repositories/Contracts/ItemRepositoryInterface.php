<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\Item;

/**
 * ItemRepositoryInterface — 商品 Repository 合約
 *
 * 繼承通用介面，加入商品特定的查詢方法。
 * 型別宣告使用具體的 Item Entity，提供更好的 IDE 支援。
 */
interface ItemRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?Item;

    /**
     * 依條碼取得 SKU（含商品資訊）
     */
    public function findByBarcode(string $barcode): ?Item;

    /**
     * 依 SKU 代碼取得商品
     */
    public function findBySku(string $skuCode): ?Item;

    /**
     * 取得所有啟用商品（含分頁）
     *
     * @return array{data: Item[], total: int}
     */
    public function findActive(array $options = []): array;

    /**
     * 依分類取得商品
     *
     * @return array{data: Item[], total: int}
     */
    public function findByCategory(int $categoryId, array $options = []): array;

    /**
     * 儲存商品
     */
    public function save(\App\Entities\BaseEntity $entity): bool;
}
