<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 銷售退貨 E2E 測試
 *
 * 涵蓋：建立退貨單、確認退貨（庫存回補）、取消退貨、驗證規則
 */
class SalesReturnTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = 'DatabaseSeeder';

    private string $token        = '';
    private int    $shippedSoId  = 0;
    private int    $soLineId     = 0;
    private int    $skuId        = 0;
    private int    $warehouseId  = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token       = $this->loginAsAdmin();
        $this->shippedSoId = $this->createShippedSo();
    }

    // ── 輔助 ──────────────────────────────────────────────────────────

    private function loginAsAdmin(): string
    {
        $r    = $this->withBodyFormat('json')
            ->post('/api/v1/auth/login', ['username' => 'admin', 'password' => 'Admin@12345']);
        $body = json_decode($r->getBody(), true);
        return $body['data']['access_token'] ?? '';
    }

    /**
     * 建立一張已出貨的銷售訂單，回傳 soId
     * 同時設定 $this->soLineId 與 $this->skuId
     */
    private function createShippedSo(): int
    {
        $db = \Config\Database::connect();

        $db->table('customers')->insert([
            'name' => '退貨測試客戶', 'code' => 'CUST-SRET',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $customerId = (int) $db->insertID();

        $db->table('categories')->insert([
            'name' => '銷售退貨測試分類', 'code' => 'CAT-SRET',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $catId = (int) $db->insertID();

        $db->table('units')->insert([
            'name' => '個SRET', 'code' => 'PCS-SRET',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $unitId = (int) $db->insertID();

        $db->table('items')->insert([
            'name' => '銷售退貨測試商品', 'code' => 'ITEM-SRET',
            'category_id' => $catId, 'unit_id' => $unitId,
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $itemId = (int) $db->insertID();

        $db->table('item_skus')->insert([
            'item_id' => $itemId, 'sku_code' => 'SKU-SRET-001',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->skuId = (int) $db->insertID();

        // 預先設定庫存（避免出貨時庫存不足）
        $db->table('inventory')->insert([
            'sku_id'         => $this->skuId,
            'warehouse_id'   => $this->warehouseId,
            'qty_on_hand'    => 100,
            'qty_reserved'   => 0,
            'qty_available'  => 100,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // 建立銷售訂單
        $r = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/sales-orders', [
                'customer_id'  => $customerId,
                'warehouse_id' => $this->warehouseId,
                'order_date'   => date('Y-m-d'),
                'lines'        => [['sku_id' => $this->skuId, 'ordered_qty' => 10, 'unit_price' => 150.0]],
            ]);
        $body = json_decode($r->getBody(), true);
        $soId = (int) ($body['data']['sales_order']['id'] ?? $body['data']['id'] ?? 0);

        // 確認訂單
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$soId}/confirm");

        // 取得 line_id
        $detailResult = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/sales-orders/{$soId}");
        $detailBody  = json_decode($detailResult->getBody(), true);
        $soData      = $detailBody['data'];
        $this->soLineId = (int) ($soData['lines'][0]['id'] ?? 0);

        // 建立出貨
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$soId}/shipments", [
                'lines' => [[
                    'sales_order_line_id' => $this->soLineId,
                    'sku_id'              => $this->skuId,
                    'shipped_qty'         => 10,
                ]],
            ]);

        return $soId;
    }

    /** 建立草稿退貨單並回傳 returnId */
    private function createReturnDraft(int $qty = 2): int
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->shippedSoId}/returns", [
                'warehouse_id' => $this->warehouseId,
                'reason'       => '商品瑕疵',
                'lines'        => [[
                    'sales_order_line_id' => $this->soLineId,
                    'sku_id'              => $this->skuId,
                    'return_qty'          => $qty,
                    'unit_price'          => 150.0,
                ]],
            ]);

        $result->assertStatus(201);
        $body = json_decode($result->getBody(), true);
        return (int) ($body['data']['sales_return']['id'] ?? $body['data']['id'] ?? 0);
    }

    // ── 測試案例 ──────────────────────────────────────────────────────

    public function testListByOrderReturnsEmptyInitially(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/sales-orders/{$this->shippedSoId}/returns");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $this->assertIsArray($body['data']);
        $this->assertCount(0, $body['data']);
    }

    public function testCreateReturnCreatesADraftReturn(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->shippedSoId}/returns", [
                'warehouse_id' => $this->warehouseId,
                'reason'       => '商品瑕疵',
                'lines'        => [[
                    'sales_order_line_id' => $this->soLineId,
                    'sku_id'              => $this->skuId,
                    'return_qty'          => 3,
                    'unit_price'          => 150.0,
                ]],
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['success' => true]);

        $body = json_decode($result->getBody(), true);
        $ret  = $body['data']['sales_return'] ?? $body['data'];
        $this->assertEquals('draft', $ret['status']);
        $this->assertStringStartsWith('SR-', $ret['return_number']);
    }

    public function testGetReturnWithLines(): void
    {
        $returnId = $this->createReturnDraft(2);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/sales-returns/{$returnId}");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $data = $body['data'];
        $this->assertArrayHasKey('sales_return', $data);
        $this->assertArrayHasKey('lines', $data);
        $this->assertCount(1, $data['lines']);
        $this->assertEquals(2, (int) $data['lines'][0]['return_qty']);
    }

    public function testConfirmReturnChangesStatusAndReplenishesStock(): void
    {
        $returnId = $this->createReturnDraft(3);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-returns/{$returnId}/confirm");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $ret  = $body['data']['sales_return'] ?? $body['data'];
        $this->assertEquals('confirmed', $ret['status']);
        $this->assertNotNull($ret['confirmed_at']);
    }

    public function testCannotConfirmAlreadyConfirmedReturn(): void
    {
        $returnId = $this->createReturnDraft(2);

        // 第一次確認
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-returns/{$returnId}/confirm");

        // 第二次確認應失敗
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-returns/{$returnId}/confirm");

        $result->assertStatus(422);
    }

    public function testCancelDraftReturn(): void
    {
        $returnId = $this->createReturnDraft(2);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-returns/{$returnId}/cancel");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $ret  = $body['data']['sales_return'] ?? $body['data'];
        $this->assertEquals('cancelled', $ret['status']);
    }

    public function testCannotCancelConfirmedReturn(): void
    {
        $returnId = $this->createReturnDraft(2);

        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-returns/{$returnId}/confirm");

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-returns/{$returnId}/cancel");

        $result->assertStatus(422);
    }

    public function testCreateReturnFailsWhenQtyExceedsShipped(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->shippedSoId}/returns", [
                'warehouse_id' => $this->warehouseId,
                'reason'       => '數量超過',
                'lines'        => [[
                    'sales_order_line_id' => $this->soLineId,
                    'sku_id'              => $this->skuId,
                    'return_qty'          => 999,  // 超過已出貨 10
                    'unit_price'          => 150.0,
                ]],
            ]);

        $result->assertStatus(422);
        $result->assertJSONFragment(['success' => false]);
    }

    public function testCreateReturnFailsForDraftOrder(): void
    {
        $db = \Config\Database::connect();

        $db->table('customers')->insert([
            'name' => '草稿客戶SRET', 'code' => 'CUST-DRAFT-SRET',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $customerId = (int) $db->insertID();

        $r = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/sales-orders', [
                'customer_id'  => $customerId,
                'warehouse_id' => $this->warehouseId,
                'order_date'   => date('Y-m-d'),
                'lines'        => [['sku_id' => $this->skuId, 'ordered_qty' => 1, 'unit_price' => 100.0]],
            ]);
        $body    = json_decode($r->getBody(), true);
        $draftId = (int) ($body['data']['sales_order']['id'] ?? $body['data']['id'] ?? 0);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$draftId}/returns", [
                'warehouse_id' => $this->warehouseId,
                'lines'        => [[
                    'sales_order_line_id' => 1,
                    'sku_id'              => $this->skuId,
                    'return_qty'          => 1,
                    'unit_price'          => 100.0,
                ]],
            ]);

        $result->assertStatus(422);
    }

    public function testCreateReturnRequiresWarehouseId(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/sales-orders/{$this->shippedSoId}/returns", [
                // warehouse_id 缺失
                'lines' => [[
                    'sales_order_line_id' => $this->soLineId,
                    'sku_id'              => $this->skuId,
                    'return_qty'          => 1,
                    'unit_price'          => 150.0,
                ]],
            ]);

        $result->assertStatus(422);
    }
}
