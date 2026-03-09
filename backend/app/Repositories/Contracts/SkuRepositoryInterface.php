<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\ItemSku;
use App\Entities\BaseEntity;

interface SkuRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?ItemSku;

    /**
     * 取得商品所有 SKU
     * @return ItemSku[]
     */
    public function findByItemId(int $itemId): array;

    /**
     * 依 SKU 代碼查找
     */
    public function findByCode(string $skuCode): ?ItemSku;

    public function save(BaseEntity $entity): bool;
}
