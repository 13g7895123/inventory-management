<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Sales;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\ShipmentService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ShipmentController — 出貨管理
 *
 * Routes:
 *   GET  /api/v1/sales-orders/:id/shipments
 *   POST /api/v1/sales-orders/:id/shipments
 *   GET  /api/v1/shipments/:id
 */
class ShipmentController extends BaseApiController
{
    private ShipmentService $shipmentService;

    public function __construct()
    {
        $this->shipmentService = \Config\Services::shipmentService();
    }

    /**
     * GET /api/v1/sales-orders/:soId/shipments
     */
    public function listBySalesOrder($soId = null): ResponseInterface
    {
        try {
            $shipments = $this->shipmentService->listBySalesOrder((int) $soId);
            return api_success(array_map(fn ($s) => $s->toArray(), $shipments));
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/sales-orders/:soId/shipments
     * 建立出貨單 (庫存扣減)
     */
    public function create($soId = null): ResponseInterface
    {
        $rules = [
            'lines'               => 'required',
            'lines.*.sol_id'      => 'required|is_natural_no_zero',
            'lines.*.shipped_qty' => 'required|decimal|greater_than[0]',
            'carrier'             => 'permit_empty|max_length[100]',
            'tracking_number'     => 'permit_empty|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return api_error(
                '請求參數錯誤',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors(),
            );
        }

        try {
            $shipment = $this->shipmentService->create((int) $soId, $this->jsonBody(), $this->currentUserId());
            $data     = $this->shipmentService->getWithLines($shipment->id);
            return api_success(
                [
                    'shipment' => $data['shipment']->toArray(),
                    'lines'    => array_map(fn ($l) => $l->toArray(), $data['lines']),
                ],
                '出貨單建立成功，庫存已扣減',
                ResponseInterface::HTTP_CREATED,
            );
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return api_error('建立出貨單失敗：' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/shipments/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $data = $this->shipmentService->getWithLines((int) $id);
            return api_success([
                'shipment' => $data['shipment']->toArray(),
                'lines'    => array_map(fn ($l) => $l->toArray(), $data['lines']),
            ]);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
