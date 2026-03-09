<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateGoodsReceiptLinesTable extends Migration
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
            'goods_receipt_id' => [
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
            ],
            'sku_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'received_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
            ],
            'unit_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '本次進貨單價（用於計算加權平均成本）',
            ],
            'batch_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'comment'    => '批號（選填）',
            ],
            'expiry_date' => [
                'type' => 'DATE',
                'null' => true,
                'comment' => '效期（選填）',
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
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
        $this->forge->addKey('goods_receipt_id');
        $this->forge->addKey('sku_id');
        $this->forge->addForeignKey('goods_receipt_id',        'goods_receipts',       'id', 'CASCADE',  'CASCADE');
        $this->forge->addForeignKey('purchase_order_line_id',  'purchase_order_lines', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('sku_id',                  'item_skus',            'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('goods_receipt_lines');
    }

    public function down(): void
    {
        $this->forge->dropTable('goods_receipt_lines', true);
    }
}
