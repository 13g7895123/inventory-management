<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 採購單完整流程 E2E 測試
 *
 * 涵蓋：建立 → 提交 → 核准 → 進貨驗收
 */
class PurchaseOrderFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = 'DatabaseSeeder';

    private string $token       = '';
    private int    $supplierId  = 0;
    private int    $warehouseId = 1;
    private int    $skuId       = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token      = $this->loginAsAdmin();
        $this->supplierId = $this->createSupplier();
        $this->skuId      = $this->createItemWithSku();
    }

    // ── 輔助：登入取得 Token ──────────────────────────────────────────

    private function loginAsAdmin(): string
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', [
                'username' => 'admin',
                'password' => 'Admin@12345',
            ]);

        $body = json_decode($result->getBody(), true);
        return $body['data']['access_token'] ?? '';
    }

    // ── 輔助：直接插入測試資料 ────────────────────────────────────────

    private function createSupplier(): int
    {
        $db = \Config\Database::connect();
        $db->table('suppliers')->insert([
            'name'       => '測試供應商',
            'code'       => 'SUP-TEST',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->insertID();
    }

    private function createItemWithSku(): int
    {
        $db = \Config\Database::connect();

        // 建立 category
        $db->table('categories')->insert([
            'name'       => '測試分類',
            'code'       => 'CAT-TEST',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $categoryId = (int) $db->insertID();

        // 建立 unit
        $db->table('units')->insert([
            'name'       => '個',
            'code'       => 'PCS',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $unitId = (int) $db->insertID();

        // 建立 item
        $db->table('items')->insert([
            'name'        => '測試商品',
            'code'        => 'ITEM-TEST',
            'category_id' => $categoryId,
            'unit_id'     => $unitId,
            'is_active'   => 1,
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        $itemId = (int) $db->insertID();

        // 建立 SKU
        $db->table('item_skus')->insert([
            'item_id'    => $itemId,
            'sku_code'   => 'SKU-TEST-001',
            'is_active'  => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $db->insertID();
    }

    // ── 輔助：建立採購單並回傳 ID ─────────────────────────────────────

    private function createPurchaseOrder(array $extra = []): int
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/purchase-orders', array_merge([
                'supplier_id'  => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'lines'        => [[
                    'sku_id'      => $this->skuId,
                    'ordered_qty' => 10,
                    'unit_price'  => 100.0,
                ]],
            ], $extra));

        $result->assertStatus(201);
        $body = json_decode($result->getBody(), true);
        return (int) ($body['data']['purchase_order']['id'] ?? $body['data']['id'] ?? 0);
    }

    // ── 測試案例 ──────────────────────────────────────────────────────

    public function testCreatePurchaseOrderReturns201(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/purchase-orders', [
                'supplier_id'  => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'lines'        => [[
                    'sku_id'      => $this->skuId,
                    'ordered_qty' => 5,
                    'unit_price'  => 50.0,
                ]],
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['success' => true]);

        $body = json_decode($result->getBody(), true);
        $po   = $body['data']['purchase_order'] ?? $body['data'];
        $this->assertEquals('draft', $po['status']);
        $this->assertNotEmpty($po['po_number']);
    }

    public function testCreateRequiresAuth(): void
    {
        $result = $this->withBodyFormat('json')
            ->post('/api/v1/purchase-orders', [
                'supplier_id'  => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'lines'        => [['sku_id' => $this->skuId, 'ordered_qty' => 1, 'unit_price' => 10]],
            ]);

        $result->assertStatus(401);
    }

    public function testCreateValidationFailsWithNoLines(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/purchase-orders', [
                'supplier_id'  => $this->supplierId,
                'warehouse_id' => $this->warehouseId,
                'lines'        => [],
            ]);

        $result->assertStatus(422);
    }

    public function testSubmitChangesPurchaseOrderStatusToPending(): void
    {
        $poId = $this->createPurchaseOrder();

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/submit");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $po   = $body['data']['purchase_order'] ?? $body['data'];
        $this->assertEquals('pending', $po['status']);
    }

    public function testCannotSubmitAlreadyPendingOrder(): void
    {
        $poId = $this->createPurchaseOrder();

        // 第一次提交
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/submit");

        // 第二次提交應失敗
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/submit");

        $result->assertStatus(422);
    }

    public function testApproveChangesStatusToApproved(): void
    {
        $poId = $this->createPurchaseOrder();

        // submit
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/submit");

        // approve
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/approve");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $po   = $body['data']['purchase_order'] ?? $body['data'];
        $this->assertEquals('approved', $po['status']);
        $this->assertNotNull($po['approved_at']);
    }

    public function testCancelDraftOrder(): void
    {
        $poId = $this->createPurchaseOrder();

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/cancel");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $po   = $body['data']['purchase_order'] ?? $body['data'];
        $this->assertEquals('cancelled', $po['status']);
    }

    public function testGetPurchaseOrderDetail(): void
    {
        $poId = $this->createPurchaseOrder();

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-orders/{$poId}");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $po   = $body['data']['purchase_order'] ?? $body['data'];
        $this->assertEquals($poId, (int) $po['id']);
        $this->assertArrayHasKey('lines', $po);
    }

    public function testGetNonExistentOrderReturns404(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get('/api/v1/purchase-orders/99999');

        $result->assertStatus(404);
    }

    public function testListPurchaseOrdersReturnsPagedResult(): void
    {
        $this->createPurchaseOrder();
        $this->createPurchaseOrder();

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get('/api/v1/purchase-orders');

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $this->assertArrayHasKey('data', $body);
        $this->assertGreaterThanOrEqual(2, count($body['data']));
    }
}
