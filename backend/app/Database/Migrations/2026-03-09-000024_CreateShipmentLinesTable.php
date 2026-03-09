<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 shipment_lines 資料表
 *
 * 出貨單明細：實際出貨的 SKU 與數量
 */
class CreateShipmentLinesTable extends Migration
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
            'shipment_id' => [
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
                'comment'    => '對應的銷售訂單明細 ID',
            ],
            'sku_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'shipped_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '本次出貨數量',
            ],
            'batch_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
                'default'    => null,
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
        $this->forge->addKey('shipment_id');
        $this->forge->addKey('sales_order_line_id');
        $this->forge->addForeignKey('shipment_id', 'shipments', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('shipment_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('shipment_lines', true);
    }
}
