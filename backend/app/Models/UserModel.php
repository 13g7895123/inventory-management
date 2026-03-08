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
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $validationRules = [
        'name'     => 'required|min_length[2]|max_length[100]',
        'email'    => 'required|valid_email|max_length[191]|is_unique[users.email,id,{id}]',
        'password' => 'required|min_length[8]',
        'role_id'  => 'required|integer|is_not_unique[roles.id]',
    ];

    protected $validationMessages = [
        'email' => [
            'is_unique' => '此 Email 已被使用',
        ],
        'role_id' => [
            'is_not_unique' => '指定的角色不存在',
        ],
    ];

    /**
     * 以 Email 查找啟用中的使用者（含 role 資訊）
     */
    public function findActiveByEmail(string $email): ?User
    {
        return $this
            ->where('email', $email)
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
