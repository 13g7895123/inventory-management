<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\BaseEntity;
use App\Entities\PurchaseOrder;
use App\Entities\PurchaseOrderLine;
use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseOrderModel;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;

class PurchaseOrderRepository extends BaseRepository implements PurchaseOrderRepositoryInterface
{
    public function __construct(
        PurchaseOrderModel                    $model,
        private readonly PurchaseOrderLineModel $lineModel,
    ) {
        parent::__construct($model);
    }

    public function findById(int $id): ?PurchaseOrder
    {
        return $this->model->find($id);
    }

    public function findLines(int $purchaseOrderId): array
    {
        return $this->lineModel
            ->where('purchase_order_id', $purchaseOrderId)
            ->findAll();
    }

    public function findLine(int $lineId): ?PurchaseOrderLine
    {
        return $this->lineModel->find($lineId);
    }

    public function saveLine(PurchaseOrderLine $line): bool
    {
        return (bool) $this->lineModel->save($line);
    }

    public function updateReceivedQty(int $lineId, float $additionalQty): bool
    {
        return (bool) $this->lineModel
            ->set('received_qty', "received_qty + {$additionalQty}", false)
            ->where('id', $lineId)
            ->update();
    }

    public function generatePoNumber(): string
    {
        $today  = date('Ymd');
        $prefix = "PO-{$today}-";

        $last = $this->model
            ->like('po_number', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        if ($last === null) {
            $seq = 1;
        } else {
            $parts = explode('-', $last->po_number);
            $seq   = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
