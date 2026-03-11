<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchasePaymentsTable extends Migration
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
            'purchase_order_id' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '本次付款金額',
            ],
            'payment_date' => [
                'type' => 'DATE',
                'null' => false,
                'comment' => '付款日期',
            ],
            'payment_method' => [
                'type'       => 'ENUM',
                'constraint' => ['bank_transfer', 'cash', 'check', 'other'],
                'default'    => 'bank_transfer',
                'comment'    => '付款方式',
            ],
            'reference_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => '轉帳單號 / 支票號碼',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'           => 'BIGINT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => '建立人員 user_id',
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
        $this->forge->addKey('purchase_order_id');
        $this->forge->addKey('payment_date');
        $this->forge->addForeignKey('purchase_order_id', 'purchase_orders', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('purchase_payments');
    }

    public function down(): void
    {
        $this->forge->dropTable('purchase_payments', true);
    }
}
