<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 取得 admin role ID
        $adminRole = $this->db->table('roles')->where('name', 'admin')->get()->getRow();

        if ($adminRole === null) {
            echo "Error: roles table is empty. Run RoleSeeder first.\n";
            return;
        }

        $users = [
            [
                'role_id'    => $adminRole->id,
                'name'       => 'System Administrator',
                'email'      => 'admin@example.com',
                'password'   => password_hash('Admin@12345', PASSWORD_BCRYPT),
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('users')->insertBatch($users);
    }
}
