<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\SalesReturn;
use CodeIgniter\Model;

class SalesReturnModel extends Model
{
    protected $table          = 'sales_returns';
    protected $primaryKey     = 'id';
    protected $returnType     = SalesReturn::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'return_number',
        'sales_order_id',
        'warehouse_id',
        'status',
        'reason',
        'refund_amount',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];
}
