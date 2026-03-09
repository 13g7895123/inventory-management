<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\Category;
use App\Entities\BaseEntity;

interface CategoryRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?Category;

    /** @return Category[] */
    public function findAllActive(): array;

    public function save(BaseEntity $entity): bool;
}
