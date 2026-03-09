<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Customer;
use App\Entities\CustomerAddress;
use App\Repositories\Contracts\CustomerRepositoryInterface;

/**
 * CustomerService — 客戶基礎資料業務邏輯
 */
class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepo,
    ) {
    }

    public function create(array $data, int $createdBy): Customer
    {
        // code 唯一性由 Model validation 處理
        $customer = new Customer();
        $customer->fill(array_merge($data, ['created_by' => $createdBy]));
        $this->customerRepo->save($customer);

        return $this->customerRepo->findById(
            (int) \Config\Database::connect()->insertID()
        );
    }

    public function update(int $id, array $data): Customer
    {
        $customer = $this->customerRepo->findById($id);
        if ($customer === null) {
            throw new \RuntimeException("找不到客戶 #{$id}");
        }
        $customer->fill($data);
        $this->customerRepo->save($customer);
        return $this->customerRepo->findById($id);
    }

    public function getWithAddresses(int $id): array
    {
        $customer = $this->customerRepo->findById($id);
        if ($customer === null) {
            throw new \RuntimeException("找不到客戶 #{$id}");
        }
        return [
            'customer'  => $customer,
            'addresses' => $this->customerRepo->findAddresses($id),
        ];
    }

    public function list(array $criteria = [], array $options = []): array
    {
        $result = $this->customerRepo->findAll($criteria, $options);
        return [
            'data'  => $result['data']  ?? $result,
            'total' => $result['total'] ?? count($result),
        ];
    }

    public function addAddress(int $customerId, array $data): CustomerAddress
    {
        $customer = $this->customerRepo->findById($customerId);
        if ($customer === null) {
            throw new \RuntimeException("找不到客戶 #{$customerId}");
        }

        $db = \Config\Database::connect();

        // 若設為預設，先取消其他預設
        if (!empty($data['is_default'])) {
            $db->table('customer_addresses')
               ->where('customer_id', $customerId)
               ->update(['is_default' => 0]);
        }

        $address = new CustomerAddress();
        $address->fill(array_merge(['customer_id' => $customerId], $data));
        $this->customerRepo->saveAddress($address);

        $addressId = (int) $db->insertID();
        return $db->table('customer_addresses')
            ->where('id', $addressId)
            ->get()
            ->getCustomRowObject(CustomerAddress::class);
    }
}
