<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 建立 units 資料表（計量單位，如：個、箱、打、公斤）
 */
class CreateUnitsTable extends Migration
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
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'comment'    => '單位名稱（如：個、箱、公斤）',
            ],
            'symbol' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'comment'    => '單位符號（如：pcs、kg、L）',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('units');
    }

    public function down(): void
    {
        $this->forge->dropTable('units', true);
    }
}
