<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchaseOrdersTable extends Migration
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
            'po_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'comment'    => '採購單號，格式 PO-YYYYMMDD-NNNN',
            ],
            'supplier_id' => [
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
                'comment'    => '預計到貨倉庫',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['draft', 'pending', 'approved', 'partial', 'received', 'cancelled'],
                'default'    => 'draft',
            ],
            'subtotal' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
            ],
            'tax_rate' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,4',
                'default'    => '0.0500',
                'comment'    => '稅率，預設 5%',
            ],
            'tax_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
            ],
            'total_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
            ],
            'expected_date' => [
                'type' => 'DATE',
                'null' => true,
                'comment' => '預計到貨日',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'approved_by' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'approved_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_by' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('po_number');
        $this->forge->addKey(['status', 'deleted_at']);
        $this->forge->addKey(['supplier_id', 'status']);
        $this->forge->addForeignKey('supplier_id', 'suppliers', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('approved_by', 'users',     'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('created_by',  'users',     'id', 'RESTRICT', 'CASCADE');

        $this->forge->createTable('purchase_orders');
    }

    public function down(): void
    {
        $this->forge->dropTable('purchase_orders', true);
    }
}
