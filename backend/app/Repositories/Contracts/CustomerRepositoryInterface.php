<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Entities\Customer;
use App\Entities\CustomerAddress;

interface CustomerRepositoryInterface extends RepositoryInterface
{
    public function findById(int $id): ?Customer;

    /**
     * @return CustomerAddress[]
     */
    public function findAddresses(int $customerId): array;

    public function findDefaultAddress(int $customerId): ?CustomerAddress;

    public function saveAddress(CustomerAddress $address): bool;

    public function deleteAddress(int $addressId): bool;
}
