<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\PurchaseOrderLine;
use CodeIgniter\Model;

class PurchaseOrderLineModel extends Model
{
    protected $table         = 'purchase_order_lines';
    protected $primaryKey    = 'id';
    protected $returnType    = PurchaseOrderLine::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'purchase_order_id',
        'sku_id',
        'ordered_qty',
        'received_qty',
        'unit_price',
        'line_total',
        'notes',
    ];
}
