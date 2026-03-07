<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Inventory;
use CodeIgniter\Model;

class InventoryModel extends Model
{
    protected $table         = 'inventories';
    protected $primaryKey    = 'id';
    protected $returnType    = Inventory::class;
    protected $useSoftDeletes = false;   // 庫存記錄不使用軟刪除
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'sku_id',
        'warehouse_id',
        'on_hand_qty',
        'reserved_qty',
        'on_order_qty',
        'avg_cost',
    ];
}
