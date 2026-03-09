<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\CustomerAddress;
use CodeIgniter\Model;

class CustomerAddressModel extends Model
{
    protected $table         = 'customer_addresses';
    protected $returnType    = CustomerAddress::class;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'customer_id', 'label',
        'contact_name', 'contact_phone',
        'address_line1', 'address_line2',
        'city', 'postal_code', 'country',
        'is_default',
    ];
}
