<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 sales_return_lines 資料表（退貨明細）
 */
class CreateSalesReturnLinesTable extends Migration
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
            'sales_return_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'sales_order_line_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => '對應的銷售訂單明細',
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
            'unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => true,
                'comment'    => '退貨單價（供退款計算）',
            ],
            'return_reason' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => '本行退貨原因（品質問題、數量錯誤…）',
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
        $this->forge->addKey('sales_return_id');
        $this->forge->addKey('sku_id');
        $this->forge->addForeignKey('sales_return_id', 'sales_returns', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('sales_return_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('sales_return_lines', true);
    }
}
