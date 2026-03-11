<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Purchase;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderPdfService;
use App\Services\PurchaseOrderService;
use App\Services\SupplierPaymentService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PurchaseOrderController — 採購單管理
 *
 * Routes:
 *   GET  /api/v1/purchase-orders
 *   POST /api/v1/purchase-orders
 *   GET  /api/v1/purchase-orders/:id
 *   POST /api/v1/purchase-orders/:id/submit
 *   POST /api/v1/purchase-orders/:id/approve
 *   POST /api/v1/purchase-orders/:id/cancel
 *   POST /api/v1/purchase-orders/:id/receive
 *   GET  /api/v1/purchase-orders/:id/payments
 *   POST /api/v1/purchase-orders/:id/payments
 */
class PurchaseOrderController extends BaseApiController
{
    private PurchaseOrderService $poService;
    private GoodsReceiptService $grService;
    private PurchaseOrderPdfService $pdfService;
    private SupplierPaymentService $paymentService;

    public function __construct()
    {
        $this->poService = \Config\Services::purchaseOrderService();
        $this->grService = \Config\Services::goodsReceiptService();
        $this->pdfService = \Config\Services::purchaseOrderPdfService();
        $this->paymentService = \Config\Services::supplierPaymentService();
    }

    /**
     * GET /api/v1/purchase-orders
     */
    public function index(): ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $criteria = [];
        if ($status = $this->request->getGet('status')) {
            $criteria['status'] = $status;
        }
        if ($supplierId = $this->request->getGet('supplier_id')) {
            $criteria['supplier_id'] = (int) $supplierId;
        }

        $result = $this->poService->list($criteria, [
            'page'     => $page,
            'per_page' => $perPage,
            'sort'     => $this->request->getGet('sort') ?? 'id',
            'order'    => $this->request->getGet('order') ?? 'desc',
        ]);

        return api_paginated(
            array_map(fn ($po) => $po->toArray(), $result['data']),
            $result['total'],
            $page,
            $perPage,
        );
    }

    /**
     * GET /api/v1/purchase-orders/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $data = $this->poService->getWithLines((int) $id);

            return api_success([
                'purchase_order' => $data['purchase_order']->toArray(),
                'lines'          => array_map(fn ($l) => $l->toArray(), $data['lines']),
            ]);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/purchase-orders
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'supplier_id'    => 'required|is_natural_no_zero',
            'warehouse_id'   => 'required|is_natural_no_zero',
            'expected_date'  => 'permit_empty|valid_date[Y-m-d]',
            'tax_rate'       => 'permit_empty|decimal',
            'lines'          => 'required',
            'lines.*.sku_id'      => 'required|is_natural_no_zero',
            'lines.*.ordered_qty' => 'required|decimal|greater_than[0]',
            'lines.*.unit_price'  => 'required|decimal|greater_than_equal_to[0]',
        ];

        if (!$this->validate($rules)) {
            return api_error(
                '請求參數錯誤',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors(),
            );
        }

        try {
            $po = $this->poService->create($this->jsonBody(), $this->currentUserId());

            return api_success($po->toArray(), '採購單建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            return api_error('建立採購單失敗：' . $e->getMessage());
        }
    }

    /**
     * POST /api/v1/purchase-orders/:id/submit
     * 草稿 → 待審核
     */
    public function submit($id = null): ResponseInterface
    {
        try {
            $po = $this->poService->submit((int) $id);
            return api_success($po->toArray(), '採購單已提交審核');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/purchase-orders/:id/approve
     * 待審核 → 已核准
     */
    public function approve($id = null): ResponseInterface
    {
        try {
            $po = $this->poService->approve((int) $id, $this->currentUserId());
            return api_success($po->toArray(), '採購單已核准');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/purchase-orders/:id/cancel
     */
    public function cancel($id = null): ResponseInterface
    {
        try {
            $po = $this->poService->cancel((int) $id);
            return api_success($po->toArray(), '採購單已取消');
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/purchase-orders/:id/receive
     * 進貨驗收：觸發庫存入庫
     *
     * Body:
     * {
     *   "lines": [
     *     {
     *       "line_id": 1,
     *       "received_qty": 10,
     *       "unit_cost": 50.00,      // optional, defaults to PO unit_price
     *       "batch_number": "B001",  // optional
     *       "expiry_date": "2027-12-31", // optional
     *       "notes": ""
     *     }
     *   ]
     * }
     */
    public function receive($id = null): ResponseInterface
    {
        $rules = [
            'lines'                   => 'required',
            'lines.*.line_id'         => 'required|is_natural_no_zero',
            'lines.*.received_qty'    => 'required|decimal|greater_than[0]',
            'lines.*.unit_cost'       => 'permit_empty|decimal|greater_than_equal_to[0]',
            'lines.*.batch_number'    => 'permit_empty|max_length[64]',
            'lines.*.expiry_date'     => 'permit_empty|valid_date[Y-m-d]',
        ];

        if (!$this->validate($rules)) {
            return api_error(
                '請求參數錯誤',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors(),
            );
        }

        try {
            $body = $this->jsonBody();
            $gr   = $this->grService->receive((int) $id, $body['lines'], $this->currentUserId());

            return api_success(
                ['gr_number' => $gr->gr_number, 'id' => $gr->id],
                '進貨驗收成功，庫存已入庫',
                ResponseInterface::HTTP_CREATED,
            );
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return api_error('進貨驗收失敗：' . $e->getMessage());
        }
    }

    /**
     * GET /api/v1/purchase-orders/:id/pdf
     */
    public function pdf($id = null): ResponseInterface
    {
        try {
            $pdfContent = $this->pdfService->generate((int) $id);

            return $this->response
                ->setStatusCode(ResponseInterface::HTTP_OK)
                ->setHeader('Content-Type', 'application/pdf')
                ->setHeader('Content-Disposition', 'inline; filename="PO-' . $id . '.pdf"')
                ->setBody($pdfContent);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return api_error('PDF 產生失敗：' . $e->getMessage());
        }
    }

    // ── 付款記錄 ──────────────────────────────────────────────────────

    /**
     * GET /api/v1/purchase-orders/:id/payments
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
     * POST /api/v1/purchase-orders/:id/payments
     *
     * Body:
     * {
     *   "amount": 10000.00,
     *   "payment_date": "2026-03-09",
     *   "payment_method": "bank_transfer",  // bank_transfer|cash|check|other
     *   "reference_no": "TXN-001",          // optional
     *   "notes": ""                         // optional
     * }
     */
    public function addPayment($id = null): ResponseInterface
    {
        $rules = [
            'amount'         => 'required|decimal|greater_than[0]',
            'payment_date'   => 'required|valid_date[Y-m-d]',
            'payment_method' => 'required|in_list[bank_transfer,cash,check,other]',
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

            return api_success($payment->toArray(), '付款記錄已新增', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return api_error('新增付款失敗：' . $e->getMessage());
        }
    }
}
