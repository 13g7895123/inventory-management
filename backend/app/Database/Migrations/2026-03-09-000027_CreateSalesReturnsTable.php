<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 sales_returns 資料表（銷售退貨單主檔）
 *
 * 退貨流程：
 *   draft → confirmed（庫存入庫）→ done
 *   draft → cancelled
 */
class CreateSalesReturnsTable extends Migration
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
                'comment'    => '退貨單號，格式：SR-YYYYMMDD-NNNN',
            ],
            'sales_order_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => '對應銷售訂單',
            ],
            'warehouse_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => '退貨入庫倉庫',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'confirmed', 'cancelled'],
                'null'       => false,
                'default'    => 'draft',
                'comment'    => '草稿、已確認（庫存入庫）、已取消',
            ],
            'reason' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => '退貨原因',
            ],
            'refund_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => 0,
                'null'       => false,
                'comment'    => '退款金額',
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
        $this->forge->addKey('sales_order_id');
        $this->forge->addKey(['status', 'deleted_at']);
        $this->forge->addForeignKey('sales_order_id', 'sales_orders', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('sales_returns');
    }

    public function down(): void
    {
        $this->forge->dropTable('sales_returns', true);
    }
}
