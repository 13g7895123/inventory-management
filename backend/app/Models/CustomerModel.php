<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Customer;
use CodeIgniter\Model;

class CustomerModel extends Model
{
    protected $table         = 'customers';
    protected $returnType    = Customer::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'code', 'name', 'tax_id',
        'contact_name', 'contact_phone', 'contact_email',
        'credit_limit', 'payment_terms', 'notes',
        'is_active', 'created_by',
    ];

    protected $validationRules = [
        'code' => 'required|max_length[32]|is_unique[customers.code,id,{id}]',
        'name' => 'required|max_length[100]',
    ];
}
