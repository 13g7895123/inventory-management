<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Items;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\SkuService;
use CodeIgniter\HTTP\ResponseInterface;

class SkuController extends BaseApiController
{
    private SkuService $skuService;

    public function __construct()
    {
        $this->skuService = \Config\Services::skuService();
    }

    /**
     * GET /api/v1/items/:itemId/skus
     */
    public function index($itemId = null): ResponseInterface
    {
        try {
            $skus = $this->skuService->listByItem((int) $itemId);

            return api_success(
                array_map(fn ($s) => $s->toApiArray(), $skus)
            );
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/items/:itemId/skus
     */
    public function create($itemId = null): ResponseInterface
    {
        $rules = [
            'sku_code'      => 'permit_empty|max_length[100]',
            'cost_price'    => 'permit_empty|decimal',
            'selling_price' => 'permit_empty|decimal',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $sku = $this->skuService->create((int) $itemId, $this->jsonBody());

            return api_success($sku->toApiArray(), 'SKU 建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            log_message('error', '[SkuController::create] ' . $e->getMessage());
            return api_error('建立 SKU 失敗：' . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/skus/:id
     */
    public function update($id = null): ResponseInterface
    {
        $rules = [
            'sku_code'      => 'permit_empty|max_length[100]',
            'cost_price'    => 'permit_empty|decimal',
            'selling_price' => 'permit_empty|decimal',
            'is_active'     => 'permit_empty|in_list[0,1,true,false]',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $sku = $this->skuService->update((int) $id, $this->jsonBody());

            return api_success($sku->toApiArray(), 'SKU 更新成功');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * DELETE /api/v1/skus/:id
     */
    public function remove($id = null): ResponseInterface
    {
        try {
            $this->skuService->delete((int) $id);

            return api_success(null, 'SKU 已刪除');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
