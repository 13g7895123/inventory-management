<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\Supplier;
use App\Entities\BaseEntity;
use App\Models\SupplierModel;
use App\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierRepository extends BaseRepository implements SupplierRepositoryInterface
{
    public function __construct(SupplierModel $model)
    {
        parent::__construct($model);
    }

    public function findById(int $id): ?Supplier
    {
        return $this->model->find($id);
    }

    public function findByCode(string $code): ?Supplier
    {
        return $this->model->where('code', $code)->first();
    }

    public function findActive(array $options = []): array
    {
        return $this->findAll(['is_active' => 1], $options);
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
