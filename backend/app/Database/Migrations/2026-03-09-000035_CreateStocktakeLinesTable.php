<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStocktakeLinesTable extends Migration
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
            'stocktake_id' => [
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
            'system_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '系統在庫量（盤點建立時快照）',
            ],
            'actual_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => true,
                'comment'    => '實際盤點量（null=尚未輸入）',
            ],
            'difference_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => true,
                'comment'    => '差異量 = actual_qty - system_qty',
            ],
            'batch_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'counted_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'comment' => '實盤時間',
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => '0000-00-00 00:00:00',
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => '0000-00-00 00:00:00',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('stocktake_id');
        $this->forge->addKey('sku_id');
        $this->forge->addForeignKey('stocktake_id', 'stocktakes', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('stocktake_lines', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('stocktake_lines', true);
    }
}
