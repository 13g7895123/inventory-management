<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Warehouse;
use CodeIgniter\Model;

class WarehouseModel extends Model
{
    protected $table          = 'warehouses';
    protected $primaryKey     = 'id';
    protected $returnType     = Warehouse::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'name',
        'code',
        'location',
        'is_active',
        'notes',
    ];
}
