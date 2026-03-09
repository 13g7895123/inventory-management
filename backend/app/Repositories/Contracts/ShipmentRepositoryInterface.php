<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\Shipment;
use App\Entities\ShipmentLine;

interface ShipmentRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?Shipment;

    /**
     * @return ShipmentLine[]
     */
    public function findLines(int $shipmentId): array;

    public function saveLine(ShipmentLine $line): bool;

    /**
     * @return Shipment[]
     */
    public function findBySalesOrder(int $salesOrderId): array;

    /**
     * 產生下一個出貨單號（格式：SH-YYYYMMDD-NNNN）
     */
    public function generateShipmentNumber(): string;
}
