<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Supplier;
use CodeIgniter\Model;

class SupplierModel extends Model
{
    protected $table          = 'suppliers';
    protected $primaryKey     = 'id';
    protected $returnType     = Supplier::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'code',
        'name',
        'contact_name',
        'contact_phone',
        'contact_email',
        'address',
        'tax_id',
        'payment_terms',
        'lead_time_days',
        'is_active',
        'notes',
    ];

    protected $validationRules = [
        'code' => 'required|max_length[32]|is_unique[suppliers.code,id,{id}]',
        'name' => 'required|max_length[255]',
    ];

    protected $validationMessages = [
        'code' => [
            'is_unique' => '供應商代碼已存在。',
        ],
    ];
}
