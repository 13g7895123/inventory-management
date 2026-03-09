<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchaseReturnLinesTable extends Migration
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
            'purchase_return_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'purchase_order_line_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => '對應的採購單明細',
            ],
            'sku_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'return_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '退貨數量',
            ],
            'unit_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => true,
                'comment'    => '退貨單位成本（可能與進貨價不同）',
            ],
            'return_reason' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => '本行退貨原因',
            ],
            'batch_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => '退貨批號（若有批次管理）',
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
        $this->forge->addKey('purchase_return_id');
        $this->forge->addKey('sku_id');
        $this->forge->addForeignKey('purchase_return_id', 'purchase_returns', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('purchase_return_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('purchase_return_lines', true);
    }
}
