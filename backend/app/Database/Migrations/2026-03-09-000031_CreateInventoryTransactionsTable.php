<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInventoryTransactionsTable extends Migration
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
            'tx_type' => [
                'type'       => 'ENUM',
                'constraint' => ['DEDUCT', 'REPLENISH', 'ADJUST', 'TRANSFER_IN', 'TRANSFER_OUT'],
                'null'       => false,
                'comment'    => '異動類型',
            ],
            'qty_change' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '異動數量（正=入庫，負=出庫）',
            ],
            'qty_after' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '異動後在庫量',
            ],
            'unit_cost' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => true,
            ],
            'source_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'comment'    => 'sales_order|purchase_order|adjustment|transfer|stocktake',
            ],
            'source_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'operator_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'occurred_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => '0000-00-00 00:00:00',
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => '0000-00-00 00:00:00',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['sku_id', 'warehouse_id']);
        $this->forge->addKey('source_type');
        $this->forge->addKey('occurred_at');

        $this->forge->createTable('inventory_transactions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_transactions', true);
    }
}
