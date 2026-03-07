<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Item;
use CodeIgniter\Model;

class ItemModel extends Model
{
    protected $table         = 'items';
    protected $primaryKey    = 'id';
    protected $returnType    = Item::class;   // 自動 hydrate Entity
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'category_id',
        'unit_id',
        'code',
        'name',
        'description',
        'reorder_point',
        'safety_stock',
        'lead_time_days',
        'image_path',
        'is_active',
    ];

    protected $validationRules = [
        'code'     => 'required|max_length[64]|is_unique[items.code,id,{id}]',
        'name'     => 'required|max_length[255]',
        'category_id' => 'required|is_natural_no_zero',
        'unit_id'     => 'required|is_natural_no_zero',
    ];

    protected $validationMessages = [
        'code' => [
            'is_unique' => '料號已存在，請重新輸入。',
        ],
    ];
}
