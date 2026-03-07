<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Items;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\ItemService;
use CodeIgniter\HTTP\ResponseInterface;

class ItemController extends BaseApiController
{
    public function __construct(private readonly ItemService $itemService)
    {
        // CI4 4.4+ 支援 Controller 建構子注入（透過 new 實例化或 Services）
    }

    /**
     * GET /api/v1/items
     * 商品列表（分頁 + 搜尋）
     */
    public function index(): ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page')     ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $filters = array_filter([
            'keyword'     => $this->request->getGet('keyword'),
            'category_id' => $this->request->getGet('category_id'),
            'is_active'   => $this->request->getGet('is_active'),
        ]);

        $result = $this->itemService->list($filters, $page, $perPage);

        $items = array_map(
            fn ($item) => $item->toApiArray(),
            $result['items']
        );

        return api_paginated($items, $result['total'], $page, $perPage);
    }

    /**
     * GET /api/v1/items/:id
     * 商品詳情
     */
    public function show($id = null): ResponseInterface
    {
        $item = $this->itemService->getById((int) $id);

        if ($item === null) {
            return api_error('商品不存在', ResponseInterface::HTTP_NOT_FOUND);
        }

        return api_success($item->toApiArray());
    }

    /**
     * POST /api/v1/items
     * 新增商品
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'code'        => 'required|max_length[64]',
            'name'        => 'required|max_length[255]',
            'category_id' => 'required|is_natural_no_zero',
            'unit_id'     => 'required|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $item = $this->itemService->create($this->jsonBody());

            return api_success($item->toApiArray(), '商品建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\Exception $e) {
            log_message('error', '[ItemController::create] ' . $e->getMessage());

            return api_error('建立商品失敗：' . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/items/:id
     * 更新商品
     */
    public function update($id = null): ResponseInterface
    {
        $rules = [
            'name'        => 'permit_empty|max_length[255]',
            'category_id' => 'permit_empty|is_natural_no_zero',
            'unit_id'     => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $item = $this->itemService->update((int) $id, $this->jsonBody());

            return api_success($item->toApiArray(), '商品更新成功');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * DELETE /api/v1/items/:id
     * 刪除商品（軟刪除）
     */
    public function remove($id = null): ResponseInterface
    {
        try {
            $this->itemService->delete((int) $id);

            return api_success(null, '商品已刪除');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
