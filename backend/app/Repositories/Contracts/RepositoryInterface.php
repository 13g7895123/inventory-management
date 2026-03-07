<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\BaseEntity;

/**
 * RepositoryInterface — 泛用 Repository 合約
 *
 * 定義所有 Repository 必須實作的基礎方法。
 * 透過介面進行依賴注入，方便替換實作（如：測試時用 InMemory Repository）。
 */
interface RepositoryInterface
{
    /**
     * 依 ID 取得單筆 Entity
     */
    public function findById(int $id): ?BaseEntity;

    /**
     * 依多個 ID 取得多筆 Entity
     *
     * @return BaseEntity[]
     */
    public function findByIds(array $ids): array;

    /**
     * 取得所有（含篩選、分頁）
     *
     * @param array $criteria  篩選條件 ['field' => 'value'] 或 ['field' => ['op', 'value']]
     * @param array $options   ['sort' => 'field', 'order' => 'asc', 'page' => 1, 'per_page' => 20]
     * @return array{data: BaseEntity[], total: int}
     */
    public function findAll(array $criteria = [], array $options = []): array;

    /**
     * 持久化 Entity（insert 或 update）
     */
    public function save(BaseEntity $entity): bool;

    /**
     * 軟刪除
     */
    public function delete(int $id): bool;

    /**
     * 計算符合條件的資料筆數
     */
    public function count(array $criteria = []): int;
}
