<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\SalesOrder;
use CodeIgniter\Model;

class SalesOrderModel extends Model
{
    protected $table          = 'sales_orders';
    protected $returnType     = SalesOrder::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'so_number', 'customer_id', 'warehouse_id', 'status',
        'shipping_address_id', 'shipping_name', 'shipping_phone', 'shipping_address',
        'order_date', 'expected_ship_date',
        'tax_rate', 'subtotal', 'tax_amount', 'total_amount', 'discount_amount',
        'is_dropship', 'notes',
        'created_by', 'confirmed_by', 'confirmed_at', 'closed_at',
    ];
}
