<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Supplier;
use App\Repositories\Contracts\SupplierRepositoryInterface;

class SupplierService
{
    public function __construct(
        private readonly SupplierRepositoryInterface $supplierRepo,
    ) {
    }

    public function list(array $criteria = [], array $options = []): array
    {
        return $this->supplierRepo->findAll($criteria, $options);
    }

    public function getById(int $id): Supplier
    {
        $supplier = $this->supplierRepo->findById($id);
        if ($supplier === null) {
            throw new \RuntimeException("供應商 #{$id} 不存在");
        }
        return $supplier;
    }

    public function create(array $data): Supplier
    {
        // 檢查代碼唯一性
        if ($this->supplierRepo->findByCode($data['code'] ?? '') !== null) {
            throw new \DomainException("供應商代碼 {$data['code']} 已存在");
        }

        $supplier = new Supplier($data);
        $supplier->fill(['is_active' => 1]);
        $this->supplierRepo->save($supplier);

        return $this->supplierRepo->findById($supplier->id);
    }

    public function update(int $id, array $data): Supplier
    {
        $supplier = $this->supplierRepo->findById($id);
        if ($supplier === null) {
            throw new \RuntimeException("供應商 #{$id} 不存在");
        }

        $supplier->fill($data);
        $this->supplierRepo->save($supplier);

        return $this->supplierRepo->findById($id);
    }
}
