<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Item;
use App\Repositories\Contracts\ItemRepositoryInterface;

/**
 * ItemService — 商品主檔業務邏輯
 */
class ItemService
{
    public function __construct(
        private readonly ItemRepositoryInterface $itemRepo,
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

        $items = $this->itemRepo->findAll($criteria, [
            'page'     => $page,
            'per_page' => $perPage,
            'sort_by'  => 'name',
            'sort_dir' => 'asc',
        ]);

        $total = $this->itemRepo->count($criteria);

        return compact('items', 'total');
    }

    /**
     * 取得單一商品（含 SKU）
     */
    public function getById(int $id): ?Item
    {
        return $this->itemRepo->findById($id);
    }

    /**
     * 新增商品
     */
    public function create(array $data): Item
    {
        $item = new Item($data);
        $this->itemRepo->save($item);

        return $item;
    }

    /**
     * 更新商品
     */
    public function update(int $id, array $data): Item
    {
        $item = $this->itemRepo->findById($id);

        if ($item === null) {
            throw new \RuntimeException("商品 #{$id} 不存在");
        }

        $item->fill($data);
        $this->itemRepo->save($item);

        return $item;
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
}
