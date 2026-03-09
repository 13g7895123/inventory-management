<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\PurchaseOrder;
use CodeIgniter\Model;

class PurchaseOrderModel extends Model
{
    protected $table          = 'purchase_orders';
    protected $primaryKey     = 'id';
    protected $returnType     = PurchaseOrder::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'po_number',
        'supplier_id',
        'warehouse_id',
        'status',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'expected_date',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
    ];
}
