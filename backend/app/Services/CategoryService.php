<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;

/**
 * CategoryService — 商品分類業務邏輯
 */
class CategoryService
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepo,
    ) {}

    /**
     * 取得所有啟用分類（依 sort_order/name 排序）
     *
     * @return Category[]
     */
    public function list(): array
    {
        $result = $this->categoryRepo->findAll([], ['sort' => 'sort_order', 'order' => 'asc']);
        return $result['data'];
    }

    /**
     * 依 ID 取得分類
     */
    public function getById(int $id): ?Category
    {
        return $this->categoryRepo->findById($id);
    }

    /**
     * 新增分類
     */
    public function create(array $data): Category
    {
        // 自動產生 slug（若未提供）
        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = $this->generateSlug($data['name']);
        }

        $category = new Category($data);
        $this->categoryRepo->save($category);

        return $this->categoryRepo->findById($category->id);
    }

    /**
     * 更新分類
     */
    public function update(int $id, array $data): Category
    {
        $category = $this->categoryRepo->findById($id);

        if ($category === null) {
            throw new \RuntimeException("分類 #{$id} 不存在");
        }

        $category->fill($data);
        $this->categoryRepo->save($category);

        return $this->categoryRepo->findById($id);
    }

    /**
     * 軟刪除分類
     */
    public function delete(int $id): void
    {
        $category = $this->categoryRepo->findById($id);

        if ($category === null) {
            throw new \RuntimeException("分類 #{$id} 不存在");
        }

        $this->categoryRepo->delete($id);
    }

    private function generateSlug(string $name): string
    {
        // 簡單 slug：轉小寫、空格換成 -、移除特殊字元
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-\x{4e00}-\x{9fff}]/u', '', $slug);
        return $slug ?: bin2hex(random_bytes(4));
    }
}
