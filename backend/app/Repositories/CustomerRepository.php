<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Entities\BaseEntity;
use App\Entities\Customer;
use App\Entities\CustomerAddress;
use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Repositories\Contracts\CustomerRepositoryInterface;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    public function __construct(
        CustomerModel                         $model,
        private readonly CustomerAddressModel $addressModel,
    ) {
        parent::__construct($model);
    }

    public function findById(int $id): ?Customer
    {
        return $this->model->find($id);
    }

    public function findAddresses(int $customerId): array
    {
        return $this->addressModel
            ->where('customer_id', $customerId)
            ->orderBy('is_default', 'DESC')
            ->findAll();
    }

    public function findDefaultAddress(int $customerId): ?CustomerAddress
    {
        return $this->addressModel
            ->where('customer_id', $customerId)
            ->where('is_default', 1)
            ->first();
    }

    public function saveAddress(CustomerAddress $address): bool
    {
        return (bool) $this->addressModel->save($address);
    }

    public function deleteAddress(int $addressId): bool
    {
        return (bool) $this->addressModel->delete($addressId);
    }

    public function save(BaseEntity $entity): bool
    {
        return parent::save($entity);
    }
}
