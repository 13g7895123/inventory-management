<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Inventory;

use App\Controllers\Api\V1\BaseApiController;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * InventoryTransactionController — 庫存異動日誌查詢
 *
 * GET /api/v1/inventory-transactions  → index()
 */
class InventoryTransactionController extends BaseApiController
{
    /**
     * GET /api/v1/inventory-transactions
     *
     * Query params:
     *   warehouse_id – 倉庫 ID
     *   sku_id       – SKU ID
     *   sku_code     – SKU 代碼（模糊搜尋）
     *   tx_type      – DEDUCT | REPLENISH | ADJUST | TRANSFER_IN | TRANSFER_OUT
     *   date_from    – 開始日期（Y-m-d）
     *   date_to      – 結束日期（Y-m-d）
     *   page         – 頁碼（預設 1）
     *   per_page     – 每頁筆數（預設 50）
     */
    public function index(): ResponseInterface
    {
        $db      = \Config\Database::connect();
        $page    = (int) ($this->request->getGet('page') ?: 1);
        $perPage = min((int) ($this->request->getGet('per_page') ?: 50), 200);
        $offset  = ($page - 1) * $perPage;

        $builder = $db->table('inventory_transactions tx')
            ->select('tx.*, item_skus.sku_code, items.name AS item_name, warehouses.name AS warehouse_name')
            ->join('item_skus',  'item_skus.id = tx.sku_id', 'left')
            ->join('items',      'items.id = item_skus.item_id', 'left')
            ->join('warehouses', 'warehouses.id = tx.warehouse_id', 'left');

        if ($v = $this->request->getGet('warehouse_id')) {
            $builder->where('tx.warehouse_id', (int) $v);
        }
        if ($v = $this->request->getGet('sku_id')) {
            $builder->where('tx.sku_id', (int) $v);
        }
        if ($v = $this->request->getGet('sku_code')) {
            $builder->like('item_skus.sku_code', $v);
        }
        if ($v = $this->request->getGet('tx_type')) {
            $builder->where('tx.tx_type', $v);
        }
        if ($v = $this->request->getGet('date_from')) {
            $builder->where('tx.occurred_at >=', $v . ' 00:00:00');
        }
        if ($v = $this->request->getGet('date_to')) {
            $builder->where('tx.occurred_at <=', $v . ' 23:59:59');
        }

        $total = (clone $builder)->countAllResults(false);

        $rows = $builder
            ->orderBy('tx.occurred_at', 'DESC')
            ->limit($perPage, $offset)
            ->get()
            ->getResultArray();

        return api_success([
            'data'     => $rows,
            'total'    => (int) $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }
}
