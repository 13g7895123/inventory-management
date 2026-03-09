<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Master;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\SupplierService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SupplierController — 供應商基礎資料管理
 *
 * Routes:
 *   GET  /api/v1/suppliers        → index
 *   POST /api/v1/suppliers        → create
 *   GET  /api/v1/suppliers/:id    → show
 *   PUT  /api/v1/suppliers/:id    → update
 */
class SupplierController extends BaseApiController
{
    public function __construct(private readonly SupplierService $supplierService)
    {
    }

    /**
     * GET /api/v1/suppliers
     */
    public function index(): ResponseInterface
    {
        $options = [
            'page'     => (int) ($this->request->getGet('page') ?? 1),
            'per_page' => (int) ($this->request->getGet('per_page') ?? 20),
            'sort'     => $this->request->getGet('sort') ?? 'id',
            'order'    => $this->request->getGet('order') ?? 'asc',
        ];

        $keyword = $this->request->getGet('keyword');
        $criteria = [];
        if ($keyword) {
            $criteria['name'] = ['LIKE', $keyword];
        }

        $result = $this->supplierService->list($criteria, $options);

        return api_paginated(
            array_map(fn ($s) => $s->toArray(), $result['data']),
            $result['total'],
            $options['page'],
            $options['per_page'],
        );
    }

    /**
     * GET /api/v1/suppliers/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $supplier = $this->supplierService->getById((int) $id);
            return api_success($supplier->toArray());
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/suppliers
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'code'           => 'required|max_length[32]',
            'name'           => 'required|max_length[255]',
            'contact_name'   => 'permit_empty|max_length[100]',
            'contact_phone'  => 'permit_empty|max_length[20]',
            'contact_email'  => 'permit_empty|valid_email',
            'tax_id'         => 'permit_empty|max_length[20]',
            'payment_terms'  => 'permit_empty|max_length[100]',
            'lead_time_days' => 'permit_empty|is_natural',
        ];

        if (!$this->validate($rules)) {
            return api_error(
                '請求參數錯誤',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors(),
            );
        }

        try {
            $supplier = $this->supplierService->create($this->jsonBody());
            return api_success($supplier->toArray(), '供應商建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_CONFLICT);
        } catch (\Exception $e) {
            return api_error('建立供應商失敗：' . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/suppliers/:id
     */
    public function update($id = null): ResponseInterface
    {
        $rules = [
            'name'           => 'permit_empty|max_length[255]',
            'contact_name'   => 'permit_empty|max_length[100]',
            'contact_phone'  => 'permit_empty|max_length[20]',
            'contact_email'  => 'permit_empty|valid_email',
            'tax_id'         => 'permit_empty|max_length[20]',
            'payment_terms'  => 'permit_empty|max_length[100]',
            'lead_time_days' => 'permit_empty|is_natural',
            'is_active'      => 'permit_empty|in_list[0,1]',
        ];

        if (!$this->validate($rules)) {
            return api_error(
                '請求參數錯誤',
                ResponseInterface::HTTP_UNPROCESSABLE_ENTITY,
                $this->validator->getErrors(),
            );
        }

        try {
            $supplier = $this->supplierService->update((int) $id, $this->jsonBody());
            return api_success($supplier->toArray(), '供應商更新成功');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return api_error('更新供應商失敗：' . $e->getMessage());
        }
    }
}
