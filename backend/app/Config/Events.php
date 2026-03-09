<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;

/*
 | --------------------------------------------------------------------
 | Application Events
 | --------------------------------------------------------------------
 | 透過 Events::on() 將 Event 名稱與 Listener 方法綁定。
 */

// ── 庫存扣減 ──────────────────────────────────────────
Events::on('stock.deducted', static function (\App\Events\StockDeducted $event): void {
    service('sendLowStockAlert')->handle($event);
    service('logInventoryTransaction')->handleDeducted($event);
});

// ── 庫存入庫 ──────────────────────────────────────────
Events::on('stock.replenished', static function (\App\Events\StockReplenished $event): void {
    service('logInventoryTransaction')->handleReplenished($event);
});

// ── 採購單核准 ────────────────────────────────────────
Events::on('purchase_order.approved', static function (\App\Events\PurchaseOrderApproved $event): void {
    service('logInventoryTransaction')->handlePoApproved($event);
});

// ── 銷售單確認 ────────────────────────────────────────
Events::on('sales_order.confirmed', static function (\App\Events\SalesOrderConfirmed $event): void {
    service('logInventoryTransaction')->handleSoConfirmed($event);
});
