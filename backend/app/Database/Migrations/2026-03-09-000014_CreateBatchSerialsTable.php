<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 批號 / 序號管理
 *
 * 用於追蹤具有批次（batch）或序號（serial）的庫存品項。
 */
class CreateBatchSerialsTable extends Migration
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
            'sku_id' => [
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
            ],
            'goods_receipt_line_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => '來源進貨明細（可空，表示盤點或手動建立）',
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['batch', 'serial'],
                'default'    => 'batch',
            ],
            'batch_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'serial_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
                'comment'    => '序號（type=serial 時使用）',
            ],
            'quantity' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '此批/序號的數量（序號通常為 1）',
            ],
            'unit_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
            ],
            'manufactured_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'expiry_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['available', 'reserved', 'consumed', 'expired'],
                'default'    => 'available',
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
        $this->forge->addKey(['sku_id', 'warehouse_id', 'status']);
        $this->forge->addKey(['batch_number', 'sku_id']);
        $this->forge->addForeignKey('sku_id',                  'item_skus',           'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('goods_receipt_line_id',   'goods_receipt_lines', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('batch_serials');
    }

    public function down(): void
    {
        $this->forge->dropTable('batch_serials', true);
    }
}
