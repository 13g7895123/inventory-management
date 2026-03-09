<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentFieldsToPurchaseOrders extends Migration
{
    public function up(): void
    {
        $fields = [
            'payment_status' => [
                'type'       => 'ENUM',
                'constraint' => ['unpaid', 'partial', 'paid'],
                'default'    => 'unpaid',
                'after'      => 'total_amount',
                'comment'    => '付款狀態',
            ],
            'paid_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '15,4',
                'default'    => '0.0000',
                'after'      => 'payment_status',
                'comment'    => '已付金額',
            ],
            'payment_due_date' => [
                'type'    => 'DATE',
                'null'    => true,
                'default' => null,
                'after'   => 'paid_amount',
                'comment' => '付款截止日',
            ],
        ];

        $this->forge->addColumn('purchase_orders', $fields);
    }

    public function down(): void
    {
        $this->forge->dropColumn('purchase_orders', ['payment_status', 'paid_amount', 'payment_due_date']);
    }
}
