<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\GoodsReceiptLine;
use CodeIgniter\Model;

class GoodsReceiptLineModel extends Model
{
    protected $table          = 'goods_receipt_lines';
    protected $primaryKey     = 'id';
    protected $returnType     = GoodsReceiptLine::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'goods_receipt_id',
        'purchase_order_line_id',
        'sku_id',
        'received_qty',
        'unit_cost',
        'batch_number',
        'expiry_date',
        'notes',
    ];
}
