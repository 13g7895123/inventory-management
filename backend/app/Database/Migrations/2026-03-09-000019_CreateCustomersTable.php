<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 customers 資料表
 *
 * 客戶主檔：姓名/公司、統一編號、聯絡資訊、信用額度等
 */
class CreateCustomersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'comment'    => '客戶代碼（唯一）',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => '客戶姓名／公司名稱',
            ],
            'tax_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'default'    => null,
                'comment'    => '統一編號（公司戶）',
            ],
            'contact_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'default'    => null,
            ],
            'contact_phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
                'default'    => null,
            ],
            'contact_email' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'default'    => null,
            ],
            'credit_limit' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'default'    => 0,
                'comment'    => '信用額度（0 = 無限制）',
            ],
            'payment_terms' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'default'    => null,
                'comment'    => '付款條件（如：月結30天）',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'is_active' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'null'    => false,
                'default' => 1,
            ],
            'created_by' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('is_active');

        $this->forge->createTable('customers');
    }

    public function down(): void
    {
        $this->forge->dropTable('customers', true);
    }
}
