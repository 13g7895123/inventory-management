<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\ShipmentLine;
use CodeIgniter\Model;

class ShipmentLineModel extends Model
{
    protected $table         = 'shipment_lines';
    protected $returnType    = ShipmentLine::class;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'shipment_id', 'sales_order_line_id', 'sku_id',
        'shipped_qty', 'batch_number', 'notes',
    ];
}
