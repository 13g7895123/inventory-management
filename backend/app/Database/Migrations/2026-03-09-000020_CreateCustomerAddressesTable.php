<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 customer_addresses 資料表
 *
 * 客戶多收貨地址，支援一客戶多地址（可設預設地址）
 */
class CreateCustomerAddressesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'customer_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'label' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => false,
                'default'    => '預設地址',
                'comment'    => '地址標籤，如：總部、倉庫',
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
            'address_line1' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
            ],
            'address_line2' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => true,
                'default'    => null,
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'default'    => null,
            ],
            'postal_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'default'    => null,
            ],
            'country' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => false,
                'default'    => 'TW',
            ],
            'is_default' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'comment'    => '是否為預設收貨地址',
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_id');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('customer_addresses');
    }

    public function down(): void
    {
        $this->forge->dropTable('customer_addresses', true);
    }
}
