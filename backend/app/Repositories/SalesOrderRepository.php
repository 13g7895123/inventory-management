<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\BaseEntity;
use App\Entities\SalesOrder;
use App\Entities\SalesOrderLine;
use App\Models\SalesOrderLineModel;
use App\Models\SalesOrderModel;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;

class SalesOrderRepository extends BaseRepository implements SalesOrderRepositoryInterface
{
    public function __construct(
        SalesOrderModel                        $model,
        private readonly SalesOrderLineModel   $lineModel,
    ) {
        parent::__construct($model);
    }

    public function findById(int $id): ?SalesOrder
    {
        return $this->model->find($id);
    }

    public function findLines(int $salesOrderId): array
    {
        return $this->lineModel
            ->where('sales_order_id', $salesOrderId)
            ->findAll();
    }

    public function findLine(int $lineId): ?SalesOrderLine
    {
        return $this->lineModel->find($lineId);
    }

    public function saveLine(SalesOrderLine $line): bool
    {
        return (bool) $this->lineModel->save($line);
    }

    public function updateShippedQty(int $lineId, float $additionalQty): bool
    {
        return (bool) $this->lineModel
            ->set('shipped_qty', "shipped_qty + {$additionalQty}", false)
            ->where('id', $lineId)
            ->update();
    }

    public function generateSoNumber(): string
    {
        $today  = date('Ymd');
        $prefix = "SO-{$today}-";

        $last = $this->model
            ->like('so_number', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        $seq = $last === null ? 1 : ((int) explode('-', $last->so_number)[3]) + 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
