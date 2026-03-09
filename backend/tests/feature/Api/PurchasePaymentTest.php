<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 供應商付款 E2E 測試
 *
 * 涵蓋：列出付款記錄、新增付款、超付驗證、付款狀態自動更新
 */
class PurchasePaymentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = 'DatabaseSeeder';

    private string $token      = '';
    private int    $approvedPoId = 0;

    /** 採購單的 total_amount（10 qty × 100.00 = 1000.00） */
    private float $totalAmount = 1000.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token        = $this->loginAsAdmin();
        $this->approvedPoId = $this->createApprovedPo();
    }

    // ── 輔助 ──────────────────────────────────────────────────────────

    private function loginAsAdmin(): string
    {
        $r    = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', ['username' => 'admin', 'password' => 'Admin@12345']);
        $body = json_decode($r->getBody(), true);
        return $body['data']['access_token'] ?? '';
    }

    /** 建立並 approve 一張採購單（10 qty × 100.00 = 1000.00） */
    private function createApprovedPo(): int
    {
        $db = \Config\Database::connect();

        $db->table('suppliers')->insert([
            'name' => 'Pay 測試供應商', 'code' => 'SUP-PAY',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $supplierId = (int) $db->insertID();

        $db->table('categories')->insert([
            'name' => '付款測試分類', 'code' => 'CAT-PAY',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $catId = (int) $db->insertID();

        $db->table('units')->insert([
            'name' => '個PAY', 'code' => 'PCS-PAY',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $unitId = (int) $db->insertID();

        $db->table('items')->insert([
            'name' => '付款測試商品', 'code' => 'ITEM-PAY',
            'category_id' => $catId, 'unit_id' => $unitId,
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $itemId = (int) $db->insertID();

        $db->table('item_skus')->insert([
            'item_id' => $itemId, 'sku_code' => 'SKU-PAY-001',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $skuId = (int) $db->insertID();

        // 建立採購單
        $r = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/purchase-orders', [
                'supplier_id'  => $supplierId,
                'warehouse_id' => 1,
                'lines'        => [['sku_id' => $skuId, 'ordered_qty' => 10, 'unit_price' => 100.0]],
            ]);
        $body = json_decode($r->getBody(), true);
        $poId = (int) ($body['data']['purchase_order']['id'] ?? $body['data']['id'] ?? 0);

        // submit → approve
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/submit");

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/approve");

        return $poId;
    }

    // ── 測試案例 ──────────────────────────────────────────────────────

    public function testListPaymentsReturnsEmptyInitially(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-orders/{$this->approvedPoId}/payments");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $this->assertIsArray($body['data']);
        $this->assertCount(0, $body['data']);
    }

    public function testAddPaymentCreatesRecord(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount'         => 300.0,
                'payment_date'   => '2026-04-01',
                'payment_method' => 'bank_transfer',
                'reference_no'   => 'TX-12345',
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['success' => true]);

        $body    = json_decode($result->getBody(), true);
        $payment = $body['data']['payment'] ?? $body['data'];
        $this->assertEquals(300.0, (float) $payment['amount']);
        $this->assertEquals('bank_transfer', $payment['payment_method']);
    }

    public function testAddPaymentUpdatesOrderPaymentStatus(): void
    {
        // 部分付款 → partial
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount' => 500.0, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-orders/{$this->approvedPoId}");

        $body = json_decode($result->getBody(), true);
        $po   = $body['data']['purchase_order'] ?? $body['data'];

        $this->assertEquals('partial', $po['payment_status']);
        $this->assertEquals(500.0, (float) $po['paid_amount']);
    }

    public function testFullPaymentSetsStatusPaid(): void
    {
        // 全額付款
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount' => $this->totalAmount, 'payment_date' => '2026-04-01', 'payment_method' => 'bank_transfer',
            ]);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-orders/{$this->approvedPoId}");

        $body = json_decode($result->getBody(), true);
        $po   = $body['data']['purchase_order'] ?? $body['data'];

        $this->assertEquals('paid', $po['payment_status']);
    }

    public function testAddPaymentFailsWhenAmountExceedsTotal(): void
    {
        $overAmount = $this->totalAmount + 100.0;

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount' => $overAmount, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);

        $result->assertStatus(422);
        $result->assertJSONFragment(['success' => false]);
    }

    public function testAddPaymentFailsWithNegativeAmount(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount' => -50.0, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);

        $result->assertStatus(422);
    }

    public function testAddPaymentFailsWithInvalidMethod(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount' => 100.0, 'payment_date' => '2026-04-01', 'payment_method' => 'bitcoin',
            ]);

        $result->assertStatus(422);
    }

    public function testListPaymentsShowsAddedPayments(): void
    {
        // 新增兩筆付款
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount' => 200.0, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount' => 300.0, 'payment_date' => '2026-04-02', 'payment_method' => 'bank_transfer',
            ]);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-orders/{$this->approvedPoId}/payments");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $this->assertCount(2, $body['data']);
    }

    public function testAddPaymentFailsWithoutAuth(): void
    {
        $result = $this->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->approvedPoId}/payments", [
                'amount' => 100.0, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);

        $result->assertStatus(401);
    }
}
