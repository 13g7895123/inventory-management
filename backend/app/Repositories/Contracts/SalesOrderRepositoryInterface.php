<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\SalesOrder;
use App\Entities\SalesOrderLine;

interface SalesOrderRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?SalesOrder;

    /**
     * @return SalesOrderLine[]
     */
    public function findLines(int $salesOrderId): array;

    public function findLine(int $lineId): ?SalesOrderLine;

    public function saveLine(SalesOrderLine $line): bool;

    /**
     * 累加已出貨數量
     */
    public function updateShippedQty(int $lineId, float $additionalQty): bool;

    /**
     * 產生下一個銷售單號（格式：SO-YYYYMMDD-NNNN）
     */
    public function generateSoNumber(): string;
}
