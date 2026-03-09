<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\SalesPayment;
use CodeIgniter\Model;

class SalesPaymentModel extends Model
{
    protected $table          = 'sales_payments';
    protected $primaryKey     = 'id';
    protected $returnType     = SalesPayment::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'sales_order_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_no',
        'notes',
        'created_by',
    ];
}
