<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\PurchasePayment;
use CodeIgniter\Model;

class PurchasePaymentModel extends Model
{
    protected $table          = 'purchase_payments';
    protected $primaryKey     = 'id';
    protected $returnType     = PurchasePayment::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'purchase_order_id',
        'amount',
        'payment_date',
        'payment_method',
        'reference_no',
        'notes',
        'created_by',
    ];
}
