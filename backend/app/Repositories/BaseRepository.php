<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\BaseEntity;
use App\Repositories\Contracts\RepositoryInterface;
use CodeIgniter\Model;

/**
 * BaseRepository — 抽象基底 Repository
 *
 * 封裝所有 Repository 共用的 CRUD 邏輯，
 * 子類別只需注入對應的 Model 並實作特定查詢方法。
 *
 * 架構職責：
 * - 將 CI4 Model（資料存取）與 Service（業務邏輯）解耦
 * - 統一分頁、篩選、排序的處理方式
 * - 確保回傳的都是強型別 Entity 物件，而非陣列
 */
abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected readonly Model $model) {}

    /**
     * 依 ID 取得單筆（由子類別覆寫以提供強型別回傳宣告）
     */
    public function findById(int $id): ?BaseEntity
    {
        return $this->model->find($id);
    }

    /**
     * 依多筆 ID 取得
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return $this->model->find($ids) ?: [];
    }

    /**
     * 通用查詢（含篩選、排序、分頁）
     *
     * $options 支援：
     *   page, per_page, sort, order
     *
     * $criteria 支援：
     *   ['field' => 'value']               → WHERE field = 'value'
     *   ['field' => ['LIKE', '%keyword%']] → WHERE field LIKE '%keyword%'
     */
    public function findAll(array $criteria = [], array $options = []): array
    {
        $builder = $this->model->builder();

        foreach ($criteria as $field => $condition) {
            if (is_array($condition)) {
                [$op, $value] = $condition;
                $builder->where("{$field} {$op}", $value);
            } else {
                $builder->where($field, $condition);
            }
        }

        // 排序
        $sort  = $options['sort']  ?? $this->model->primaryKey;
        $order = strtolower($options['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $builder->orderBy($sort, $order);

        // 計算總筆數
        $total = (clone $builder)->countAllResults(false);

        // 分頁
        $page    = max(1, (int) ($options['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($options['per_page'] ?? 20)));
        $builder->limit($perPage, ($page - 1) * $perPage);

        // 透過 Model 執行（確保 returnType = Entity）
        $data = $this->model
            ->setBuilder($builder)
            ->findAll();

        return [
            'data'  => $data ?: [],
            'total' => $total,
        ];
    }

    /**
     * 儲存 Entity（自動判斷 insert / update）
     */
    public function save(BaseEntity $entity): bool
    {
        return (bool) $this->model->save($entity);
    }

    /**
     * 軟刪除
     */
    public function delete(int $id): bool
    {
        return (bool) $this->model->delete($id);
    }

    /**
     * 計算筆數
     */
    public function count(array $criteria = []): int
    {
        $builder = $this->model->builder();

        foreach ($criteria as $field => $value) {
            $builder->where($field, $value);
        }

        return $builder->countAllResults();
    }
}
