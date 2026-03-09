<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\PurchaseReturnLine;
use CodeIgniter\Model;

class PurchaseReturnLineModel extends Model
{
    protected $table         = 'purchase_return_lines';
    protected $primaryKey    = 'id';
    protected $returnType    = PurchaseReturnLine::class;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'purchase_return_id',
        'purchase_order_line_id',
        'sku_id',
        'return_qty',
        'unit_cost',
        'return_reason',
        'batch_number',
        'notes',
    ];
}
