<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Category;
use CodeIgniter\Model;

class CategoryModel extends Model
{
    protected $table          = 'categories';
    protected $primaryKey     = 'id';
    protected $returnType     = Category::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'parent_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $validationRules = [
        'name' => 'required|max_length[100]',
        'slug' => 'required|max_length[100]|is_unique[categories.slug,id,{id}]',
    ];

    protected $validationMessages = [
        'slug' => [
            'is_unique' => '分類網址代碼已存在。',
        ],
    ];

    /**
     * 取得樹狀分類（self-join，扁平陣列帶 depth）
     */
    public function getTree(): array
    {
        return $this->select('c.*, p.name as parent_name')
            ->from('categories c')
            ->join('categories p', 'p.id = c.parent_id', 'left')
            ->where('c.deleted_at IS NULL')
            ->orderBy('c.parent_id ASC, c.sort_order ASC, c.name ASC')
            ->findAll();
    }
}
