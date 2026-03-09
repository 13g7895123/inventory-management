<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * 採購退貨 E2E 測試
 *
 * 涵蓋：建立退貨單、確認退貨（庫存扣除）、取消退貨、驗證規則
 */
class PurchaseReturnTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = 'DatabaseSeeder';

    private string $token      = '';
    private int    $receivedPoId  = 0;
    private int    $poLineId      = 0;
    private int    $skuId         = 0;
    private int    $warehouseId   = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token        = $this->loginAsAdmin();
        $this->receivedPoId = $this->createReceivedPo();
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
     * 建立一張完整驗收的採購單，回傳 poId
     * 同時設定 $this->poLineId 與 $this->skuId
     */
    private function createReceivedPo(): int
    {
        $db = \Config\Database::connect();

        $db->table('suppliers')->insert([
            'name' => 'Return 測試供應商', 'code' => 'SUP-RET',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $supplierId = (int) $db->insertID();

        $db->table('categories')->insert([
            'name' => '退貨測試分類', 'code' => 'CAT-RET',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $catId = (int) $db->insertID();

        $db->table('units')->insert([
            'name' => '個RET', 'code' => 'PCS-RET',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $unitId = (int) $db->insertID();

        $db->table('items')->insert([
            'name' => '退貨測試商品', 'code' => 'ITEM-RET',
            'category_id' => $catId, 'unit_id' => $unitId,
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $itemId = (int) $db->insertID();

        $db->table('item_skus')->insert([
            'item_id' => $itemId, 'sku_code' => 'SKU-RET-001',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->skuId = (int) $db->insertID();

        // 建立採購單
        $r = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/purchase-orders', [
                'supplier_id'  => $supplierId,
                'warehouse_id' => $this->warehouseId,
                'lines'        => [['sku_id' => $this->skuId, 'ordered_qty' => 10, 'unit_price' => 50.0]],
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

        // 取得 line_id
        $detailResult = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-orders/{$poId}");
        $detailBody = json_decode($detailResult->getBody(), true);
        $poDetail   = $detailBody['data']['purchase_order'] ?? $detailBody['data'];
        $this->poLineId = (int) ($poDetail['lines'][0]['id'] ?? 0);

        // 全數驗收
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$poId}/receive", [
                'lines' => [[
                    'line_id'      => $this->poLineId,
                    'sku_id'       => $this->skuId,
                    'received_qty' => 10,
                ]],
            ]);

        return $poId;
    }

    /** 建立草稿退貨單並回傳 returnId */
    private function createReturnDraft(int $qty = 2): int
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->receivedPoId}/returns", [
                'reason' => '品質問題',
                'lines'  => [[
                    'purchase_order_line_id' => $this->poLineId,
                    'sku_id'                 => $this->skuId,
                    'return_qty'             => $qty,
                ]],
            ]);

        $result->assertStatus(201);
        $body = json_decode($result->getBody(), true);
        return (int) ($body['data']['purchase_return']['id'] ?? $body['data']['id'] ?? 0);
    }

    // ── 測試案例 ──────────────────────────────────────────────────────

    public function testListByOrderReturnsEmptyInitially(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-orders/{$this->receivedPoId}/returns");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $this->assertIsArray($body['data']);
        $this->assertCount(0, $body['data']);
    }

    public function testCreateReturnCreatesADraftReturn(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->receivedPoId}/returns", [
                'reason' => '品質問題',
                'lines'  => [[
                    'purchase_order_line_id' => $this->poLineId,
                    'sku_id'                 => $this->skuId,
                    'return_qty'             => 3,
                ]],
            ]);

        $result->assertStatus(201);
        $result->assertJSONFragment(['success' => true]);

        $body   = json_decode($result->getBody(), true);
        $ret    = $body['data']['purchase_return'] ?? $body['data'];
        $this->assertEquals('draft', $ret['status']);
        $this->assertStringStartsWith('PR-', $ret['return_number']);
    }

    public function testGetReturnWithLines(): void
    {
        $returnId = $this->createReturnDraft(2);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-returns/{$returnId}");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $data = $body['data'];
        $this->assertArrayHasKey('return', $data);
        $this->assertArrayHasKey('lines', $data);
        $this->assertCount(1, $data['lines']);
        $this->assertEquals(2, (int) $data['lines'][0]['return_qty']);
    }

    public function testConfirmReturnChangesStatusToConfirmed(): void
    {
        $returnId = $this->createReturnDraft(2);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-returns/{$returnId}/confirm");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $ret  = $body['data']['purchase_return'] ?? $body['data'];
        $this->assertEquals('confirmed', $ret['status']);
        $this->assertNotNull($ret['confirmed_at']);
    }

    public function testCannotConfirmAlreadyConfirmedReturn(): void
    {
        $returnId = $this->createReturnDraft(2);

        // 第一次確認
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-returns/{$returnId}/confirm");

        // 第二次確認應失敗
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-returns/{$returnId}/confirm");

        $result->assertStatus(422);
    }

    public function testCancelDraftReturn(): void
    {
        $returnId = $this->createReturnDraft(2);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-returns/{$returnId}/cancel");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $ret  = $body['data']['purchase_return'] ?? $body['data'];
        $this->assertEquals('cancelled', $ret['status']);
    }

    public function testCannotCancelConfirmedReturn(): void
    {
        $returnId = $this->createReturnDraft(2);

        // 確認退貨
        $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-returns/{$returnId}/confirm");

        // 嘗試取消
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-returns/{$returnId}/cancel");

        $result->assertStatus(422);
    }

    public function testCreateReturnFailsWhenReturnQtyExceedsReceived(): void
    {
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->receivedPoId}/returns", [
                'reason' => '數量超過',
                'lines'  => [[
                    'purchase_order_line_id' => $this->poLineId,
                    'sku_id'                 => $this->skuId,
                    'return_qty'             => 999,  // 超過已驗收 10
                ]],
            ]);

        $result->assertStatus(422);
        $result->assertJSONFragment(['success' => false]);
    }

    public function testCreateReturnFailsForDraftPo(): void
    {
        // 建立一批未核准的採購單
        $db = \Config\Database::connect();
        $db->table('suppliers')->insert([
            'name' => 'Draft PO Sup', 'code' => 'SUP-DRAFT2',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $supId = (int) $db->insertID();

        $db->table('categories')->insert([
            'name' => 'Cat Draft2', 'code' => 'CAT-DRAFT2',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $catId = (int) $db->insertID();

        $db->table('units')->insert([
            'name' => 'U Draft2', 'code' => 'PCS-DRAFT2',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $unitId = (int) $db->insertID();

        $db->table('items')->insert([
            'name' => 'Item Draft2', 'code' => 'ITEM-DRAFT2',
            'category_id' => $catId, 'unit_id' => $unitId,
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $itemId = (int) $db->insertID();

        $db->table('item_skus')->insert([
            'item_id' => $itemId, 'sku_code' => 'SKU-DRAFT2',
            'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $skuId2 = (int) $db->insertID();

        $r = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post('/api/v1/purchase-orders', [
                'supplier_id'  => $supId,
                'warehouse_id' => $this->warehouseId,
                'lines'        => [['sku_id' => $skuId2, 'ordered_qty' => 5, 'unit_price' => 10.0]],
            ]);
        $body     = json_decode($r->getBody(), true);
        $draftPoId = (int) ($body['data']['purchase_order']['id'] ?? $body['data']['id'] ?? 0);

        // 嘗試對 draft PO 建立退貨 → 應失敗
        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$draftPoId}/returns", [
                'reason' => '測試',
                'lines'  => [[
                    'purchase_order_line_id' => 1,
                    'sku_id'                 => $skuId2,
                    'return_qty'             => 1,
                ]],
            ]);

        $result->assertStatus(422);
    }

    public function testListByOrderShowsReturnsAfterCreation(): void
    {
        $this->createReturnDraft(3);
        $this->createReturnDraft(2);

        $result = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->get("/api/v1/purchase-orders/{$this->receivedPoId}/returns");

        $result->assertStatus(200);
        $body = json_decode($result->getBody(), true);
        $this->assertCount(2, $body['data']);
    }

    public function testCreateReturnRequiresAuth(): void
    {
        $result = $this->withBodyFormat('json')
            ->post("/api/v1/purchase-orders/{$this->receivedPoId}/returns", [
                'reason' => '測試',
                'lines'  => [[
                    'purchase_order_line_id' => $this->poLineId,
                    'sku_id'                 => $this->skuId,
                    'return_qty'             => 1,
                ]],
            ]);

        $result->assertStatus(401);
    }
}
