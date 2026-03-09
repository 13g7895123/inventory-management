<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\SalesReturnLine;
use CodeIgniter\Model;

class SalesReturnLineModel extends Model
{
    protected $table          = 'sales_return_lines';
    protected $primaryKey     = 'id';
    protected $returnType     = SalesReturnLine::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'sales_return_id',
        'sales_order_line_id',
        'sku_id',
        'return_qty',
        'unit_price',
        'return_reason',
        'batch_number',
        'notes',
    ];
}
