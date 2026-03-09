<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 供應商商品目錄（記錄哪個供應商可供應哪些 SKU，以及報價資訊）
 */
class CreateSupplierItemsTable extends Migration
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
            'supplier_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'sku_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'supplier_sku_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'comment'    => '供應商自己的料號',
            ],
            'unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'default'    => '0.0000',
                'comment'    => '採購單價',
            ],
            'min_order_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '1.0000',
                'comment'    => '最低訂購量',
            ],
            'lead_time_days' => [
                'type'       => 'TINYINT',
                'constraint' => 3,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => '此料品前置天數（覆蓋供應商預設值）',
            ],
            'is_preferred' => [
                'type'    => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'comment' => '是否為首選供應商',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['supplier_id', 'sku_id']);
        $this->forge->addKey('sku_id');
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sku_id',      'item_skus', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('supplier_items');
    }

    public function down(): void
    {
        $this->forge->dropTable('supplier_items', true);
    }
}
