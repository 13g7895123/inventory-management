<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\Stocktake;
use App\Entities\StocktakeLine;
use App\Models\StocktakeLineModel;
use App\Models\StocktakeModel;
use App\Services\InventoryService;
use App\Services\StocktakeService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * StocktakeService 單元測試
 *
 * 測試狀態流程、差異計算、DomainException 守門邏輯。
 * 使用 Mock 隔離 DB 依賴。
 */
class StocktakeServiceTest extends CIUnitTestCase
{
    private StocktakeService $stocktakeService;

    /** @var StocktakeModel&MockObject */
    private StocktakeModel $stocktakeModel;

    /** @var StocktakeLineModel&MockObject */
    private StocktakeLineModel $lineModel;

    /** @var InventoryService&MockObject */
    private InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stocktakeModel   = $this->createMock(StocktakeModel::class);
        $this->lineModel        = $this->createMock(StocktakeLineModel::class);
        $this->inventoryService = $this->createMock(InventoryService::class);

        $this->stocktakeService = new StocktakeService(
            $this->stocktakeModel,
            $this->lineModel,
            $this->inventoryService,
        );
    }

    // ──────────────────────────────────────────────
    // start()
    // ──────────────────────────────────────────────

    public function testStartThrowsWhenStocktakeNotFound(): void
    {
        $this->stocktakeModel->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('找不到盤點單');

        $this->stocktakeService->start(999);
    }

    public function testStartChangesStatusToInProgress(): void
    {
        $stocktake = new Stocktake();
        $stocktake->fill([
            'id'               => 1,
            'stocktake_number' => 'SK-20260101-0001',
            'warehouse_id'     => 1,
            'status'           => Stocktake::STATUS_DRAFT,
            'created_by'       => 1,
        ]);

        $this->stocktakeModel->method('find')->willReturn($stocktake);
        $this->stocktakeModel->expects($this->once())->method('save');

        $result = $this->stocktakeService->start(1);

        $this->assertSame(Stocktake::STATUS_IN_PROGRESS, $result->status);
    }

    public function testStartThrowsWhenAlreadyInProgress(): void
    {
        $stocktake = new Stocktake();
        $stocktake->fill([
            'id'     => 1,
            'status' => Stocktake::STATUS_IN_PROGRESS,
        ]);

        $this->stocktakeModel->method('find')->willReturn($stocktake);

        $this->expectException(\DomainException::class);

        $this->stocktakeService->start(1);
    }

    // ──────────────────────────────────────────────
    // updateCount()
    // ──────────────────────────────────────────────

    public function testUpdateCountThrowsWhenStocktakeConfirmed(): void
    {
        $stocktake = new Stocktake();
        $stocktake->fill([
            'id'     => 1,
            'status' => Stocktake::STATUS_CONFIRMED,
        ]);

        $this->stocktakeModel->method('find')->willReturn($stocktake);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('不允許錄入盤點數量');

        $this->stocktakeService->updateCount(1, 1, 10.0);
    }

    public function testUpdateCountCalculatesDifference(): void
    {
        $stocktake = new Stocktake();
        $stocktake->fill([
            'id'     => 1,
            'status' => Stocktake::STATUS_IN_PROGRESS,
        ]);

        $line = new StocktakeLine();
        $line->fill([
            'id'           => 1,
            'stocktake_id' => 1,
            'sku_id'       => 5,
            'system_qty'   => 100.0,
            'actual_qty'   => null,
        ]);

        $this->stocktakeModel->method('find')->willReturn($stocktake);

        // 模擬 lineModel->where(...)->where(...)->first() 的鏈式呼叫
        $lineModelMock = $this->getMockBuilder(StocktakeLineModel::class)
            ->onlyMethods(['where', 'first', 'save'])
            ->getMock();

        $lineModelMock->method('where')->willReturnSelf();
        $lineModelMock->method('first')->willReturn($line);
        $lineModelMock->expects($this->once())->method('save');

        $service = new StocktakeService($this->stocktakeModel, $lineModelMock, $this->inventoryService);
        $result  = $service->updateCount(1, 5, 90.0);

        $this->assertEqualsWithDelta(90.0,  $result->actual_qty,   0.001);
        $this->assertEqualsWithDelta(-10.0, $result->difference_qty, 0.001);
    }

    // ──────────────────────────────────────────────
    // cancel()
    // ──────────────────────────────────────────────

    public function testCancelThrowsWhenStocktakeNotFound(): void
    {
        $this->stocktakeModel->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->stocktakeService->cancel(999);
    }

    public function testCancelThrowsWhenAlreadyConfirmed(): void
    {
        $stocktake = new Stocktake();
        $stocktake->fill([
            'id'     => 1,
            'status' => Stocktake::STATUS_CONFIRMED,
        ]);

        $this->stocktakeModel->method('find')->willReturn($stocktake);

        $this->expectException(\DomainException::class);

        $this->stocktakeService->cancel(1);
    }

    public function testCancelSucceedsFromDraft(): void
    {
        $stocktake = new Stocktake();
        $stocktake->fill([
            'id'     => 1,
            'status' => Stocktake::STATUS_DRAFT,
        ]);

        $this->stocktakeModel->method('find')->willReturn($stocktake);
        $this->stocktakeModel->expects($this->once())->method('save');

        $result = $this->stocktakeService->cancel(1);

        $this->assertSame(Stocktake::STATUS_CANCELLED, $result->status);
    }

    public function testCancelSucceedsFromInProgress(): void
    {
        $stocktake = new Stocktake();
        $stocktake->fill([
            'id'     => 1,
            'status' => Stocktake::STATUS_IN_PROGRESS,
        ]);

        $this->stocktakeModel->method('find')->willReturn($stocktake);
        $this->stocktakeModel->expects($this->once())->method('save');

        $result = $this->stocktakeService->cancel(1);

        $this->assertSame(Stocktake::STATUS_CANCELLED, $result->status);
    }
}
