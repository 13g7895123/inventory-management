<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Category;
use App\Entities\BaseEntity;
use App\Models\CategoryModel;
use App\Repositories\Contracts\CategoryRepositoryInterface;

class CategoryRepository extends BaseRepository implements CategoryRepositoryInterface
{
    public function __construct(CategoryModel $model)
    {
        parent::__construct($model);
    }

    public function findById(int $id): ?Category
    {
        return $this->model->find($id);
    }

    public function findAllActive(): array
    {
        $result = $this->findAll(['is_active' => true], ['sort' => 'sort_order', 'order' => 'asc']);
        return $result['data'] ?? [];
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
