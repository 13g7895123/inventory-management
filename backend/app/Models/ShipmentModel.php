<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Shipment;
use CodeIgniter\Model;

class ShipmentModel extends Model
{
    protected $table          = 'shipments';
    protected $returnType     = Shipment::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'shipment_number', 'sales_order_id', 'warehouse_id', 'status',
        'carrier', 'tracking_number', 'shipped_at', 'notes', 'created_by',
    ];
}
