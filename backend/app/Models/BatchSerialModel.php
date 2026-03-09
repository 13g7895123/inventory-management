<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\BatchSerial;
use CodeIgniter\Model;

class BatchSerialModel extends Model
{
    protected $table          = 'batch_serials';
    protected $primaryKey     = 'id';
    protected $returnType     = BatchSerial::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'sku_id',
        'warehouse_id',
        'goods_receipt_line_id',
        'type',
        'batch_number',
        'serial_number',
        'quantity',
        'unit_cost',
        'manufactured_date',
        'expiry_date',
        'status',
    ];
}
