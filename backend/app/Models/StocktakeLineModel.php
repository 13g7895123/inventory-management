<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\StocktakeLine;
use CodeIgniter\Model;

class StocktakeLineModel extends Model
{
    protected $table          = 'stocktake_lines';
    protected $primaryKey     = 'id';
    protected $returnType     = StocktakeLine::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'stocktake_id',
        'sku_id',
        'system_qty',
        'actual_qty',
        'difference_qty',
        'batch_number',
        'notes',
        'counted_at',
    ];
}
