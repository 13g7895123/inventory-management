<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Inventory;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\StocktakeService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * StocktakeController — 盤點管理
 *
 * GET  /api/v1/stocktakes                        → index()
 * POST /api/v1/stocktakes                        → create()
 * GET  /api/v1/stocktakes/:id                    → show($id)
 * POST /api/v1/stocktakes/:id/start              → start($id)
 * POST /api/v1/stocktakes/:id/count              → updateCount($id)
 * POST /api/v1/stocktakes/:id/confirm            → confirm($id)
 * POST /api/v1/stocktakes/:id/cancel             → cancel($id)
 */
class StocktakeController extends BaseApiController
{
    public function __construct(private readonly StocktakeService $stocktakeService)
    {
    }

    /**
     * GET /api/v1/stocktakes
     */
    public function index(): ResponseInterface
    {
        $criteria = array_filter([
            'status'       => $this->request->getGet('status'),
            'warehouse_id' => $this->request->getGet('warehouse_id'),
        ]);

        $options = [
            'page'     => (int) ($this->request->getGet('page') ?: 1),
            'per_page' => (int) ($this->request->getGet('per_page') ?: 20),
        ];

        $result = $this->stocktakeService->list($criteria, $options);

        return api_success([
            'data'  => array_map(fn ($s) => $s->toApiArray(), $result['data']),
            'total' => $result['total'],
            'page'  => $options['page'],
        ]);
    }

    /**
     * GET /api/v1/stocktakes/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $data = $this->stocktakeService->getWithLines((int) $id);

            return api_success([
                'stocktake' => $data['stocktake']->toApiArray(),
                'lines'     => array_map(fn ($l) => $l->toApiArray(), $data['lines']),
            ]);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/stocktakes
     * Body: { warehouse_id: int, notes?: string }
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'warehouse_id' => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        $body = $this->jsonBody();

        try {
            $stocktake = $this->stocktakeService->create(
                warehouseId: (int) $body['warehouse_id'],
                data:        $body,
                createdBy:   $this->currentUserId(),
            );

            return api_success($stocktake->toApiArray(), '盤點單建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage());
        }
    }

    /**
     * POST /api/v1/stocktakes/:id/start
     */
    public function start($id = null): ResponseInterface
    {
        try {
            $stocktake = $this->stocktakeService->start((int) $id);

            return api_success($stocktake->toApiArray(), '盤點已開始');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/stocktakes/:id/count
     * Body: { sku_id: int, actual_qty: float }
     */
    public function updateCount($id = null): ResponseInterface
    {
        $rules = [
            'sku_id'     => 'required|is_natural_no_zero',
            'actual_qty' => 'required|numeric|greater_than_equal_to[0]',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        $body = $this->jsonBody();

        try {
            $line = $this->stocktakeService->updateCount(
                stocktakeId: (int) $id,
                skuId:       (int) $body['sku_id'],
                actualQty:   (float) $body['actual_qty'],
            );

            return api_success($line->toApiArray(), '盤點數量已更新');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/stocktakes/:id/confirm
     */
    public function confirm($id = null): ResponseInterface
    {
        try {
            $stocktake = $this->stocktakeService->confirm((int) $id, $this->currentUserId());

            return api_success($stocktake->toApiArray(), '盤點已確認並完成庫存調整');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/stocktakes/:id/cancel
     */
    public function cancel($id = null): ResponseInterface
    {
        try {
            $stocktake = $this->stocktakeService->cancel((int) $id);

            return api_success($stocktake->toApiArray(), '盤點單已取消');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
