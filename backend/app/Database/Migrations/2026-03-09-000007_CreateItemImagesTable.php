<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 建立 item_images 資料表（商品圖片，支援多圖）
 */
class CreateItemImagesTable extends Migration
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
            'path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'comment'    => '儲存路徑（MinIO/S3 Object Key）',
            ],
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => 1000,
                'null'       => true,
                'comment'    => '公開存取 URL（可為 CDN URL 或 presigned URL）',
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'file_size' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
                'comment'  => '檔案大小（bytes）',
            ],
            'sort_order' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'is_primary' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'comment'    => '是否為主要展示圖',
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('item_id');
        $this->forge->addKey('is_primary');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('item_images');
    }

    public function down(): void
    {
        $this->forge->dropTable('item_images', true);
    }
}
