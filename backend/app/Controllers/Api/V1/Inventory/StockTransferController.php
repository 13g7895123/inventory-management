<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Inventory;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\StockTransferService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * StockTransferController — 庫存調撥
 *
 * GET  /api/v1/stock-transfers              → index()
 * POST /api/v1/stock-transfers              → create()
 * GET  /api/v1/stock-transfers/:id          → show($id)
 * POST /api/v1/stock-transfers/:id/confirm  → confirm($id)
 * POST /api/v1/stock-transfers/:id/cancel   → cancel($id)
 */
class StockTransferController extends BaseApiController
{
    private StockTransferService $stockTransferService;

    public function __construct()
    {
        $this->stockTransferService = \Config\Services::stockTransferService();
    }

    /**
     * GET /api/v1/stock-transfers
     */
    public function index(): ResponseInterface
    {
        $criteria = array_filter([
            'status'            => $this->request->getGet('status'),
            'from_warehouse_id' => $this->request->getGet('from_warehouse_id'),
            'to_warehouse_id'   => $this->request->getGet('to_warehouse_id'),
        ]);

        $options = [
            'page'     => (int) ($this->request->getGet('page') ?: 1),
            'per_page' => (int) ($this->request->getGet('per_page') ?: 20),
        ];

        $result = $this->stockTransferService->list($criteria, $options);

        return api_success([
            'data'  => array_map(fn ($t) => $t->toApiArray(), $result['data']),
            'total' => $result['total'],
            'page'  => $options['page'],
        ]);
    }

    /**
     * GET /api/v1/stock-transfers/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $data = $this->stockTransferService->getWithLines((int) $id);

            return api_success([
                'transfer' => $data['transfer']->toApiArray(),
                'lines'    => array_map(fn ($l) => $l->toApiArray(), $data['lines']),
            ]);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/stock-transfers
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'from_warehouse_id'   => 'required|is_natural_no_zero',
            'to_warehouse_id'     => 'required|is_natural_no_zero',
            'lines'               => 'required|is_array',
            'lines.*.sku_id'      => 'required|is_natural_no_zero',
            'lines.*.qty'         => 'required|numeric|greater_than[0]',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $transfer = $this->stockTransferService->create($this->jsonBody(), $this->currentUserId());

            return api_success($transfer->toApiArray(), '調撥單建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage());
        }
    }

    /**
     * POST /api/v1/stock-transfers/:id/confirm
     */
    public function confirm($id = null): ResponseInterface
    {
        try {
            $transfer = $this->stockTransferService->confirm((int) $id, $this->currentUserId());

            return api_success($transfer->toApiArray(), '調撥確認成功');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/stock-transfers/:id/cancel
     */
    public function cancel($id = null): ResponseInterface
    {
        try {
            $transfer = $this->stockTransferService->cancel((int) $id);

            return api_success($transfer->toApiArray(), '調撥單已取消');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
