<?php

declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name'         => 'admin',
                'display_name' => '系統管理員',
                'description'  => '擁有所有功能的最高權限',
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'manager',
                'display_name' => '主管',
                'description'  => '可審核採購單、銷售單，查看所有報表',
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'purchase_staff',
                'display_name' => '採購人員',
                'description'  => '負責採購單建立與進貨驗收',
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'sales_staff',
                'display_name' => '銷售人員',
                'description'  => '負責銷售訂單建立與出貨管理',
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
            [
                'name'         => 'warehouse_staff',
                'display_name' => '倉庫人員',
                'description'  => '負責庫存操作與盤點',
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('roles')->insertBatch($roles);
    }
}
