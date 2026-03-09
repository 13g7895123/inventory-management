<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\ItemSku;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\SkuRepositoryInterface;

/**
 * SkuService — SKU 變體業務邏輯
 */
class SkuService
{
    public function __construct(
        private readonly SkuRepositoryInterface  $skuRepo,
        private readonly ItemRepositoryInterface $itemRepo,
    ) {}

    /**
     * 取得商品的 SKU 列表
     *
     * @return ItemSku[]
     */
    public function listByItem(int $itemId): array
    {
        $item = $this->itemRepo->findById($itemId);

        if ($item === null) {
            throw new \RuntimeException("商品 #{$itemId} 不存在");
        }

        return $this->skuRepo->findByItemId($itemId);
    }

    /**
     * 新增 SKU
     */
    public function create(int $itemId, array $data): ItemSku
    {
        $item = $this->itemRepo->findById($itemId);

        if ($item === null) {
            throw new \RuntimeException("商品 #{$itemId} 不存在");
        }

        if (empty($data['sku_code'])) {
            $data['sku_code'] = $item->code . '-' . uniqid();
        }

        $sku = new ItemSku(array_merge($data, ['item_id' => $itemId]));
        $this->skuRepo->save($sku);

        return $this->skuRepo->findById($sku->id);
    }

    /**
     * 更新 SKU
     */
    public function update(int $skuId, array $data): ItemSku
    {
        $sku = $this->skuRepo->findById($skuId);

        if ($sku === null) {
            throw new \RuntimeException("SKU #{$skuId} 不存在");
        }

        unset($data['item_id']); // 不允許變更所屬商品
        $sku->fill($data);
        $this->skuRepo->save($sku);

        return $this->skuRepo->findById($skuId);
    }

    /**
     * 刪除 SKU（軟刪除）
     */
    public function delete(int $skuId): void
    {
        $sku = $this->skuRepo->findById($skuId);

        if ($sku === null) {
            throw new \RuntimeException("SKU #{$skuId} 不存在");
        }

        $this->skuRepo->delete($skuId);
    }
}
