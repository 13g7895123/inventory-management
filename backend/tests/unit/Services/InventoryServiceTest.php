<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\Inventory;
use App\Entities\StockTransfer;
use App\Models\StockTransferLineModel;
use App\Models\StockTransferModel;
use App\Models\WarehouseModel;
use App\Repositories\Contracts\InventoryRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Models\InventoryTransactionModel;
use App\Services\InventoryService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * InventoryService 單元測試
 *
 * 測試 getAllInventory, adjustStock, deductStock, replenishStock 業務邏輯。
 * 使用 Mock 隔離 DB 依賴。
 */
class InventoryServiceTest extends CIUnitTestCase
{
    private InventoryService $inventoryService;

    /** @var InventoryRepositoryInterface&MockObject */
    private InventoryRepositoryInterface $inventoryRepo;

    /** @var ItemRepositoryInterface&MockObject */
    private ItemRepositoryInterface $itemRepo;

    /** @var InventoryTransactionModel&MockObject */
    private InventoryTransactionModel $txModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryRepo = $this->createMock(InventoryRepositoryInterface::class);
        $this->itemRepo      = $this->createMock(ItemRepositoryInterface::class);
        $this->txModel       = $this->createMock(InventoryTransactionModel::class);

        $this->inventoryService = new InventoryService(
            $this->inventoryRepo,
            $this->itemRepo,
            $this->txModel,
        );
    }

    // ──────────────────────────────────────────────
    // deductStock()
    // ──────────────────────────────────────────────

    public function testDeductStockThrowsWhenInventoryNotFound(): void
    {
        $this->inventoryRepo
            ->method('findAndLock')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('找不到 SKU');

        $this->inventoryService->deductStock(
            skuId:       1,
            warehouseId: 1,
            qty:         10.0,
            sourceType:  'sales',
            sourceId:    1,
            operatorId:  1,
        );
    }

    public function testDeductStockThrowsWhenInsufficientStock(): void
    {
        $inventory = new Inventory();
        $inventory->fill([
            'id'           => 1,
            'sku_id'       => 1,
            'warehouse_id' => 1,
            'on_hand_qty'  => 5.0,
            'reserved_qty' => 0.0,
            'on_order_qty' => 0.0,
            'avg_cost'     => 10.0,
        ]);

        $this->inventoryRepo
            ->method('findAndLock')
            ->willReturn($inventory);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('庫存不足');

        $this->inventoryService->deductStock(
            skuId:       1,
            warehouseId: 1,
            qty:         10.0,  // 超出現有 5.0
            sourceType:  'sales',
            sourceId:    1,
            operatorId:  1,
        );
    }

    public function testDeductStockSucceedsAndReducesOnHandQty(): void
    {
        $inventory = new Inventory();
        $inventory->fill([
            'id'           => 1,
            'sku_id'       => 1,
            'warehouse_id' => 1,
            'on_hand_qty'  => 20.0,
            'reserved_qty' => 0.0,
            'on_order_qty' => 0.0,
            'avg_cost'     => 10.0,
        ]);

        $this->inventoryRepo
            ->method('findAndLock')
            ->willReturn($inventory);

        $this->inventoryRepo
            ->expects($this->once())
            ->method('save');

        $result = $this->inventoryService->deductStock(
            skuId:       1,
            warehouseId: 1,
            qty:         8.0,
            sourceType:  'sales',
            sourceId:    1,
            operatorId:  1,
        );

        $this->assertEqualsWithDelta(12.0, $result->on_hand_qty, 0.001);
    }

    // ──────────────────────────────────────────────
    // replenishStock()
    // ──────────────────────────────────────────────

    public function testReplenishStockCreatesNewInventoryWhenNotExists(): void
    {
        $this->inventoryRepo
            ->method('findAndLock')
            ->willReturn(null);

        $this->inventoryRepo
            ->expects($this->once())
            ->method('save');

        $result = $this->inventoryService->replenishStock(
            skuId:       1,
            warehouseId: 1,
            qty:         50.0,
            unitCost:    20.0,
            sourceType:  'purchase',
            sourceId:    1,
            operatorId:  1,
        );

        $this->assertEqualsWithDelta(50.0, $result->on_hand_qty, 0.001);
        $this->assertEqualsWithDelta(20.0, $result->avg_cost, 0.001);
    }

    public function testReplenishStockUpdatesAvgCost(): void
    {
        $inventory = new Inventory();
        $inventory->fill([
            'id'           => 1,
            'sku_id'       => 1,
            'warehouse_id' => 1,
            'on_hand_qty'  => 10.0,
            'reserved_qty' => 0.0,
            'on_order_qty' => 0.0,
            'avg_cost'     => 10.0,
        ]);

        $this->inventoryRepo
            ->method('findAndLock')
            ->willReturn($inventory);

        $this->inventoryRepo->expects($this->once())->method('save');

        $result = $this->inventoryService->replenishStock(
            skuId:       1,
            warehouseId: 1,
            qty:         10.0,
            unitCost:    20.0,   // 新批次成本較高
            sourceType:  'purchase',
            sourceId:    1,
            operatorId:  1,
        );

        // 加權平均: (10*10 + 10*20) / 20 = 15
        $this->assertEqualsWithDelta(15.0, $result->avg_cost, 0.001);
        $this->assertEqualsWithDelta(20.0, $result->on_hand_qty, 0.001);
    }

    // ──────────────────────────────────────────────
    // reserveStock()
    // ──────────────────────────────────────────────

    public function testReserveStockThrowsWhenInventoryNotFound(): void
    {
        $this->inventoryRepo->method('findAndLock')->willReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->inventoryService->reserveStock(1, 1, 5.0);
    }

    public function testReserveStockIncreasesReservedQty(): void
    {
        $inventory = new Inventory();
        $inventory->fill([
            'id'           => 1,
            'sku_id'       => 1,
            'warehouse_id' => 1,
            'on_hand_qty'  => 50.0,
            'reserved_qty' => 10.0,
            'on_order_qty' => 0.0,
            'avg_cost'     => 5.0,
        ]);

        $this->inventoryRepo->method('findAndLock')->willReturn($inventory);
        $this->inventoryRepo->expects($this->once())->method('save');

        $result = $this->inventoryService->reserveStock(1, 1, 20.0);

        $this->assertEqualsWithDelta(30.0, $result->reserved_qty, 0.001);
    }

    public function testReserveStockThrowsWhenInsufficientAvailable(): void
    {
        $inventory = new Inventory();
        $inventory->fill([
            'id'           => 1,
            'sku_id'       => 1,
            'warehouse_id' => 1,
            'on_hand_qty'  => 5.0,
            'reserved_qty' => 3.0,
            'on_order_qty' => 0.0,
            'avg_cost'     => 5.0,
        ]);

        $this->inventoryRepo->method('findAndLock')->willReturn($inventory);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('庫存不足');

        $this->inventoryService->reserveStock(1, 1, 5.0); // 可用僅 2.0
    }
}
