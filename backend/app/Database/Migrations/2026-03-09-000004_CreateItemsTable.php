<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 建立 items 資料表（商品主檔）
 */
class CreateItemsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'category_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'unit_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'comment'    => '料號（自訂唯一碼）',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'tax_type' => [
                'type'       => 'ENUM',
                'constraint' => ['taxable', 'zero', 'exempt'],
                'default'    => 'taxable',
            ],
            'reorder_point' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'comment'    => '再訂購點',
            ],
            'safety_stock' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'comment'    => '安全庫存量',
            ],
            'lead_time_days' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
                'comment'    => '前置天數（平均採購到貨天數）',
            ],
            'image_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => '主圖路徑',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addKey('category_id');
        $this->forge->addKey('is_active');
        $this->forge->addKey('name', false, false, 'idx_items_name');
        $this->forge->addForeignKey('category_id', 'categories', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('unit_id', 'units', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('items');
    }

    public function down(): void
    {
        $this->forge->dropTable('items', true);
    }
}
