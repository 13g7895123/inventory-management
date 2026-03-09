<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoriesTable extends Migration
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
            'sku_id' => [
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
            'on_hand_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'comment'    => '在庫量',
            ],
            'reserved_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'comment'    => '已預留量（已確認銷售訂單）',
            ],
            'on_order_qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'comment'    => '在途量（採購中）',
            ],
            'avg_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'comment'    => '移動加權平均成本',
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
        $this->forge->addUniqueKey(['sku_id', 'warehouse_id']);
        $this->forge->addKey('warehouse_id');

        $this->forge->createTable('inventory', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory', true);
    }
}
