<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\BatchSerial;
use App\Entities\GoodsReceipt;
use App\Entities\GoodsReceiptLine;
use App\Entities\PurchaseOrder;
use App\Models\BatchSerialModel;
use App\Models\GoodsReceiptLineModel;
use App\Models\GoodsReceiptModel;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use CodeIgniter\Database\BaseConnection;

/**
 * GoodsReceiptService — 進貨驗收業務邏輯
 *
 * 核心流程：
 *   1. 驗證採購單狀態（已核准/部分到貨）
 *   2. 驗證明細數量（不可超過未到貨量）
 *   3. 建立 GoodsReceipt + GoodsReceiptLine
 *   4. 呼叫 InventoryService::replenishStock()（含 DB Transaction + Event）
 *   5. 更新 PurchaseOrderLine.received_qty
 *   6. 更新 PurchaseOrder 狀態：全部到貨 → received，否則 → partial
 *   7. 如有批號 → 建立 BatchSerial 記錄
 */
class GoodsReceiptService
{
    private readonly BaseConnection $db;

    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepo,
        private readonly InventoryService                 $inventoryService,
        private readonly GoodsReceiptModel                $grModel,
        private readonly GoodsReceiptLineModel            $grLineModel,
        private readonly BatchSerialModel                 $batchSerialModel,
    ) {
        $this->db = \Config\Database::connect();
    }

    /**
     * 執行進貨驗收
     *
     * @param int $purchaseOrderId
     * @param array<array{
     *     line_id: int,
     *     received_qty: float,
     *     unit_cost: ?float,
     *     batch_number: ?string,
     *     expiry_date: ?string,
     *     notes: ?string
     * }> $receiveLines
     * @param int $receivedBy   操作人員 user_id
     */
    public function receive(int $purchaseOrderId, array $receiveLines, int $receivedBy): GoodsReceipt
    {
        $po = $this->poRepo->findById($purchaseOrderId);

        if ($po === null) {
            throw new \RuntimeException("找不到採購單 #{$purchaseOrderId}");
        }

        if (!$po->isApproved()) {
            throw new \DomainException('只有已核准（含部分到貨）的採購單可以進行驗收');
        }

        if (empty($receiveLines)) {
            throw new \DomainException('至少需要一筆驗收明細');
        }

        $this->db->transStart();

        // 建立進貨單主檔
        $gr = new GoodsReceipt();
        $gr->fill([
            'gr_number'         => $this->generateGrNumber(),
            'purchase_order_id' => $purchaseOrderId,
            'warehouse_id'      => $po->warehouse_id,
            'received_by'       => $receivedBy,
            'received_at'       => date('Y-m-d H:i:s'),
        ]);
        $this->grModel->save($gr);
        $grId = $this->grModel->getInsertID();

        $allFullyReceived = true;

        foreach ($receiveLines as $lineData) {
            $poLine = $this->poRepo->findLine($lineData['line_id']);

            if ($poLine === null) {
                throw new \DomainException("找不到採購單明細 #{$lineData['line_id']}");
            }
            if ((int) $poLine->purchase_order_id !== $purchaseOrderId) {
                throw new \DomainException("明細 #{$lineData['line_id']} 不屬於此採購單");
            }

            $receivedQty = (float) $lineData['received_qty'];
            $pendingQty  = $poLine->getPendingQty();

            if ($receivedQty <= 0) {
                throw new \DomainException("明細 #{$lineData['line_id']} 的驗收數量必須大於零");
            }
            if ($receivedQty > $pendingQty) {
                throw new \DomainException(
                    "明細 #{$lineData['line_id']} 驗收數量 {$receivedQty} " .
                    "超過未到貨數量 {$pendingQty}"
                );
            }

            $unitCost    = (float) ($lineData['unit_cost'] ?? $poLine->unit_price);
            $batchNumber = $lineData['batch_number'] ?? null;
            $expiryDate  = $lineData['expiry_date']  ?? null;

            // 建立進貨明細
            $grLine = new GoodsReceiptLine();
            $grLine->fill([
                'goods_receipt_id'       => $grId,
                'purchase_order_line_id' => $poLine->id,
                'sku_id'                 => $poLine->sku_id,
                'received_qty'           => $receivedQty,
                'unit_cost'              => $unitCost,
                'batch_number'           => $batchNumber,
                'expiry_date'            => $expiryDate,
                'notes'                  => $lineData['notes'] ?? null,
            ]);
            $this->grLineModel->save($grLine);
            $grLineId = $this->grLineModel->getInsertID();

            // 更新採購單明細的累計到貨量
            $this->poRepo->updateReceivedQty($poLine->id, $receivedQty);

            // 判斷是否全部到貨（需重新計算）
            $newReceivedQty = $poLine->received_qty + $receivedQty;
            if ($newReceivedQty < $poLine->ordered_qty) {
                $allFullyReceived = false;
            }

            $this->db->transComplete();
            if (!$this->db->transStatus()) {
                throw new \RuntimeException('進貨驗收 DB 交易失敗');
            }

            // ── 庫存入庫（各自含 Transaction + Event）──
            $this->inventoryService->replenishStock(
                skuId:      $poLine->sku_id,
                warehouseId: $po->warehouse_id,
                qty:        $receivedQty,
                unitCost:   $unitCost,
                sourceType: 'goods_receipt',
                sourceId:   $grId,
                operatorId: $receivedBy,
            );

            // 批號記錄
            if ($batchNumber !== null) {
                $bs = new BatchSerial();
                $bs->fill([
                    'sku_id'                => $poLine->sku_id,
                    'warehouse_id'          => $po->warehouse_id,
                    'goods_receipt_line_id' => $grLineId,
                    'type'                  => BatchSerial::TYPE_BATCH,
                    'batch_number'          => $batchNumber,
                    'quantity'              => $receivedQty,
                    'unit_cost'             => $unitCost,
                    'expiry_date'           => $expiryDate,
                    'status'                => BatchSerial::STATUS_AVAILABLE,
                ]);
                $this->batchSerialModel->save($bs);
            }

            // 為下一筆明細重啟 transaction
            $this->db->transStart();
        }

        // 更新採購單狀態
        $newPoStatus = $allFullyReceived
            ? PurchaseOrder::STATUS_RECEIVED
            : PurchaseOrder::STATUS_PARTIAL;

        $this->db->query(
            'UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?',
            [$newPoStatus, $purchaseOrderId]
        );

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('更新採購單狀態 DB 交易失敗');
        }

        return $this->grModel->find($grId);
    }

    // ── 私有輔助 ──────────────────────────────────────────────────

    private function generateGrNumber(): string
    {
        $today  = date('Ymd');
        $prefix = "GR-{$today}-";

        $last = $this->grModel
            ->like('gr_number', $prefix, 'after')
            ->orderBy('id', 'DESC')
            ->first();

        if ($last === null) {
            $seq = 1;
        } else {
            $parts = explode('-', $last->gr_number);
            $seq   = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
