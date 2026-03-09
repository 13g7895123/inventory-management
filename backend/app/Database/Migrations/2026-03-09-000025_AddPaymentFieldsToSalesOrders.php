<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 為 sales_orders 新增付款狀態欄位
 *
 * 付款狀態：
 *   unpaid  → paid_amount = 0
 *   partial → 0 < paid_amount < total_amount
 *   paid    → paid_amount >= total_amount
 */
class AddPaymentFieldsToSalesOrders extends Migration
{
    public function up(): void
    {
        $fields = [
            'payment_status' => [
                'type'       => 'ENUM',
                'constraint' => ['unpaid', 'partial', 'paid'],
                'default'    => 'unpaid',
                'null'       => false,
                'after'      => 'total_amount',
                'comment'    => '收款狀態',
            ],
            'paid_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => 0,
                'null'       => false,
                'after'      => 'payment_status',
                'comment'    => '已收金額',
            ],
            'payment_due_date' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'paid_amount',
                'comment' => '帳款到期日',
            ],
        ];

        $this->forge->addColumn('sales_orders', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('sales_orders', ['payment_status', 'paid_amount', 'payment_due_date']);
    }
}
