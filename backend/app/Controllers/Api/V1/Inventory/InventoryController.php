<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Inventory;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\InventoryService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * InventoryController — 庫存查詢 & 調整
 *
 * GET  /api/v1/inventories              → index()
 * GET  /api/v1/inventories/low-stock   → lowStock()
 * GET  /api/v1/skus/:id/inventories    → bySku($skuId)
 * POST /api/v1/inventories/adjust      → adjust()
 */
class InventoryController extends BaseApiController
{
    private InventoryService $inventoryService;

    public function __construct()
    {
        $this->inventoryService = \Config\Services::inventoryService();
    }

    /**
     * GET /api/v1/inventories
     * 查詢全部庫存，支援 warehouse_id / sku_id / page / per_page
     */
    public function index(): ResponseInterface
    {
        $criteria = array_filter([
            'warehouse_id' => $this->request->getGet('warehouse_id'),
            'sku_id'       => $this->request->getGet('sku_id'),
        ]);

        $options = [
            'page'     => (int) ($this->request->getGet('page') ?: 1),
            'per_page' => (int) ($this->request->getGet('per_page') ?: 20),
        ];

        $result = $this->inventoryService->getAllInventory($criteria, $options);

        return api_success([
            'data'  => array_map(fn ($inv) => $inv->toApiArray(), $result['data']),
            'total' => $result['total'],
            'page'  => $options['page'],
        ]);
    }

    /**
     * GET /api/v1/inventories/low-stock
     * 取得低於安全庫存的品項
     */
    public function lowStock(): ResponseInterface
    {
        $warehouseId = $this->request->getGet('warehouse_id')
            ? (int) $this->request->getGet('warehouse_id')
            : null;

        $items = $this->inventoryService->getLowStockItems($warehouseId);

        return api_success(
            array_map(fn ($inv) => $inv->toApiArray(), $items)
        );
    }

    /**
     * GET /api/v1/skus/:id/inventories
     * 查詢特定 SKU 在各倉庫的庫存
     */
    public function bySku(int $skuId = 0): ResponseInterface
    {
        $result = $this->inventoryService->getAllInventory(['sku_id' => $skuId], ['per_page' => 200]);

        return api_success(
            array_map(fn ($inv) => $inv->toApiArray(), $result['data'])
        );
    }

    /**
     * POST /api/v1/inventories/adjust
     * 手動調整庫存（盤點差異修正）
     */
    public function adjust(): ResponseInterface
    {
        $rules = [
            'sku_id'       => 'required|is_natural_no_zero',
            'warehouse_id' => 'required|is_natural_no_zero',
            'qty'          => 'required|numeric',
            'reason'       => 'required|max_length[500]',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        $body = $this->jsonBody();

        try {
            $inventory = $this->inventoryService->adjustStock(
                skuId:       (int) $body['sku_id'],
                warehouseId: (int) $body['warehouse_id'],
                qty:         (float) $body['qty'],
                reason:      $body['reason'],
                operatorId:  $this->currentUserId(),
            );

            return api_success($inventory->toApiArray(), '庫存調整成功');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
