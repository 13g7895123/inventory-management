<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\SalesOrderLine;
use CodeIgniter\Model;

class SalesOrderLineModel extends Model
{
    protected $table         = 'sales_order_lines';
    protected $returnType    = SalesOrderLine::class;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'sales_order_id', 'sku_id',
        'ordered_qty', 'shipped_qty',
        'unit_price', 'discount_rate', 'line_total',
        'notes',
    ];
}
