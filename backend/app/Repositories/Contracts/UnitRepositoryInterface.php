<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\Unit;
use App\Entities\BaseEntity;

interface UnitRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?Unit;

    /** @return Unit[] */
    public function findAllActive(): array;

    public function save(BaseEntity $entity): bool;
}
