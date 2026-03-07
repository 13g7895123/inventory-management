<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\StockDeducted;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;

/**
 * SendLowStockAlert — 低庫存警示 Listener
 *
 * 每次出庫後比對安全庫存，若低於閾值則寫入通知。
 */
class SendLowStockAlert
{
    public function __construct(
        private readonly InventoryRepositoryInterface $inventoryRepo,
        private readonly ItemRepositoryInterface      $itemRepo,
    ) {}

    public function handle(StockDeducted $event): void
    {
        $inventory = $this->inventoryRepo->findBySkuAndWarehouse(
            $event->skuId,
            $event->warehouseId
        );

        if ($inventory === null) {
            return;
        }

        // 透過 ItemSku → Item 取得安全庫存設定
        $item = $this->itemRepo->findBySkuId($event->skuId);
        if ($item === null) {
            return;
        }

        if ($item->isBelowSafetyStock($inventory->getAvailableQty())) {
            // 寫入 inventory_alerts 或發送 WebSocket Push
            // 此處示範：寫入 CI4 log，實際可替換為 NotificationService
            log_message('warning', sprintf(
                '[LowStock] SKU #%d 在倉庫 #%d 低於安全庫存 (現有: %.2f, 安全: %.2f)',
                $event->skuId,
                $event->warehouseId,
                $inventory->getAvailableQty(),
                (float) $item->safety_stock,
            ));
        }
    }
}
