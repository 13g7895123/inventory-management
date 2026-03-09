<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Item;
use App\Entities\ItemSku;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\SkuRepositoryInterface;

/**
 * ItemService — 商品主檔業務邏輯
 */
class ItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepo,
        private readonly SkuRepositoryInterface  $skuRepo,
    ) {}

    /**
     * 分頁列表（含搜尋）
     *
     * @return array{items: Item[], total: int}
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $criteria = [];

        if (! empty($filters['keyword'])) {
            $criteria['name'] = ['LIKE', "%{$filters['keyword']}%"];
        }
        if (! empty($filters['category_id'])) {
            $criteria['category_id'] = (int) $filters['category_id'];
        }
        if (isset($filters['is_active'])) {
            $criteria['is_active'] = (bool) $filters['is_active'];
        }

        $result = $this->itemRepo->findAll($criteria, [
            'page'     => $page,
            'per_page' => $perPage,
            'sort'     => 'name',
            'order'    => 'asc',
        ]);

        return [
            'items' => $result['data'],
            'total' => $result['total'],
        ];
    }

    /**
     * 取得單一商品（含 SKU 列表）
     */
    public function getById(int $id): ?Item
    {
        return $this->itemRepo->findById($id);
    }

    /**
     * 新增商品，並自動展開 SKU
     *
     * 若 $data['skus'] 有值，則建立對應 SKU。
     * 若無 skus，建立一個預設 SKU（sku_code = item.code）。
     */
    public function create(array $data): Item
    {
        $skusData = $data['skus'] ?? [];
        unset($data['skus']);

        $item = new Item($data);
        $this->itemRepo->save($item);

        if (empty($skusData)) {
            $this->createDefaultSku($item);
        } else {
            foreach ($skusData as $skuData) {
                $this->createSku($item->id, $skuData);
            }
        }

        return $this->itemRepo->findById($item->id);
    }

    /**
     * 更新商品基本資料（SKU 透過 SkuService 獨立管理）
     */
    public function update(int $id, array $data): Item
    {
        $item = $this->itemRepo->findById($id);

        if ($item === null) {
            throw new \RuntimeException("商品 #{$id} 不存在");
        }

        unset($data['skus']);
        $item->fill($data);
        $this->itemRepo->save($item);

        return $this->itemRepo->findById($id);
    }

    /**
     * 軟刪除商品
     */
    public function delete(int $id): void
    {
        $item = $this->itemRepo->findById($id);

        if ($item === null) {
            throw new \RuntimeException("商品 #{$id} 不存在");
        }

        $this->itemRepo->delete($id);
    }

    /**
     * 取得商品的 SKU 列表
     *
     * @return ItemSku[]
     */
    public function getSkus(int $itemId): array
    {
        return $this->skuRepo->findByItemId($itemId);
    }

    private function createDefaultSku(Item $item): void
    {
        $sku = new ItemSku([
            'item_id'   => $item->id,
            'sku_code'  => $item->code,
            'is_active' => true,
        ]);
        $this->skuRepo->save($sku);
    }

    private function createSku(int $itemId, array $data): void
    {
        $sku = new ItemSku(array_merge($data, ['item_id' => $itemId]));
        $this->skuRepo->save($sku);
    }
}
