<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 sales_order_lines 資料表
 *
 * 銷售訂單明細：每行對應一個 SKU、數量、售價
 */
class CreateSalesOrderLinesTable extends Migration
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
            'sales_order_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'sku_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'ordered_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '訂購數量',
            ],
            'shipped_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'default'    => 0,
                'comment'    => '已出貨數量',
            ],
            'unit_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '銷售單價',
            ],
            'discount_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => false,
                'default'    => 0.00,
                'comment'    => '行折扣率 %（0–100）',
            ],
            'line_total' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'default'    => 0,
                'comment'    => '行小計（折扣後）',
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
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
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('sales_order_id');
        $this->forge->addKey('sku_id');
        $this->forge->addForeignKey('sales_order_id', 'sales_orders', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('sales_order_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('sales_order_lines', true);
    }
}
