<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Master;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\CategoryService;
use CodeIgniter\HTTP\ResponseInterface;

class CategoryController extends BaseApiController
{
    private CategoryService $categoryService;

    public function __construct()
    {
        $this->categoryService = \Config\Services::categoryService();
    }

    /**
     * GET /api/v1/categories
     */
    public function index(): ResponseInterface
    {
        $categories = $this->categoryService->list();

        return api_success(
            array_map(fn ($c) => $c->toApiArray(), $categories)
        );
    }

    /**
     * GET /api/v1/categories/:id
     */
    public function show($id = null): ResponseInterface
    {
        $category = $this->categoryService->getById((int) $id);

        if ($category === null) {
            return api_error('分類不存在', ResponseInterface::HTTP_NOT_FOUND);
        }

        return api_success($category->toApiArray());
    }

    /**
     * POST /api/v1/categories
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'name'      => 'required|max_length[100]',
            'slug'      => 'required|max_length[100]',
            'parent_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $category = $this->categoryService->create($this->jsonBody());

            return api_success($category->toApiArray(), '分類建立成功', ResponseInterface::HTTP_CREATED);
        } catch (\Exception $e) {
            return api_error('建立分類失敗：' . $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/categories/:id
     */
    public function update($id = null): ResponseInterface
    {
        $rules = [
            'name'      => 'permit_empty|max_length[100]',
            'slug'      => 'permit_empty|max_length[100]',
            'parent_id' => 'permit_empty|is_natural_no_zero',
        ];

        if (! $this->validate($rules)) {
            return api_error('請求參數錯誤', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY, $this->validator->getErrors());
        }

        try {
            $category = $this->categoryService->update((int) $id, $this->jsonBody());

            return api_success($category->toApiArray(), '分類更新成功');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * DELETE /api/v1/categories/:id
     */
    public function remove($id = null): ResponseInterface
    {
        try {
            $this->categoryService->delete((int) $id);

            return api_success(null, '分類已刪除');
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
