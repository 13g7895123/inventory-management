<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateStockTransferLinesTable extends Migration
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
            'stock_transfer_id' => [
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
            'qty' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '調撥數量',
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
        $this->forge->addKey('stock_transfer_id');
        $this->forge->addForeignKey('stock_transfer_id', 'stock_transfers', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('stock_transfer_lines', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('stock_transfer_lines', true);
    }
}
