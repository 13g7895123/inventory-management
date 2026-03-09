<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\SalesOrder;
use App\Entities\SalesOrderLine;
use App\Entities\Shipment;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use App\Repositories\Contracts\ShipmentRepositoryInterface;
use App\Services\InventoryService;
use App\Services\ShipmentService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * ShipmentService 單元測試
 *
 * 使用 Mock Repository / InventoryService 隔離外部依賴。
 */
class ShipmentServiceTest extends CIUnitTestCase
{
    private ShipmentService $shipmentService;

    /** @var ShipmentRepositoryInterface&MockObject */
    private ShipmentRepositoryInterface $shipmentRepo;

    /** @var SalesOrderRepositoryInterface&MockObject */
    private SalesOrderRepositoryInterface $soRepo;

    /** @var InventoryService&MockObject */
    private InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shipmentRepo     = $this->createMock(ShipmentRepositoryInterface::class);
        $this->soRepo           = $this->createMock(SalesOrderRepositoryInterface::class);
        $this->inventoryService = $this->createMock(InventoryService::class);

        $this->shipmentService = new ShipmentService(
            $this->shipmentRepo,
            $this->soRepo,
            $this->inventoryService,
        );
    }

    // ──────────────────────────────────────────────
    // create() — 基本防護
    // ──────────────────────────────────────────────

    public function testCreateThrowsWhenSalesOrderNotFound(): void
    {
        $this->soRepo
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('找不到銷售單');

        $this->shipmentService->create(999, ['lines' => []], createdBy: 1);
    }

    public function testCreateThrowsWhenSONotShippable(): void
    {
        // draft 狀態的 SO 不可出貨
        $so = $this->makeSalesOrder(1, SalesOrder::STATUS_DRAFT);

        $this->soRepo
            ->method('findById')
            ->willReturn($so);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('只有已確認');

        $this->shipmentService->create(1, [
            'lines' => [['sales_order_line_id' => 1, 'sku_id' => 10, 'shipped_qty' => 2]],
        ], createdBy: 1);
    }

    public function testCreateThrowsWhenNoLines(): void
    {
        $so = $this->makeSalesOrder(1, SalesOrder::STATUS_CONFIRMED);

        $this->soRepo
            ->method('findById')
            ->willReturn($so);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('至少需要一筆明細');

        $this->shipmentService->create(1, ['lines' => []], createdBy: 1);
    }

    public function testCreateThrowsWhenQtyExceedsPending(): void
    {
        $so    = $this->makeSalesOrder(1, SalesOrder::STATUS_CONFIRMED, warehouseId: 1);
        $soLine = $this->makeLine(id: 5, skuId: 10, orderedQty: 5, shippedQty: 3); // pending = 2

        $this->soRepo
            ->method('findById')
            ->willReturn($so);

        $this->soRepo
            ->method('findLine')
            ->with(5)
            ->willReturn($soLine);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('超過待出貨數量');

        $this->shipmentService->create(1, [
            'lines' => [['sales_order_line_id' => 5, 'sku_id' => 10, 'shipped_qty' => 5]], // 5 > 2
        ], createdBy: 1);
    }

    // ──────────────────────────────────────────────
    // listBySalesOrder()
    // ──────────────────────────────────────────────

    public function testListBySalesOrderReturnsShipments(): void
    {
        $shipment = new Shipment([
            'id'             => 1,
            'shipment_number' => 'SH-20260309-0001',
            'sales_order_id' => 1,
            'status'         => Shipment::STATUS_SHIPPED,
        ]);

        $this->shipmentRepo
            ->method('findBySalesOrder')
            ->with(1)
            ->willReturn([$shipment]);

        $result = $this->shipmentService->listBySalesOrder(1);

        $this->assertCount(1, $result);
        $this->assertSame('SH-20260309-0001', $result[0]->shipment_number);
    }

    // ──────────────────────────────────────────────
    // getWithLines()
    // ──────────────────────────────────────────────

    public function testGetWithLinesThrowsWhenNotFound(): void
    {
        $this->shipmentRepo
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('找不到出貨單');

        $this->shipmentService->getWithLines(999);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function makeSalesOrder(int $id, string $status, int $warehouseId = 1): SalesOrder
    {
        return new SalesOrder([
            'id'           => $id,
            'so_number'    => "SO-TEST-{$id}",
            'customer_id'  => 1,
            'warehouse_id' => $warehouseId,
            'status'       => $status,
            'tax_rate'     => 5.0,
            'subtotal'     => 100.0,
            'tax_amount'   => 5.0,
            'total_amount' => 105.0,
        ]);
    }

    private function makeLine(int $id, int $skuId, float $orderedQty, float $shippedQty): SalesOrderLine
    {
        return new SalesOrderLine([
            'id'             => $id,
            'sales_order_id' => 1,
            'sku_id'         => $skuId,
            'ordered_qty'    => $orderedQty,
            'shipped_qty'    => $shippedQty,
            'unit_price'     => 100.0,
            'discount_rate'  => 0,
            'line_total'     => $orderedQty * 100.0,
        ]);
    }
}
