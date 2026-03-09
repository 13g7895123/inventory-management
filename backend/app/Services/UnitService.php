<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Unit;
use App\Repositories\Contracts\UnitRepositoryInterface;

/**
 * UnitService — 計量單位業務邏輯
 */
class UnitService
{
    public function __construct(
        private readonly UnitRepositoryInterface $unitRepo,
    ) {}

    /**
     * 取得所有計量單位
     *
     * @return Unit[]
     */
    public function list(): array
    {
        $result = $this->unitRepo->findAll([], ['sort' => 'name', 'order' => 'asc']);
        return $result['data'];
    }

    /**
     * 依 ID 取得計量單位
     */
    public function getById(int $id): ?Unit
    {
        return $this->unitRepo->findById($id);
    }

    /**
     * 新增計量單位
     */
    public function create(array $data): Unit
    {
        $unit = new Unit($data);
        $this->unitRepo->save($unit);

        return $this->unitRepo->findById($unit->id);
    }

    /**
     * 更新計量單位
     */
    public function update(int $id, array $data): Unit
    {
        $unit = $this->unitRepo->findById($id);

        if ($unit === null) {
            throw new \RuntimeException("計量單位 #{$id} 不存在");
        }

        $unit->fill($data);
        $this->unitRepo->save($unit);

        return $this->unitRepo->findById($id);
    }

    /**
     * 刪除計量單位
     */
    public function delete(int $id): void
    {
        $unit = $this->unitRepo->findById($id);

        if ($unit === null) {
            throw new \RuntimeException("計量單位 #{$id} 不存在");
        }

        $this->unitRepo->delete($id);
    }
}
