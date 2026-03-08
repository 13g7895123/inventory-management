<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\User;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = User::class;
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';

    protected $allowedFields = [
        'role_id',
        'username',
        'name',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $validationRules = [
        'username' => 'required|alpha_numeric_punct|min_length[3]|max_length[100]|is_unique[users.username,id,{id}]',
        'name'     => 'required|min_length[2]|max_length[100]',
        'password' => 'required|min_length[8]',
        'role_id'  => 'required|integer|is_not_unique[roles.id]',
    ];

    protected $validationMessages = [
        'username' => [
            'is_unique' => '此帳號已被使用',
        ],
        'role_id' => [
            'is_not_unique' => '指定的角色不存在',
        ],
    ];

    /**
     * 以帳號查找啟用中的使用者
     */
    public function findActiveByUsername(string $username): ?User
    {
        return $this
            ->where('username', $username)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * 更新最後登入時間
     */
    public function touchLastLogin(int $userId): void
    {
        $this->set('last_login_at', date('Y-m-d H:i:s'))
             ->where('id', $userId)
             ->update();
    }
}
