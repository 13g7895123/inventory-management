<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Unit;
use CodeIgniter\Model;

class UnitModel extends Model
{
    protected $table         = 'units';
    protected $primaryKey    = 'id';
    protected $returnType    = Unit::class;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'name',
        'symbol',
        'description',
        'is_active',
    ];

    protected $validationRules = [
        'name'   => 'required|max_length[50]|is_unique[units.name,id,{id}]',
        'symbol' => 'required|max_length[20]',
    ];
}
