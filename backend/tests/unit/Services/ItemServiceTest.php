<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\Item;
use App\Entities\ItemSku;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Repositories\Contracts\SkuRepositoryInterface;
use App\Services\ItemService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * ItemService 單元測試
 *
 * 使用 Mock Repository 隔離 DB，只測試業務邏輯。
 */
class ItemServiceTest extends CIUnitTestCase
{
    private ItemService $itemService;

    /** @var ItemRepositoryInterface&MockObject */
    private ItemRepositoryInterface $itemRepo;

    /** @var SkuRepositoryInterface&MockObject */
    private SkuRepositoryInterface $skuRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemRepo = $this->createMock(ItemRepositoryInterface::class);
        $this->skuRepo  = $this->createMock(SkuRepositoryInterface::class);

        $this->itemService = new ItemService($this->itemRepo, $this->skuRepo);
    }

    // ──────────────────────────────────────────────
    // list()
    // ──────────────────────────────────────────────

    public function testListReturnsPaginatedItems(): void
    {
        $items = [new Item(['id' => 1, 'name' => 'A']), new Item(['id' => 2, 'name' => 'B'])];

        $this->itemRepo
            ->expects($this->once())
            ->method('findAll')
            ->willReturn(['data' => $items, 'total' => 2]);

        $result = $this->itemService->list([], 1, 20);

        $this->assertCount(2, $result['items']);
        $this->assertSame(2, $result['total']);
    }

    public function testListWithKeywordFilter(): void
    {
        $this->itemRepo
            ->expects($this->once())
            ->method('findAll')
            ->with($this->callback(function (array $criteria) {
                return isset($criteria['name']) && $criteria['name'] === ['LIKE', '%螺絲%'];
            }))
            ->willReturn(['data' => [], 'total' => 0]);

        $result = $this->itemService->list(['keyword' => '螺絲'], 1, 20);

        $this->assertEmpty($result['items']);
        $this->assertSame(0, $result['total']);
    }

    // ──────────────────────────────────────────────
    // getById()
    // ──────────────────────────────────────────────

    public function testGetByIdReturnsItem(): void
    {
        $item = new Item(['id' => 1, 'name' => '螺絲 M3']);

        $this->itemRepo
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($item);

        $result = $this->itemService->getById(1);

        $this->assertSame($item, $result);
    }

    public function testGetByIdReturnsNullWhenNotFound(): void
    {
        $this->itemRepo
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->assertNull($this->itemService->getById(999));
    }

    // ──────────────────────────────────────────────
    // create()
    // ──────────────────────────────────────────────

    public function testCreateItemWithDefaultSku(): void
    {
        $data = [
            'code'        => 'ITEM-001',
            'name'        => '測試商品',
            'category_id' => 1,
            'unit_id'     => 1,
        ];

        $savedItem = new Item(array_merge($data, ['id' => 10]));

        $this->itemRepo
            ->expects($this->once())
            ->method('save');

        $this->itemRepo
            ->expects($this->once())
            ->method('findById')
            ->willReturn($savedItem);

        // 未傳入 skus，應建立預設 SKU
        $this->skuRepo
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (ItemSku $sku) {
                return $sku->sku_code === 'ITEM-001';
            }));

        $result = $this->itemService->create($data);

        $this->assertSame($savedItem, $result);
    }

    public function testCreateItemWithMultipleSkus(): void
    {
        $data = [
            'code'        => 'ITEM-002',
            'name'        => 'T-Shirt',
            'category_id' => 1,
            'unit_id'     => 1,
            'skus'        => [
                ['sku_code' => 'ITEM-002-RED-M', 'attributes' => ['color' => 'red', 'size' => 'M']],
                ['sku_code' => 'ITEM-002-BLUE-L', 'attributes' => ['color' => 'blue', 'size' => 'L']],
            ],
        ];

        $savedItem = new Item(['id' => 11, 'code' => 'ITEM-002', 'name' => 'T-Shirt']);

        $this->itemRepo->method('save');
        $this->itemRepo->method('findById')->willReturn($savedItem);

        // 傳入 2 個 SKU，應建立 2 個 SKU（不建立預設）
        $this->skuRepo
            ->expects($this->exactly(2))
            ->method('save');

        $result = $this->itemService->create($data);

        $this->assertSame($savedItem, $result);
    }

    // ──────────────────────────────────────────────
    // update()
    // ──────────────────────────────────────────────

    public function testUpdateSucceeds(): void
    {
        $item = new Item(['id' => 1, 'name' => '舊名稱', 'code' => 'OLD']);

        $this->itemRepo
            ->expects($this->exactly(2))
            ->method('findById')
            ->with(1)
            ->willReturn($item);

        $this->itemRepo
            ->expects($this->once())
            ->method('save');

        $result = $this->itemService->update(1, ['name' => '新名稱']);

        $this->assertNotNull($result);
    }

    public function testUpdateThrowsWhenNotFound(): void
    {
        $this->itemRepo
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('商品 #999 不存在');

        $this->itemService->update(999, ['name' => '新名稱']);
    }

    public function testUpdateIgnoresSkusField(): void
    {
        $item = new Item(['id' => 1, 'name' => '商品', 'code' => 'ABC']);

        $this->itemRepo->method('findById')->willReturn($item);
        $this->itemRepo->expects($this->once())->method('save');

        // skus 欄位應被忽略，不呼叫 skuRepo
        $this->skuRepo->expects($this->never())->method('save');

        $this->itemService->update(1, ['name' => '新名稱', 'skus' => [['sku_code' => 'X']]]);
    }

    // ──────────────────────────────────────────────
    // delete()
    // ──────────────────────────────────────────────

    public function testDeleteSucceeds(): void
    {
        $item = new Item(['id' => 1, 'code' => 'A']);

        $this->itemRepo
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($item);

        $this->itemRepo
            ->expects($this->once())
            ->method('delete')
            ->with(1);

        $this->itemService->delete(1);
    }

    public function testDeleteThrowsWhenNotFound(): void
    {
        $this->itemRepo
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('商品 #42 不存在');

        $this->itemService->delete(42);
    }

    // ──────────────────────────────────────────────
    // getSkus()
    // ──────────────────────────────────────────────

    public function testGetSkusReturnsSkuList(): void
    {
        $skus = [new ItemSku(['id' => 1, 'sku_code' => 'SKU-001'])];

        $this->skuRepo
            ->expects($this->once())
            ->method('findByItemId')
            ->with(5)
            ->willReturn($skus);

        $result = $this->itemService->getSkus(5);

        $this->assertCount(1, $result);
        $this->assertSame($skus[0], $result[0]);
    }
}
