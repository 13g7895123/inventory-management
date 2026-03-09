<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\StockTransferLine;
use CodeIgniter\Model;

class StockTransferLineModel extends Model
{
    protected $table          = 'stock_transfer_lines';
    protected $primaryKey     = 'id';
    protected $returnType     = StockTransferLine::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'stock_transfer_id',
        'sku_id',
        'qty',
        'batch_number',
        'notes',
    ];
}
