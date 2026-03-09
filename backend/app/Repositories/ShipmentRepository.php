<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\BaseEntity;
use App\Entities\Shipment;
use App\Entities\ShipmentLine;
use App\Models\ShipmentLineModel;
use App\Models\ShipmentModel;
use App\Repositories\Contracts\ShipmentRepositoryInterface;

class ShipmentRepository extends BaseRepository implements ShipmentRepositoryInterface
{
    public function __construct(
        ShipmentModel                       $model,
        private readonly ShipmentLineModel  $lineModel,
    ) {
        parent::__construct($model);
    }

    public function findById(int $id): ?Shipment
    {
        return $this->model->find($id);
    }

    public function findLines(int $shipmentId): array
    {
        return $this->lineModel
            ->where('shipment_id', $shipmentId)
            ->findAll();
    }

    public function saveLine(ShipmentLine $line): bool
    {
        return (bool) $this->lineModel->save($line);
    }

    public function findBySalesOrder(int $salesOrderId): array
    {
        return $this->model
            ->where('sales_order_id', $salesOrderId)
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    public function generateShipmentNumber(): string
    {
        $today  = date('Ymd');
        $prefix = "SH-{$today}-";

        $last = $this->model
            ->like('shipment_number', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        $seq = $last === null ? 1 : ((int) explode('-', $last->shipment_number)[3]) + 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
