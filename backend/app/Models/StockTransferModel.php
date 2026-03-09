<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\StockTransfer;
use CodeIgniter\Model;

class StockTransferModel extends Model
{
    protected $table          = 'stock_transfers';
    protected $primaryKey     = 'id';
    protected $returnType     = StockTransfer::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'reason',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];
}
