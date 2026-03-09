<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Unit;
use App\Entities\BaseEntity;
use App\Models\UnitModel;
use App\Repositories\Contracts\UnitRepositoryInterface;

class UnitRepository extends BaseRepository implements UnitRepositoryInterface
{
    public function __construct(UnitModel $model)
    {
        parent::__construct($model);
    }

    public function findById(int $id): ?Unit
    {
        return $this->model->find($id);
    }

    public function findAllActive(): array
    {
        $result = $this->findAll(['is_active' => true], ['sort' => 'name', 'order' => 'asc']);
        return $result['data'] ?? [];
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
