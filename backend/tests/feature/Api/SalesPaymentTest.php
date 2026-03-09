<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 銷售收款 E2E 測試
 *
 * 涵蓋：列出收款記錄、新增收款、收款狀態自動更新、超收驗證
 */
class SalesPaymentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = 'DatabaseSeeder';

    private string $token         = '';
    private int    $confirmedSoId = 0;
    private int    $skuId         = 0;

    /** 銷售訂單的 total_amount（5 qty × 200.00 = 1000.00） */
    private float $totalAmount = 1000.0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token         = $this->loginAsAdmin();
        $this->confirmedSoId = $this->createConfirmedSo();
    }

    // ── 輔助 ──────────────────────────────────────────────────────────

    private function loginAsAdmin(): string
    {
        $r    = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', ['username' => 'admin', 'password' => 'Admin@12345']);
        $body = json_decode($r->getBody(), true);
        return $body['data']['access_token'] ?? '';
    }

    /** 建立並確認一張銷售訂單（5 qty × 200.00 = 1000.00） */
    private function createConfirmedSo(): int
    {
        $db = \Config\Database::connect();

        $db->table('customers')->insert([
            'name' => '收款測試客戶', 'code' => 'CUST-PAY',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $customerId = (int) $db->insertID();

        $db->table('categories')->insert([
            'name' => '收款測試分類', 'code' => 'CAT-SPAY',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $catId = (int) $db->insertID();

        $db->table('units')->insert([
            'name' => '個SPAY', 'code' => 'PCS-SPAY',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $unitId = (int) $db->insertID();

        $db->table('items')->insert([
            'name' => '收款測試商品', 'code' => 'ITEM-SPAY',
            'category_id' => $catId, 'unit_id' => $unitId,
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $itemId = (int) $db->insertID();

        $db->table('item_skus')->insert([
            'item_id' => $itemId, 'sku_code' => 'SKU-SPAY-001',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->skuId = (int) $db->insertID();

        // 建立銷售訂單
        $r = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/sales-orders', [
                'customer_id'  => $customerId,
                'warehouse_id' => 1,
                'order_date'   => date('Y-m-d'),
                'lines'        => [['sku_id' => $this->skuId, 'ordered_qty' => 5, 'unit_price' => 200.0]],
            ]);
        $body = json_decode($r->getBody(), true);
        $soId = (int) ($body['data']['sales_order']['id'] ?? $body['data']['id'] ?? 0);

        // 確認銷售訂單
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$soId}/confirm");

        return $soId;
    }

    // ── 測試案例 ──────────────────────────────────────────────────────

    public function testListPaymentsReturnsEmptyInitially(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/sales-orders/{$this->confirmedSoId}/payments");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $this->assertIsArray($body['data']);
        $this->assertCount(0, $body['data']);
    }

    public function testAddPaymentCreatesRecord(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->confirmedSoId}/payments", [
                'amount'         => 300.0,
                'payment_date'   => '2026-04-01',
                'payment_method' => 'bank_transfer',
                'reference_no'   => 'TX-SO-12345',
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['success' => true]);

        $body    = json_decode($result->getBody(), true);
        $payment = $body['data']['payment'] ?? $body['data'];
        $this->assertEquals(300.0, (float) $payment['amount']);
        $this->assertEquals('bank_transfer', $payment['payment_method']);
    }

    public function testPartialPaymentSetsStatusPartial(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->confirmedSoId}/payments", [
                'amount' => 500.0, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/sales-orders/{$this->confirmedSoId}");

        $body = json_decode($result->getBody(), true);
        $so   = $body['data']['sales_order'] ?? $body['data'];

        $this->assertEquals('partial', $so['payment_status']);
        $this->assertEquals(500.0, (float) $so['paid_amount']);
    }

    public function testFullPaymentSetsStatusPaid(): void
    {
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->confirmedSoId}/payments", [
                'amount' => $this->totalAmount, 'payment_date' => '2026-04-01', 'payment_method' => 'bank_transfer',
            ]);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/sales-orders/{$this->confirmedSoId}");

        $body = json_decode($result->getBody(), true);
        $so   = $body['data']['sales_order'] ?? $body['data'];

        $this->assertEquals('paid', $so['payment_status']);
        $this->assertEquals($this->totalAmount, (float) $so['paid_amount']);
    }

    public function testAddPaymentFailsWhenAmountExceedsTotal(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->confirmedSoId}/payments", [
                'amount' => $this->totalAmount + 100.0,
                'payment_date'   => '2026-04-01',
                'payment_method' => 'cash',
            ]);

        $result->assertStatus(422);
        $result->assertJSONFragment(['success' => false]);
    }

    public function testAddPaymentFailsWithNegativeAmount(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->confirmedSoId}/payments", [
                'amount' => -50.0, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);

        $result->assertStatus(422);
    }

    public function testAddPaymentFailsWithInvalidMethod(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->confirmedSoId}/payments", [
                'amount' => 100.0, 'payment_date' => '2026-04-01', 'payment_method' => 'bitcoin',
            ]);

        $result->assertStatus(422);
    }

    public function testAddPaymentFailsForDraftOrder(): void
    {
        $db = \Config\Database::connect();

        $db->table('customers')->insert([
            'name' => '草稿客戶SPAY', 'code' => 'CUST-DRAFT-SPAY',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $customerId = (int) $db->insertID();

        $r = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/sales-orders', [
                'customer_id'  => $customerId,
                'warehouse_id' => 1,
                'order_date'   => date('Y-m-d'),
                'lines'        => [['sku_id' => $this->skuId, 'ordered_qty' => 1, 'unit_price' => 100.0]],
            ]);
        $body   = json_decode($r->getBody(), true);
        $draftId = (int) ($body['data']['sales_order']['id'] ?? $body['data']['id'] ?? 0);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$draftId}/payments", [
                'amount' => 100.0, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);

        $result->assertStatus(422);
    }

    public function testMultiplePaymentsAccumulate(): void
    {
        // 兩筆各 300，合計 600
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->confirmedSoId}/payments", [
                'amount' => 300.0, 'payment_date' => '2026-04-01', 'payment_method' => 'cash',
            ]);
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->confirmedSoId}/payments", [
                'amount' => 300.0, 'payment_date' => '2026-04-02', 'payment_method' => 'bank_transfer',
            ]);

        $listResult = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/sales-orders/{$this->confirmedSoId}/payments");

        $body = json_decode($listResult->getBody(), true);
        $this->assertCount(2, $body['data']);

        $soResult = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/sales-orders/{$this->confirmedSoId}");
        $soBody = json_decode($soResult->getBody(), true);
        $so     = $soBody['data']['sales_order'] ?? $soBody['data'];
        $this->assertEquals(600.0, (float) $so['paid_amount']);
        $this->assertEquals('partial', $so['payment_status']);
    }
}
