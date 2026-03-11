<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Inventory;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\BatchSerialModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * BatchSerialController — 批號 / 序號查詢
 *
 * GET  /api/v1/batch-serials         → index()
 * GET  /api/v1/batch-serials/:id     → show($id)
 */
class BatchSerialController extends BaseApiController
{
    protected BatchSerialModel $batchSerialModel;

    public function __construct()
    {
        $this->batchSerialModel = new BatchSerialModel();
    }

    /**
     * GET /api/v1/batch-serials
     *
     * Query params:
     *   batch_number  – 批號（模糊搜尋）
     *   serial_number – 序號（模糊搜尋）
     *   sku_id        – SKU ID
     *   warehouse_id  – 倉庫 ID
     *   type          – batch | serial
     *   status        – available | reserved | consumed | expired
     *   page          – 頁碼（預設 1）
     *   per_page      – 每頁筆數（預設 20）
     */
    public function index(): ResponseInterface
    {
        $db      = \Config\Database::connect();
        $page    = (int) ($this->request->getGet('page') ?: 1);
        $perPage = (int) ($this->request->getGet('per_page') ?: 20);
        $offset  = ($page - 1) * $perPage;

        $builder = $db->table('batch_serials bs')
            ->select('bs.*, item_skus.sku_code, items.name AS item_name, warehouses.name AS warehouse_name')
            ->join('item_skus',  'item_skus.id = bs.sku_id', 'left')
            ->join('items',      'items.id = item_skus.item_id', 'left')
            ->join('warehouses', 'warehouses.id = bs.warehouse_id', 'left');

        $this->applyFilters($builder);

        $total = (clone $builder)->countAllResults(false);

        $rows = $builder->limit($perPage, $offset)->get()->getResultArray();

        return api_success([
            'data'     => $rows,
            'total'    => (int) $total,
            'page'     => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * GET /api/v1/batch-serials/:id
     */
    public function show($id = null): ResponseInterface
    {
        $id  = (int) $id;
        $db  = \Config\Database::connect();
        $row = $db->table('batch_serials bs')
            ->select('bs.*, item_skus.sku_code, items.name AS item_name, warehouses.name AS warehouse_name')
            ->join('item_skus',  'item_skus.id = bs.sku_id', 'left')
            ->join('items',      'items.id = item_skus.item_id', 'left')
            ->join('warehouses', 'warehouses.id = bs.warehouse_id', 'left')
            ->where('bs.id', $id)
            ->get()->getRowArray();

        if ($row === null) {
            return api_error('批號記錄不存在', ResponseInterface::HTTP_NOT_FOUND);
        }

        return api_success($row);
    }

    // ── 私有方法 ────────────────────────────────────────────────────────

    private function applyFilters(\CodeIgniter\Database\BaseBuilder $builder): void
    {
        if ($v = $this->request->getGet('batch_number')) {
            $builder->like('bs.batch_number', $v);
        }
        if ($v = $this->request->getGet('serial_number')) {
            $builder->like('bs.serial_number', $v);
        }
        if ($v = $this->request->getGet('sku_id')) {
            $builder->where('bs.sku_id', (int) $v);
        }
        if ($v = $this->request->getGet('warehouse_id')) {
            $builder->where('bs.warehouse_id', (int) $v);
        }
        if ($v = $this->request->getGet('type')) {
            $builder->where('bs.type', $v);
        }
        if ($v = $this->request->getGet('status')) {
            $builder->where('bs.status', $v);
        }
        if ($v = $this->request->getGet('sku_code')) {
            $builder->like('item_skus.sku_code', $v);
        }
    }
}
