<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\ItemSku;
use App\Entities\BaseEntity;
use App\Models\ItemSkuModel;
use App\Repositories\Contracts\SkuRepositoryInterface;

class SkuRepository extends BaseRepository implements SkuRepositoryInterface
{
    public function __construct(ItemSkuModel $model)
    {
        parent::__construct($model);
    }

    public function findById(int $id): ?ItemSku
    {
        return $this->model->find($id);
    }

    public function findByItemId(int $itemId): array
    {
        return $this->model
            ->where('item_id', $itemId)
            ->findAll() ?: [];
    }

    public function findByCode(string $skuCode): ?ItemSku
    {
        return $this->model
            ->where('sku_code', $skuCode)
            ->first();
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
