<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PurchaseOrderApproved;
use App\Events\SalesOrderConfirmed;
use App\Events\StockDeducted;
use App\Events\StockReplenished;
use CodeIgniter\Database\BaseConnection;

/**
 * LogInventoryTransaction — 庫存異動日誌 Listener
 *
 * 監聽所有庫存相關事件，寫入 inventory_transactions 稽核表。
 */
class LogInventoryTransaction
{
    public function __construct(private readonly BaseConnection $db) {}

    public function handleDeducted(StockDeducted $event): void
    {
        $this->insert([
            'sku_id'       => $event->skuId,
            'warehouse_id' => $event->warehouseId,
            'tx_type'      => 'DEDUCT',
            'qty_change'   => -$event->deductedQuantity,
            'qty_after'    => $event->remainingOnHand,
            'source_type'  => $event->sourceType,
            'source_id'    => $event->sourceId,
            'operator_id'  => $event->operatorId,
            'occurred_at'  => $event->occurredAt,
        ]);
    }

    public function handleReplenished(StockReplenished $event): void
    {
        $this->insert([
            'sku_id'       => $event->skuId,
            'warehouse_id' => $event->warehouseId,
            'tx_type'      => 'REPLENISH',
            'qty_change'   => +$event->addedQuantity,
            'qty_after'    => $event->newOnHand,
            'unit_cost'    => $event->unitCost,
            'source_type'  => $event->sourceType,
            'source_id'    => $event->sourceId,
            'operator_id'  => $event->operatorId,
            'occurred_at'  => $event->occurredAt,
        ]);
    }

    public function handlePoApproved(PurchaseOrderApproved $event): void
    {
        log_message('info', sprintf(
            '[AuditLog] PO #%s 已核准，核准人 #%d，預計到貨: %s',
            $event->poNumber,
            $event->approvedBy,
            $event->expectedArrivalDate,
        ));
    }

    public function handleSoConfirmed(SalesOrderConfirmed $event): void
    {
        log_message('info', sprintf(
            '[AuditLog] SO #%s 已確認，客戶 #%d，共 %d 項商品',
            $event->soNumber,
            $event->customerId,
            count($event->lineItems),
        ));
    }

    private function insert(array $data): void
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->table('inventory_transactions')->insert($data);
    }
}
