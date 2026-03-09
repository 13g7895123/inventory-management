<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Entities\PurchaseOrder;
use App\Entities\PurchaseOrderLine;
use App\Entities\Supplier;
use App\Events\PurchaseOrderApproved;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Services\PurchaseOrderService;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * PurchaseOrderService 單元測試
 *
 * 使用 Mock Repository 隔離 DB，只測試業務邏輯與狀態流程。
 */
class PurchaseOrderServiceTest extends CIUnitTestCase
{
    private PurchaseOrderService $poService;

    /** @var PurchaseOrderRepositoryInterface&MockObject */
    private PurchaseOrderRepositoryInterface $poRepo;

    /** @var SupplierRepositoryInterface&MockObject */
    private SupplierRepositoryInterface $supplierRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poRepo       = $this->createMock(PurchaseOrderRepositoryInterface::class);
        $this->supplierRepo = $this->createMock(SupplierRepositoryInterface::class);

        $this->poService = new PurchaseOrderService($this->poRepo, $this->supplierRepo);
    }

    // ──────────────────────────────────────────────
    // create()
    // ──────────────────────────────────────────────

    public function testCreateThrowsWhenSupplierNotFound(): void
    {
        $this->supplierRepo
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('找不到供應商');

        $this->poService->create([
            'supplier_id'  => 999,
            'warehouse_id' => 1,
            'lines'        => [['sku_id' => 1, 'ordered_qty' => 10, 'unit_price' => 5.0]],
        ], createdBy: 1);
    }

    public function testCreateThrowsWhenSupplierInactive(): void
    {
        $supplier = new Supplier(['id' => 1, 'name' => 'ABC', 'is_active' => 0]);

        $this->supplierRepo
            ->method('findById')
            ->willReturn($supplier);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('供應商已停用');

        $this->poService->create([
            'supplier_id'  => 1,
            'warehouse_id' => 1,
            'lines'        => [['sku_id' => 1, 'ordered_qty' => 10, 'unit_price' => 5.0]],
        ], createdBy: 1);
    }

    public function testCreateThrowsWhenNoLines(): void
    {
        $supplier = new Supplier(['id' => 1, 'name' => 'ABC', 'is_active' => 1]);

        $this->supplierRepo
            ->method('findById')
            ->willReturn($supplier);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('至少需要一筆明細');

        $this->poService->create([
            'supplier_id'  => 1,
            'warehouse_id' => 1,
            'lines'        => [],
        ], createdBy: 1);
    }

    // ──────────────────────────────────────────────
    // submit()
    // ──────────────────────────────────────────────

    public function testSubmitChangesDraftToPending(): void
    {
        $po = $this->makeDraftPo(1);

        $this->poRepo
            ->method('findById')
            ->with(1)
            ->willReturn($po);

        $this->poRepo
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(fn (PurchaseOrder $p) => $p->isPending()));

        $result = $this->poService->submit(1);

        $this->assertTrue($result->isPending());
    }

    public function testSubmitThrowsWhenNotDraft(): void
    {
        $po = new PurchaseOrder(['id' => 2, 'status' => PurchaseOrder::STATUS_PENDING]);

        $this->poRepo
            ->method('findById')
            ->willReturn($po);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('只有草稿狀態');

        $this->poService->submit(2);
    }

    // ──────────────────────────────────────────────
    // approve()
    // ──────────────────────────────────────────────

    public function testApproveChangesPendingToApproved(): void
    {
        $po = new PurchaseOrder([
            'id'          => 3,
            'status'      => PurchaseOrder::STATUS_PENDING,
            'po_number'   => 'PO-20260101-0001',
            'supplier_id' => 1,
            'expected_date' => null,
        ]);

        $this->poRepo
            ->method('findById')
            ->with(3)
            ->willReturn($po);

        $this->poRepo
            ->method('findLines')
            ->willReturn([]);

        $this->poRepo
            ->expects($this->once())
            ->method('save');

        // 驗證 Event 有被觸發
        $triggered = false;
        Events::on('purchase_order.approved', function (PurchaseOrderApproved $event) use (&$triggered) {
            $triggered = true;
        });

        $result = $this->poService->approve(3, approvedBy: 5);

        $this->assertTrue($result->isApproved());
        $this->assertTrue($triggered);
    }

    public function testApproveThrowsWhenNotPending(): void
    {
        $po = $this->makeDraftPo(4);

        $this->poRepo
            ->method('findById')
            ->willReturn($po);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('只有待審核狀態');

        $this->poService->approve(4, approvedBy: 5);
    }

    // ──────────────────────────────────────────────
    // cancel()
    // ──────────────────────────────────────────────

    public function testCancelDraftPo(): void
    {
        $po = $this->makeDraftPo(5);

        $this->poRepo
            ->method('findById')
            ->willReturn($po);

        $this->poRepo
            ->expects($this->once())
            ->method('save');

        $result = $this->poService->cancel(5);

        $this->assertSame(PurchaseOrder::STATUS_CANCELLED, $result->status);
    }

    public function testCancelThrowsWhenApproved(): void
    {
        // APPROVED 狀態不可取消（在 isCancellable() 裡已過濾）
        $po = new PurchaseOrder(['id' => 6, 'status' => PurchaseOrder::STATUS_APPROVED]);

        $this->poRepo
            ->method('findById')
            ->willReturn($po);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('無法取消');

        $this->poService->cancel(6);
    }

    // ──────────────────────────────────────────────
    // getById() / getWithLines()
    // ──────────────────────────────────────────────

    public function testGetByIdThrowsWhenNotFound(): void
    {
        $this->poRepo
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->poService->getById(999);
    }

    public function testGetWithLinesReturnsPoAndLines(): void
    {
        $po    = $this->makeDraftPo(7);
        $lines = [new PurchaseOrderLine(['id' => 1, 'purchase_order_id' => 7])];

        $this->poRepo->method('findById')->willReturn($po);
        $this->poRepo->method('findLines')->willReturn($lines);

        $result = $this->poService->getWithLines(7);

        $this->assertSame($po, $result['purchase_order']);
        $this->assertCount(1, $result['lines']);
    }

    // ──────────────────────────────────────────────
    // 輔助方法
    // ──────────────────────────────────────────────

    private function makeDraftPo(int $id): PurchaseOrder
    {
        return new PurchaseOrder([
            'id'          => $id,
            'status'      => PurchaseOrder::STATUS_DRAFT,
            'supplier_id' => 1,
            'warehouse_id'=> 1,
            'po_number'   => "PO-20260101-{$id}",
            'subtotal'    => 100.0,
            'tax_amount'  => 5.0,
            'total_amount'=> 105.0,
        ]);
    }
}
