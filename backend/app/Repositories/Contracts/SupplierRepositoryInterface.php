<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\Supplier;

interface SupplierRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?Supplier;

    /**
     * 依代碼查詢
     */
    public function findByCode(string $code): ?Supplier;

    /**
     * @return array{data: Supplier[], total: int}
     */
    public function findActive(array $options = []): array;
}
