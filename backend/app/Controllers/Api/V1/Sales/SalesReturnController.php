<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Sales;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\SalesReturnService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SalesReturnController — 銷售退貨管理
 *
 * Routes:
 *   GET  /api/v1/sales-orders/:orderId/returns     → listByOrder
 *   POST /api/v1/sales-orders/:orderId/returns     → create
 *   GET  /api/v1/sales-returns/:id                 → show
 *   POST /api/v1/sales-returns/:id/confirm         → confirm
 *   POST /api/v1/sales-returns/:id/cancel          → cancel
 */
class SalesReturnController extends BaseApiController
{
    public function __construct(
        private readonly SalesReturnService $returnService,
    ) {}

    /**
     * GET /api/v1/sales-orders/:orderId/returns
     */
    public function listByOrder($orderId = null): ResponseInterface
    {
        try {
            $returns = $this->returnService->listByOrder((int) $orderId);

            return api_success(array_map(fn ($r) => $r->toArray(), $returns));
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/sales-orders/:orderId/returns
     *
     * Body:
     * {
     *   "warehouse_id": 1,
     *   "reason": "品質問題",
     *   "refund_amount": 1000.00,
     *   "notes": "",
     *   "lines": [
     *     {
     *       "sales_order_line_id": 1,
     *       "sku_id": 5,
     *       "return_qty": 2,
     *       "unit_price": 500.00,     // optional
     *       "return_reason": "破損",  // optional
     *       "batch_number": "B001",   // optional
     *       "notes": ""               // optional
     *     }
     *   ]
     * }
     */
    public function create($orderId = null): ResponseInterface
    {
        $rules = [
            'warehouse_id'                     => 'required|is_natural_no_zero',
            'reason'                           => 'permit_empty|max_length[1000]',
            'refund_amount'                    => 'permit_empty|decimal|greater_than_equal_to[0]',
            'notes'                            => 'permit_empty|max_length[1000]',
            'lines'                            => 'required',
            'lines.*.sales_order_line_id'      => 'required|is_natural_no_zero',
            'lines.*.sku_id'                   => 'required|is_natural_no_zero',
            'lines.*.return_qty'               => 'required|decimal|greater_than[0]',
            'lines.*.unit_price'               => 'permit_empty|decimal|greater_than_equal_to[0]',
            'lines.*.batch_number'             => 'permit_empty|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return api_error(
                '請求參數錯誤',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors(),
            );
        }

        try {
            $return = $this->returnService->create(
                (int) $orderId,
                $this->jsonBody(),
                $this->currentUserId(),
            );

            return api_success($return->toArray(), '退貨單已建立', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return api_error('建立退貨單失敗：' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/sales-returns/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $data = $this->returnService->getWithLines((int) $id);

            return api_success([
                'sales_return' => $data['return']->toArray(),
                'lines'        => array_map(fn ($l) => $l->toArray(), $data['lines']),
            ]);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/sales-returns/:id/confirm
     * 草稿 → 確認（並入庫）
     */
    public function confirm($id = null): ResponseInterface
    {
        try {
            $return = $this->returnService->confirm((int) $id, $this->currentUserId());

            return api_success($return->toArray(), '退貨單已確認，庫存已入庫');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/sales-returns/:id/cancel
     * 取消退貨單（僅草稿可取消）
     */
    public function cancel($id = null): ResponseInterface
    {
        try {
            $return = $this->returnService->cancel((int) $id);

            return api_success($return->toArray(), '退貨單已取消');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
