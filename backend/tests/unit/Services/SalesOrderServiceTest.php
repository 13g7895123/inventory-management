<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\Customer;
use App\Entities\SalesOrder;
use App\Entities\SalesOrderLine;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use App\Services\InventoryService;
use App\Services\SalesOrderService;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * SalesOrderService 單元測試
 *
 * 使用 Mock 隔離 DB / Repository / InventoryService，只驗證業務邏輯。
 */
class SalesOrderServiceTest extends CIUnitTestCase
{
    private SalesOrderService $soService;

    /** @var SalesOrderRepositoryInterface&MockObject */
    private SalesOrderRepositoryInterface $soRepo;

    /** @var CustomerRepositoryInterface&MockObject */
    private CustomerRepositoryInterface $customerRepo;

    /** @var InventoryService&MockObject */
    private InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->soRepo           = $this->createMock(SalesOrderRepositoryInterface::class);
        $this->customerRepo     = $this->createMock(CustomerRepositoryInterface::class);
        $this->inventoryService = $this->createMock(InventoryService::class);

        $this->soService = new SalesOrderService(
            $this->soRepo,
            $this->customerRepo,
            $this->inventoryService,
        );
    }

    // ──────────────────────────────────────────────
    // create()
    // ──────────────────────────────────────────────

    public function testCreateThrowsWhenCustomerNotFound(): void
    {
        $this->customerRepo
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('找不到客戶');

        $this->soService->create([
            'customer_id'  => 999,
            'warehouse_id' => 1,
            'lines'        => [['sku_id' => 1, 'ordered_qty' => 5, 'unit_price' => 100.0]],
        ], createdBy: 1);
    }

    public function testCreateThrowsWhenCustomerInactive(): void
    {
        $customer = new Customer(['id' => 1, 'name' => 'Test', 'is_active' => 0]);

        $this->customerRepo
            ->method('findById')
            ->willReturn($customer);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('客戶已停用');

        $this->soService->create([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'lines'        => [['sku_id' => 1, 'ordered_qty' => 5, 'unit_price' => 100.0]],
        ], createdBy: 1);
    }

    public function testCreateThrowsWhenNoLines(): void
    {
        $customer = new Customer(['id' => 1, 'name' => 'Test', 'is_active' => 1]);

        $this->customerRepo
            ->method('findById')
            ->willReturn($customer);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('至少需要一筆明細');

        $this->soService->create([
            'customer_id'  => 1,
            'warehouse_id' => 1,
            'lines'        => [],
        ], createdBy: 1);
    }

    // ──────────────────────────────────────────────
    // confirm()
    // ──────────────────────────────────────────────

    public function testConfirmThrowsWhenSalesOrderNotFound(): void
    {
        $this->soRepo
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('找不到銷售單');

        $this->soService->confirm(999, confirmedBy: 1);
    }

    public function testConfirmThrowsWhenNotDraft(): void
    {
        $so = $this->makeSalesOrder(1, SalesOrder::STATUS_CONFIRMED);

        $this->soRepo
            ->method('findById')
            ->willReturn($so);

        $this->expectException(\DomainException::class);

        $this->soService->confirm(1, confirmedBy: 1);
    }

    public function testConfirmReservesInventoryPerLine(): void
    {
        $so = $this->makeSalesOrder(1, SalesOrder::STATUS_DRAFT, warehouseId: 2);

        $lines = [
            $this->makeLine(skuId: 10, orderedQty: 5, shippedQty: 0),
            $this->makeLine(skuId: 20, orderedQty: 3, shippedQty: 0),
        ];

        $this->soRepo
            ->method('findById')
            ->willReturnOnConsecutiveCalls($so, $so);

        $this->soRepo
            ->method('findLines')
            ->willReturn($lines);

        // 預留庫存應被呼叫 2 次，每行各一次
        $this->inventoryService
            ->expects($this->exactly(2))
            ->method('reserveStock')
            ->withConsecutive(
                [10, 2, 5.0],
                [20, 2, 3.0],
            );

        $this->soRepo->method('save')->willReturn(true);

        $this->soService->confirm(1, confirmedBy: 1);
    }

    public function testConfirmThrowsWhenInsufficientStock(): void
    {
        $so    = $this->makeSalesOrder(1, SalesOrder::STATUS_DRAFT, warehouseId: 1);
        $lines = [$this->makeLine(skuId: 5, orderedQty: 100, shippedQty: 0)];

        $this->soRepo
            ->method('findById')
            ->willReturn($so);

        $this->soRepo
            ->method('findLines')
            ->willReturn($lines);

        $this->inventoryService
            ->method('reserveStock')
            ->willThrowException(new \DomainException('可用庫存不足'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('可用庫存不足');

        $this->soService->confirm(1, confirmedBy: 1);
    }

    // ──────────────────────────────────────────────
    // cancel()
    // ──────────────────────────────────────────────

    public function testCancelThrowsWhenSalesOrderNotFound(): void
    {
        $this->soRepo
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('找不到銷售單');

        $this->soService->cancel(999);
    }

    public function testCancelDraftDoesNotReleaseReservation(): void
    {
        $so = $this->makeSalesOrder(1, SalesOrder::STATUS_DRAFT);

        $this->soRepo
            ->method('findById')
            ->willReturn($so);

        $this->soRepo
            ->method('findLines')
            ->willReturn([]);

        // 草稿取消：沒有預留庫存，不應呼叫 releaseReservation
        $this->inventoryService
            ->expects($this->never())
            ->method('releaseReservation');

        $this->soRepo->method('save')->willReturn(true);

        $this->soService->cancel(1);
    }

    public function testCancelConfirmedReleasesReservationForPendingQty(): void
    {
        $so = $this->makeSalesOrder(1, SalesOrder::STATUS_CONFIRMED, warehouseId: 3);

        $lines = [
            $this->makeLine(skuId: 10, orderedQty: 10, shippedQty: 4), // pending = 6
            $this->makeLine(skuId: 20, orderedQty: 5, shippedQty: 5),  // pending = 0，無需釋放
        ];

        $this->soRepo
            ->method('findById')
            ->willReturn($so);

        $this->soRepo
            ->method('findLines')
            ->willReturn($lines);

        // 只有 sku=10 的 pendingQty=6 需要釋放（sku=20 全部出完）
        $this->inventoryService
            ->expects($this->once())
            ->method('releaseReservation')
            ->with(10, 3, 6.0);

        $this->soRepo->method('save')->willReturn(true);

        $this->soService->cancel(1);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function makeSalesOrder(int $id, string $status, int $warehouseId = 1): SalesOrder
    {
        $so = new SalesOrder([
            'id'           => $id,
            'so_number'    => "SO-TEST-{$id}",
            'customer_id'  => 1,
            'warehouse_id' => $warehouseId,
            'status'       => $status,
            'tax_rate'     => 5.0,
            'subtotal'     => 0,
            'tax_amount'   => 0,
            'total_amount' => 0,
        ]);

        return $so;
    }

    private function makeLine(int $skuId, float $orderedQty, float $shippedQty): SalesOrderLine
    {
        return new SalesOrderLine([
            'id'             => random_int(1, 9999),
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
