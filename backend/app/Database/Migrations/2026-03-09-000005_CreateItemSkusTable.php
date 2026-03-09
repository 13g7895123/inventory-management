<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 建立 item_skus 資料表（SKU 變體，庫存追蹤的最小單位）
 * 例：商品「T-Shirt」有多個 SKU：(紅色+M)、(藍色+L)
 */
class CreateItemSkusTable extends Migration
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
            'item_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'sku_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'comment'    => 'SKU 代碼（唯一）',
            ],
            'attributes' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => '屬性組合，例：{"color":"red","size":"M"}',
            ],
            'cost_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'comment'    => '成本價',
            ],
            'selling_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'comment'    => '售價',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('sku_code');
        $this->forge->addKey('item_id');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('item_skus');
    }

    public function down(): void
    {
        $this->forge->dropTable('item_skus', true);
    }
}
