<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 建立 item_barcodes 資料表（條碼，多對一 SKU）
 * 同一 SKU 可有多種條碼（EAN-13、QR Code 等）
 */
class CreateItemBarcodesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'sku_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'barcode' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'barcode_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'EAN13',
                'comment'    => '條碼格式：EAN13、CODE128、QR、UPC 等',
            ],
            'is_primary' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'comment'    => '是否為主要條碼',
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('barcode');
        $this->forge->addKey('sku_id');
        $this->forge->addForeignKey('sku_id', 'item_skus', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('item_barcodes');
    }

    public function down(): void
    {
        $this->forge->dropTable('item_barcodes', true);
    }
}
