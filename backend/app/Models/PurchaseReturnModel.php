<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\PurchaseReturn;
use CodeIgniter\Model;

class PurchaseReturnModel extends Model
{
    protected $table          = 'purchase_returns';
    protected $primaryKey     = 'id';
    protected $returnType     = PurchaseReturn::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'return_number',
        'purchase_order_id',
        'status',
        'reason',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];
}
