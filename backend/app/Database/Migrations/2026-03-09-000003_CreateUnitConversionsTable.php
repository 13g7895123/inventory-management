<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 建立 unit_conversions 資料表（單位換算比例）
 * 例：1 箱 = 24 個
 */
class CreateUnitConversionsTable extends Migration
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
            'from_unit_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'comment'    => '來源單位（較大）',
            ],
            'to_unit_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'comment'    => '目標單位（較小）',
            ],
            'factor' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,6',
                'comment'    => '換算係數：1 from_unit = factor to_unit',
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['from_unit_id', 'to_unit_id'], 'uq_unit_conversion');
        $this->forge->addForeignKey('from_unit_id', 'units', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('to_unit_id', 'units', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('unit_conversions');
    }

    public function down(): void
    {
        $this->forge->dropTable('unit_conversions', true);
    }
}
