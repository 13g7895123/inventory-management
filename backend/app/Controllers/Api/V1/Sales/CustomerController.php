<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Sales;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\CustomerService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CustomerController — 客戶管理
 *
 * Routes:
 *   GET    /api/v1/customers
 *   POST   /api/v1/customers
 *   GET    /api/v1/customers/:id
 *   PUT    /api/v1/customers/:id
 *   GET    /api/v1/customers/:id/addresses
 *   POST   /api/v1/customers/:id/addresses
 */
class CustomerController extends BaseApiController
{
    private CustomerService $customerService;

    public function __construct()
    {
        $this->customerService = \Config\Services::customerService();
    }

    /**
     * GET /api/v1/customers
     */
    public function index(): ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $criteria = [];
        if ($keyword = $this->request->getGet('keyword')) {
            $criteria['name'] = ['LIKE', "%{$keyword}%"];
        }
        if ($this->request->getGet('is_active') !== null) {
            $criteria['is_active'] = (int) $this->request->getGet('is_active');
        }

        $result = $this->customerService->list($criteria, [
            'page'     => $page,
            'per_page' => $perPage,
            'sort'     => $this->request->getGet('sort') ?? 'id',
            'order'    => $this->request->getGet('order') ?? 'asc',
        ]);

        return api_paginated(
            array_map(fn ($c) => $c->toArray(), $result['data']),
            $result['total'],
            $page,
            $perPage,
        );
    }

    /**
     * GET /api/v1/customers/:id
     */
    public function show($id = null): ResponseInterface
    {
        try {
            $data = $this->customerService->getWithAddresses((int) $id);
            return api_success([
                'customer'  => $data['customer']->toArray(),
                'addresses' => array_map(fn ($a) => $a->toArray(), $data['addresses']),
            ]);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/customers
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'code'          => 'required|max_length[32]',
            'name'          => 'required|max_length[100]',
            'contact_email' => 'permit_empty|valid_email',
            'credit_limit'  => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];

        $body = $this->jsonBody();
        if (!$this->validate($rules)) {
            return api_error($this->validator->getErrors(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $customer = $this->customerService->create($body, $this->currentUserId());
            return api_success(['customer' => $customer->toArray()], ResponseInterface::HTTP_CREATED);
        } catch (\DomainException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    /**
     * PUT /api/v1/customers/:id
     */
    public function update($id = null): ResponseInterface
    {
        $rules = [
            'name'          => 'permit_empty|max_length[100]',
            'contact_email' => 'permit_empty|valid_email',
            'credit_limit'  => 'permit_empty|decimal|greater_than_equal_to[0]',
        ];

        $body = $this->jsonBody();
        if (!$this->validate($rules)) {
            return api_error($this->validator->getErrors(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $customer = $this->customerService->update((int) $id, $body);
            return api_success(['customer' => $customer->toArray()]);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * GET /api/v1/customers/:id/addresses
     */
    public function listAddresses($id = null): ResponseInterface
    {
        try {
            $data = $this->customerService->getWithAddresses((int) $id);
            return api_success(array_map(fn ($a) => $a->toArray(), $data['addresses']));
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }

    /**
     * POST /api/v1/customers/:id/addresses
     */
    public function addAddress($id = null): ResponseInterface
    {
        $rules = [
            'address_line1' => 'required|max_length[200]',
            'label'         => 'permit_empty|max_length[60]',
        ];

        $body = $this->jsonBody();
        if (!$this->validate($rules)) {
            return api_error($this->validator->getErrors(), ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $address = $this->customerService->addAddress((int) $id, $body);
            return api_success(['address' => $address->toArray()], ResponseInterface::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return api_error($e->getMessage(), ResponseInterface::HTTP_NOT_FOUND);
        }
    }
}
