<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 sales_orders 資料表
 *
 * 銷售訂單主檔：
 *   draft → confirmed（庫存預留）→ partial / shipped
 *   draft / confirmed → cancelled（庫存釋放）
 */
class CreateSalesOrdersTable extends Migration
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
            'so_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'comment'    => '銷售單號，格式：SO-YYYYMMDD-NNNN',
            ],
            'customer_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'warehouse_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => '出貨倉庫',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'confirmed', 'partial', 'shipped', 'cancelled'],
                'null'       => false,
                'default'    => 'draft',
            ],
            'shipping_address_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'comment'    => '使用 customer_addresses.id；null 表示自訂地址',
            ],
            'shipping_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
            'shipping_phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
                'default'    => null,
            ],
            'shipping_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
            ],
            'order_date' => [
                'type' => 'DATE',
                'null' => true,
                'default' => null,
                'comment' => '訂單日期（不一定是建立日）',
            ],
            'expected_ship_date' => [
                'type' => 'DATE',
                'null' => true,
                'default' => null,
            ],
            'tax_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => false,
                'default'    => 5.00,
                'comment'    => '稅率百分比',
            ],
            'subtotal' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'default'    => 0,
            ],
            'tax_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'default'    => 0,
            ],
            'total_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'default'    => 0,
            ],
            'discount_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'default'    => 0,
                'comment'    => '整單折扣',
            ],
            'is_dropship' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
                'comment'    => '是否為代發訂單',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'created_by' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'confirmed_by' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'confirmed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'closed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
                'comment' => '全部出貨完成時間',
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
        $this->forge->addUniqueKey('so_number');
        $this->forge->addKey('customer_id');
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('customer_id', 'customers', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('sales_orders');
    }

    public function down(): void
    {
        $this->forge->dropTable('sales_orders', true);
    }
}
