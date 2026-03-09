<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 shipments 資料表
 *
 * 出貨單主檔（一張銷售單可有多筆出貨，支援分批出貨）
 */
class CreateShipmentsTable extends Migration
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
            'shipment_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'comment'    => '出貨單號，格式：SH-YYYYMMDD-NNNN',
            ],
            'sales_order_id' => [
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
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'shipped', 'cancelled'],
                'null'       => false,
                'default'    => 'pending',
            ],
            'carrier' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
                'default'    => null,
                'comment'    => '貨運業者',
            ],
            'tracking_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
                'comment'    => '追蹤號碼',
            ],
            'shipped_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
                'comment' => '實際出貨時間',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('shipment_number');
        $this->forge->addKey('sales_order_id');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('sales_order_id', 'sales_orders', 'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('shipments');
    }

    public function down(): void
    {
        $this->forge->dropTable('shipments', true);
    }
}
