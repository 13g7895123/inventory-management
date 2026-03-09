<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\GoodsReceipt;
use CodeIgniter\Model;

class GoodsReceiptModel extends Model
{
    protected $table          = 'goods_receipts';
    protected $primaryKey     = 'id';
    protected $returnType     = GoodsReceipt::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'gr_number',
        'purchase_order_id',
        'warehouse_id',
        'received_by',
        'received_at',
        'notes',
    ];
}
