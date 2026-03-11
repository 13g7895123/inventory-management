<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Master;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\UnitService;
use CodeIgniter\HTTP\ResponseInterface;

class UnitController extends BaseApiController
{
    private UnitService $unitService;

    public function __construct()
    {
        $this->unitService = \Config\Services::unitService();
    }

    /**
     * GET /api/v1/units
     */
    public function index(): ResponseInterface
    {
        $units = $this->unitService->list();

        return api_success(
            array_map(fn ($u) => $u->toApiArray(), $units)
        );
    }

    /**
     * GET /api/v1/units/:id
     */
    public function show($id = null): ResponseInterface
    {
        $unit = $this->unitService->getById((int) $id);

        if ($unit === null) {
            return api_error('計量單位不存在', ResponseInterface::HTTP_NOT_FOUND);
        }

        return api_success($unit->toApiArray());
    }

    /**
     * POST /api/v1/units
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'name'   => 'required|max_length[50]',
            'symbol' => 'required|max_length[20]',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $unit = $this->unitService->create($this->jsonBody());

            return api_success($unit->toApiArray(), '計量單位建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\Exception $e) {
            return api_error('建立計量單位失敗：' . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/units/:id
     */
    public function update($id = null): ResponseInterface
    {
        $rules = [
            'name'   => 'permit_empty|max_length[50]',
            'symbol' => 'permit_empty|max_length[20]',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $unit = $this->unitService->update((int) $id, $this->jsonBody());

            return api_success($unit->toApiArray(), '計量單位更新成功');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * DELETE /api/v1/units/:id
     */
    public function remove($id = null): ResponseInterface
    {
        try {
            $this->unitService->delete((int) $id);

            return api_success(null, '計量單位已刪除');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
