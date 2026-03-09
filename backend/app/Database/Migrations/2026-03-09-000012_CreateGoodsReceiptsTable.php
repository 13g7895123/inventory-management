<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGoodsReceiptsTable extends Migration
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
            'gr_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'comment'    => '進貨單號，格式 GR-YYYYMMDD-NNNN',
            ],
            'purchase_order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'warehouse_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'received_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => '收貨人員 user_id',
            ],
            'received_at' => [
                'type' => 'DATETIME',
                'null' => false,
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
        $this->forge->addUniqueKey('gr_number');
        $this->forge->addKey('purchase_order_id');
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('received_by',       'users',           'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('goods_receipts');
    }

    public function down(): void
    {
        $this->forge->dropTable('goods_receipts', true);
    }
}
