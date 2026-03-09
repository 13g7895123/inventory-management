<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Warehouse;
use App\Models\WarehouseModel;

/**
 * WarehouseService — 倉庫管理業務邏輯
 */
class WarehouseService
{
    public function __construct(
        private readonly WarehouseModel $warehouseModel,
    ) {}

    /**
     * 取得倉庫列表（支援分頁）
     *
     * @return array{data: Warehouse[], total: int}
     */
    public function list(array $criteria = [], array $options = []): array
    {
        $page    = (int) ($options['page'] ?? 1);
        $perPage = (int) ($options['per_page'] ?? 50);

        $builder = $this->warehouseModel;

        if (isset($criteria['is_active'])) {
            $builder = $builder->where('is_active', (int) (bool) $criteria['is_active']);
        }

        $total = $builder->countAllResults(false);
        $data  = $builder
            ->orderBy('id', 'asc')
            ->paginate($perPage, 'default', $page) ?: [];

        return ['data' => $data, 'total' => $total];
    }

    /**
     * 取得單一倉庫
     */
    public function getById(int $id): ?Warehouse
    {
        return $this->warehouseModel->find($id);
    }

    /**
     * 新增倉庫
     *
     * @throws \DomainException
     */
    public function create(array $data): Warehouse
    {
        $warehouse = new Warehouse();
        $warehouse->fill([
            'name'      => $data['name'],
            'code'      => strtoupper(trim($data['code'])),
            'location'  => $data['location'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes'     => $data['notes'] ?? null,
        ]);

        if (! $this->warehouseModel->save($warehouse)) {
            $errors = $this->warehouseModel->errors();
            throw new \DomainException('建立倉庫失敗：' . implode(', ', $errors));
        }

        $id = (int) $this->warehouseModel->getInsertID();
        return $this->warehouseModel->find($id);
    }

    /**
     * 更新倉庫
     *
     * @throws \RuntimeException
     */
    public function update(int $id, array $data): Warehouse
    {
        $warehouse = $this->warehouseModel->find($id);
        if ($warehouse === null) {
            throw new \RuntimeException("找不到倉庫 #{$id}");
        }

        $fillable = array_filter([
            'name'      => $data['name']      ?? null,
            'code'      => isset($data['code']) ? strtoupper(trim($data['code'])) : null,
            'location'  => $data['location']  ?? null,
            'is_active' => $data['is_active'] ?? null,
            'notes'     => $data['notes']     ?? null,
        ], fn ($v) => $v !== null);

        $warehouse->fill($fillable);

        if (! $this->warehouseModel->save($warehouse)) {
            $errors = $this->warehouseModel->errors();
            throw new \DomainException('更新倉庫失敗：' . implode(', ', $errors));
        }

        return $this->warehouseModel->find($id);
    }
}
