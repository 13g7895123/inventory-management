<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: 建立 sales_payments 資料表
 *
 * 記錄每筆客戶收款，並自動更新銷售訂單的付款狀態。
 */
class CreateSalesPaymentsTable extends Migration
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
            'sales_order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'comment'    => '對應銷售訂單',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'null'       => false,
                'comment'    => '本次收款金額',
            ],
            'payment_date' => [
                'type'    => 'DATE',
                'null'    => false,
                'comment' => '收款日期',
            ],
            'payment_method' => [
                'type'       => 'ENUM',
                'constraint' => ['bank_transfer', 'cash', 'check', 'credit_card', 'other'],
                'default'    => 'bank_transfer',
                'comment'    => '收款方式',
            ],
            'reference_no' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => '轉帳單號 / 支票號碼 / 刷卡末四碼',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'       => 'INT',
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
        $this->forge->addKey('sales_order_id');
        $this->forge->addKey('payment_date');
        $this->forge->addForeignKey('sales_order_id', 'sales_orders', 'id', 'CASCADE', 'RESTRICT');

        $this->forge->createTable('sales_payments');
    }

    public function down(): void
    {
        $this->forge->dropTable('sales_payments', true);
    }
}
