<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Stocktake;
use CodeIgniter\Model;

class StocktakeModel extends Model
{
    protected $table          = 'stocktakes';
    protected $primaryKey     = 'id';
    protected $returnType     = Stocktake::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'stocktake_number',
        'warehouse_id',
        'status',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];
}
