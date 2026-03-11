<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Sales;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\SalesOrderPdfService;
use App\Services\SalesOrderService;
use App\Services\SalesPaymentService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SalesOrderController — 銷售訂單管理
 *
 * Routes:
 *   GET    /api/v1/sales-orders
 *   POST   /api/v1/sales-orders
 *   GET    /api/v1/sales-orders/:id
 *   POST   /api/v1/sales-orders/:id/confirm
 *   POST   /api/v1/sales-orders/:id/cancel
 *   GET    /api/v1/sales-orders/:id/pdf
 *   GET    /api/v1/sales-orders/:id/payments
 *   POST   /api/v1/sales-orders/:id/payments
 */
class SalesOrderController extends BaseApiController
{
    private SalesOrderService $soService;
    private SalesOrderPdfService $pdfService;
    private SalesPaymentService $paymentService;

    public function __construct()
    {
        $this->soService = \Config\Services::salesOrderService();
        $this->pdfService = \Config\Services::salesOrderPdfService();
        $this->paymentService = \Config\Services::salesPaymentService();
    }

    /**
     * GET /api/v1/sales-orders
     */
    public function index(): ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $criteria = [];
        if ($status = $this->request->getGet('status')) {
            $criteria['status'] = $status;
        }
        if ($customerId = $this->request->getGet('customer_id')) {
            $criteria['customer_id'] = (int) $customerId;
        }

        $result = $this->soService->list($criteria, [
            'page'     => $page,
            'per_page' => $perPage,
            'sort'     => $this->request->getGet('sort') ?? 'id',
            'order'    => $this->request->getGet('order') ?? 'desc',
        ]);

        return api_paginated(
            array_map(fn ($so) => $so->toArray(), $result['data']),
            $result['total'],
            $page,
            $perPage,
        );
    }

    /**
     * GET /api/v1/sales-orders/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $data = $this->soService->getWithLines((int) $id);

            return api_success([
                'sales_order' => $data['sales_order']->toArray(),
                'lines'       => array_map(fn ($l) => $l->toArray(), $data['lines']),
            ]);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/sales-orders
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'customer_id'          => 'required|is_natural_no_zero',
            'warehouse_id'         => 'required|is_natural_no_zero',
            'order_date'           => 'permit_empty|valid_date[Y-m-d]',
            'expected_ship_date'   => 'permit_empty|valid_date[Y-m-d]',
            'tax_rate'             => 'permit_empty|decimal',
            'lines'                => 'required',
            'lines.*.sku_id'       => 'required|is_natural_no_zero',
            'lines.*.ordered_qty'  => 'required|decimal|greater_than[0]',
            'lines.*.unit_price'   => 'required|decimal|greater_than_equal_to[0]',
            'lines.*.discount_rate' => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];

        if (!$this->validate($rules)) {
            return api_error(
                '請求參數錯誤',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors(),
            );
        }

        try {
            $so = $this->soService->create($this->jsonBody(), $this->currentUserId());
            return api_success($so->toArray(), '銷售訂單建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return api_error('建立銷售訂單失敗：' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/sales-orders/:id/confirm
     * 草稿 → 確認 (並預留庫存)
     */
    public function confirm($id = null): ResponseInterface
    {
        try {
            $so = $this->soService->confirm((int) $id, $this->currentUserId());
            return api_success($so->toArray(), '銷售訂單已確認，庫存已預留');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/sales-orders/:id/cancel
     */
    public function cancel($id = null): ResponseInterface
    {
        try {
            $so = $this->soService->cancel((int) $id);
            return api_success($so->toArray(), '銷售訂單已取消');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * GET /api/v1/sales-orders/:id/pdf
     * 下載發票 PDF
     */
    public function pdf($id = null): ResponseInterface
    {
        try {
            $pdfBytes = $this->pdfService->generate((int) $id);

            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_OK)
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', "attachment; filename=\"invoice-{$id}.pdf\"")
                ->setBody($pdfBytes);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * GET /api/v1/sales-orders/:id/payments
     */
    public function listPayments($id = null): ResponseInterface
    {
        try {
            $payments = $this->paymentService->listPayments((int) $id);

            return api_success(array_map(fn ($p) => $p->toArray(), $payments));
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/sales-orders/:id/payments
     *
     * Body:
     * {
     *   "amount": 5000.00,
     *   "payment_date": "2026-03-09",
     *   "payment_method": "bank_transfer",  // bank_transfer|cash|check|credit_card|other
     *   "reference_no": "TXN-12345",        // optional
     *   "notes": ""                         // optional
     * }
     */
    public function addPayment($id = null): ResponseInterface
    {
        $rules = [
            'amount'         => 'required|decimal|greater_than[0]',
            'payment_date'   => 'required|valid_date[Y-m-d]',
            'payment_method' => 'required|in_list[bank_transfer,cash,check,credit_card,other]',
            'reference_no'   => 'permit_empty|max_length[100]',
            'notes'          => 'permit_empty|max_length[1000]',
        ];

        if (!$this->validate($rules)) {
            return api_error(
                '請求參數錯誤',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors(),
            );
        }

        try {
            $payment = $this->paymentService->addPayment(
                (int) $id,
                $this->jsonBody(),
                $this->currentUserId(),
            );

            return api_success($payment->toArray(), '收款記錄已新增', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return api_error('新增收款記錄失敗：' . $e->getMessage());
        }
    }
}
