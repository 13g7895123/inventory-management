<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchaseReturnsTable extends Migration
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
            'return_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'comment'    => '退貨單號，格式 PR-YYYYMMDD-NNNN',
            ],
            'purchase_order_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'confirmed', 'cancelled'],
                'default'    => 'draft',
                'comment'    => '退貨單狀態',
            ],
            'reason' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => '退貨原因',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'confirmed_by' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'confirmed_at' => [
                'type' => 'DATETIME',
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('return_number');
        $this->forge->addKey('purchase_order_id');
        $this->forge->addKey(['status', 'deleted_at']);
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('purchase_returns');
    }

    public function down(): void
    {
        $this->forge->dropTable('purchase_returns', true);
    }
}
