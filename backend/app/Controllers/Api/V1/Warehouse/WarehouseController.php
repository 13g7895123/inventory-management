<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Warehouse;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\WarehouseService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * WarehouseController — 倉庫管理 CRUD
 *
 * GET  /api/v1/warehouses        → index()
 * POST /api/v1/warehouses        → create()
 * GET  /api/v1/warehouses/:id    → show($id)
 * PUT  /api/v1/warehouses/:id    → update($id)
 */
class WarehouseController extends BaseApiController
{
    private WarehouseService $warehouseService;

    public function __construct()
    {
        $this->warehouseService = \Config\Services::warehouseService();
    }

    /**
     * GET /api/v1/warehouses
     */
    public function index(): ResponseInterface
    {
        $criteria = [];
        if ($this->request->getGet('is_active') !== null) {
            $criteria['is_active'] = filter_var($this->request->getGet('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        $options = [
            'page'     => (int) ($this->request->getGet('page') ?: 1),
            'per_page' => (int) ($this->request->getGet('per_page') ?: 50),
        ];

        $result = $this->warehouseService->list($criteria, $options);

        return api_success([
            'data'  => array_map(fn ($w) => $w->toApiArray(), $result['data']),
            'total' => $result['total'],
        ]);
    }

    /**
     * GET /api/v1/warehouses/:id
     */
    public function show($id = null): ResponseInterface
    {
        $warehouse = $this->warehouseService->getById((int) $id);
        if ($warehouse === null) {
            return api_error('倉庫不存在', ResponseInterface::HTTP_NOT_FOUND);
        }

        return api_success($warehouse->toApiArray());
    }

    /**
     * POST /api/v1/warehouses
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'name' => 'required|max_length[100]',
            'code' => 'required|max_length[20]',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $warehouse = $this->warehouseService->create($this->jsonBody());

            return api_success($warehouse->toApiArray(), '倉庫建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * PUT /api/v1/warehouses/:id
     */
    public function update($id = null): ResponseInterface
    {
        $rules = [
            'name'      => 'permit_empty|max_length[100]',
            'code'      => 'permit_empty|max_length[20]',
            'location'  => 'permit_empty|max_length[255]',
            'is_active' => 'permit_empty|in_list[0,1,true,false]',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $warehouse = $this->warehouseService->update((int) $id, $this->jsonBody());

            return api_success($warehouse->toApiArray(), '倉庫更新成功');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
