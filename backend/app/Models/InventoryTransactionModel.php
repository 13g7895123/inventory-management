<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * InventoryTransactionModel — 庫存異動流水帳
 *
 * 僅允許 INSERT，禁止 UPDATE / DELETE（稽核日誌設計）。
 */
class InventoryTransactionModel extends Model
{
    protected $table         = 'inventory_transactions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;   // 使用自訂的 occurred_at

    protected $allowedFields = [
        'sku_id',
        'warehouse_id',
        'tx_type',         // DEDUCT | REPLENISH | ADJUST | TRANSFER
        'qty_change',
        'qty_after',
        'unit_cost',
        'source_type',     // sales_order | purchase_order | adjustment | transfer
        'source_id',
        'operator_id',
        'note',
        'occurred_at',
        'created_at',
    ];

    /**
     * 明確禁止 UPDATE。
     */
    public function update($id = null, $data = null): bool
    {
        throw new \BadMethodCallException('inventory_transactions 為不可變稽核日誌，禁止 UPDATE。');
    }

    /**
     * 明確禁止 DELETE。
     */
    public function delete($id = null, bool $purge = false)
    {
        throw new \BadMethodCallException('inventory_transactions 為不可變稽核日誌，禁止 DELETE。');
    }
}
