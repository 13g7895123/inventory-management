<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\ItemSku;
use CodeIgniter\Model;

class ItemSkuModel extends Model
{
    protected $table          = 'item_skus';
    protected $primaryKey     = 'id';
    protected $returnType     = ItemSku::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'item_id',
        'sku_code',
        'attributes',
        'cost_price',
        'selling_price',
        'is_active',
    ];

    protected $validationRules = [
        'item_id'  => 'required|is_natural_no_zero',
        'sku_code' => 'required|max_length[100]|is_unique[item_skus.sku_code,id,{id}]',
    ];

    protected $validationMessages = [
        'sku_code' => [
            'is_unique' => 'SKU 代碼已存在。',
        ],
    ];
}
