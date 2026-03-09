<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Shipment;
use App\Entities\ShipmentLine;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use App\Repositories\Contracts\ShipmentRepositoryInterface;
use CodeIgniter\Database\BaseConnection;

/**
 * ShipmentService — 出貨單業務邏輯
 *
 * 建立出貨單 → 扣減庫存（reserved_qty↓, on_hand_qty↓）
 * → 更新銷售訂單 shipped_qty → 判斷 SO 狀態（partial / shipped）
 */
class ShipmentService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly ShipmentRepositoryInterface  $shipmentRepo,
        private readonly SalesOrderRepositoryInterface $soRepo,
        private readonly InventoryService             $inventoryService,
    ) {
        $this->db = \Config\Database::connect();
    }

    // ── 建立並執行出貨 ─────────────────────────────────────────────

    /**
     * @param array{
     *     carrier: ?string,
     *     tracking_number: ?string,
     *     notes: ?string,
     *     lines: array<array{sales_order_line_id: int, sku_id: int, shipped_qty: float, batch_number: ?string, notes: ?string}>
     * } $data
     *
     * @throws \DomainException  銷售單狀態不符或出貨數量超過可出量
     */
    public function create(int $salesOrderId, array $data, int $createdBy): Shipment
    {
        $so = $this->soRepo->findById($salesOrderId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售單 #{$salesOrderId}");
        }
        if (!$so->isShippable()) {
            throw new \DomainException('只有已確認（confirmed/partial）的銷售單可出貨');
        }
        if (empty($data['lines'])) {
            throw new \DomainException('出貨單至少需要一筆明細');
        }

        // 預先驗證出貨數量（避免 DB 交易中途失敗再 rollback 才知道）
        foreach ($data['lines'] as $lineData) {
            $soLine = $this->soRepo->findLine((int) $lineData['sales_order_line_id']);
            if ($soLine === null) {
                throw new \DomainException("找不到銷售訂單明細 #{$lineData['sales_order_line_id']}");
            }
            $pending = $soLine->pendingQty();
            if ((float) $lineData['shipped_qty'] > $pending) {
                throw new \DomainException(
                    "SKU #{$lineData['sku_id']} 出貨數量 {$lineData['shipped_qty']} 超過待出貨數量 {$pending}"
                );
            }
        }

        $this->db->transStart();

        // 建立出貨單
        $shipment = new Shipment();
        $shipment->fill([
            'shipment_number' => $this->shipmentRepo->generateShipmentNumber(),
            'sales_order_id'  => $salesOrderId,
            'warehouse_id'    => $so->warehouse_id,
            'status'          => Shipment::STATUS_SHIPPED,
            'carrier'         => $data['carrier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'shipped_at'      => date('Y-m-d H:i:s'),
            'notes'           => $data['notes'] ?? null,
            'created_by'      => $createdBy,
        ]);
        $this->shipmentRepo->save($shipment);
        $shipmentId = (int) $this->db->insertID();

        $totalShippedByLine = [];

        // 建立出貨明細 + 扣減庫存
        foreach ($data['lines'] as $lineData) {
            $qty = (float) $lineData['shipped_qty'];

            $line = new ShipmentLine();
            $line->fill([
                'shipment_id'         => $shipmentId,
                'sales_order_line_id' => $lineData['sales_order_line_id'],
                'sku_id'              => $lineData['sku_id'],
                'shipped_qty'         => $qty,
                'batch_number'        => $lineData['batch_number'] ?? null,
                'notes'               => $lineData['notes'] ?? null,
            ]);
            $this->shipmentRepo->saveLine($line);

            // 累加各 soLine 的本次出貨量
            $soLineId = (int) $lineData['sales_order_line_id'];
            $totalShippedByLine[$soLineId] = ($totalShippedByLine[$soLineId] ?? 0.0) + $qty;

            // 扣減庫存（on_hand_qty - qty, reserved_qty - qty）
            $this->inventoryService->deductStock(
                skuId:        (int) $lineData['sku_id'],
                warehouseId:  $so->warehouse_id,
                qty:          $qty,
                sourceType:   'sales_shipment',
                sourceId:     $shipmentId,
                operatorId:   $createdBy,
                allowNegative: false,
            );
        }

        // 更新 sales_order_lines.shipped_qty
        foreach ($totalShippedByLine as $soLineId => $qty) {
            $this->soRepo->updateShippedQty($soLineId, $qty);
        }

        // 重新取得所有行以判斷 SO 狀態
        $freshedLines  = $this->soRepo->findLines($salesOrderId);
        $allFullyShipped = true;
        $anyShipped      = false;
        foreach ($freshedLines as $line) {
            if ((float) $line->shipped_qty > 0) {
                $anyShipped = true;
            }
            if ((float) $line->shipped_qty < (float) $line->ordered_qty) {
                $allFullyShipped = false;
            }
        }

        if ($allFullyShipped) {
            $so->markFullyShipped();
        } elseif ($anyShipped) {
            $so->markPartialShipped();
        }
        $this->soRepo->save($so);

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('建立出貨單 DB 交易失敗');
        }

        return $this->getWithLines($shipmentId)['shipment'];
    }

    // ── 查詢 ─────────────────────────────────────────────────────

    /**
     * @return array{shipment: Shipment, lines: ShipmentLine[]}
     *
     * @throws \RuntimeException
     */
    public function getWithLines(int $shipmentId): array
    {
        $shipment = $this->shipmentRepo->findById($shipmentId);
        if ($shipment === null) {
            throw new \RuntimeException("找不到出貨單 #{$shipmentId}");
        }

        return [
            'shipment' => $shipment,
            'lines'    => $this->shipmentRepo->findLines($shipmentId),
        ];
    }

    /**
     * 列出銷售單的所有出貨單
     *
     * @return Shipment[]
     */
    public function listBySalesOrder(int $salesOrderId): array
    {
        return $this->shipmentRepo->findBySalesOrder($salesOrderId);
    }
}
