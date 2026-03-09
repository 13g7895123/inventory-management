<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\PurchaseOrder;
use App\Entities\PurchaseOrderLine;

interface PurchaseOrderRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?PurchaseOrder;

    /**
     * @return PurchaseOrderLine[]
     */
    public function findLines(int $purchaseOrderId): array;

    /**
     * 找單條明細
     */
    public function findLine(int $lineId): ?PurchaseOrderLine;

    /**
     * 插入或更新採購單明細
     */
    public function saveLine(PurchaseOrderLine $line): bool;

    /**
     * 更新明細的已到貨數量
     */
    public function updateReceivedQty(int $lineId, float $additionalQty): bool;

    /**
     * 產生下一個採購單號（格式：PO-YYYYMMDD-NNNN）
     */
    public function generatePoNumber(): string;
}
