<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PurchaseOrderPdfService — 採購單 PDF 產生
 *
 * 使用 DomPDF 產生含明細的採購單 PDF。
 */
class PurchaseOrderPdfService
{
    public function __construct(
        private readonly PurchaseOrderRepositoryInterface $poRepo,
        private readonly SupplierRepositoryInterface      $supplierRepo,
    ) {
    }

    /**
     * 產生採購單 PDF，回傳 PDF binary string
     */
    public function generate(int $purchaseOrderId): string
    {
        $po = $this->poRepo->findById($purchaseOrderId);
        if ($po === null) {
            throw new \RuntimeException("找不到採購單 #{$purchaseOrderId}");
        }

        $supplier = $this->supplierRepo->findById($po->supplier_id);
        $lines    = $this->poRepo->findLines($purchaseOrderId);

        $html = $this->renderHtml($po, $supplier, $lines);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function renderHtml(PurchaseOrder $po, $supplier, array $lines): string
    {
        $supplierName    = htmlspecialchars($supplier?->name ?? '-');
        $supplierContact = htmlspecialchars($supplier?->contact_name ?? '-');
        $supplierPhone   = htmlspecialchars($supplier?->contact_phone ?? '-');
        $poNumber        = htmlspecialchars($po->po_number);
        $status          = htmlspecialchars($po->status ?? '');
        $expectedDate    = $po->expected_date ? date('Y-m-d', strtotime((string) $po->expected_date)) : '-';
        $createdAt       = $po->created_at ? date('Y-m-d', strtotime((string) $po->created_at)) : '-';

        $statusLabels = [
            'draft'     => '草稿',
            'pending'   => '待審核',
            'approved'  => '已核准',
            'partial'   => '部分到貨',
            'received'  => '全部到貨',
            'cancelled' => '已取消',
        ];
        $statusLabel = $statusLabels[$status] ?? $status;

        $linesHtml = '';
        foreach ($lines as $i => $line) {
            $no        = $i + 1;
            $lineTotal = number_format((float) $line->line_total, 2);
            $unitPrice = number_format((float) $line->unit_price, 4);
            $linesHtml .= "<tr>
                <td style='text-align:center'>{$no}</td>
                <td>" . htmlspecialchars((string) $line->sku_id) . "</td>
                <td style='text-align:right'>{$line->ordered_qty}</td>
                <td style='text-align:right'>{$line->received_qty}</td>
                <td style='text-align:right'>{$unitPrice}</td>
                <td style='text-align:right'>{$lineTotal}</td>
                <td>" . htmlspecialchars($line->notes ?? '') . "</td>
            </tr>";
        }

        $subtotal    = number_format((float) $po->subtotal, 2);
        $taxAmount   = number_format((float) $po->tax_amount, 2);
        $totalAmount = number_format((float) $po->total_amount, 2);

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: "DejaVu Sans", sans-serif; font-size: 12px; color: #333; }
  h1 { font-size: 20px; margin-bottom: 4px; }
  .header-table { width: 100%; margin-bottom: 20px; }
  .header-table td { vertical-align: top; }
  .section-title { font-weight: bold; margin-bottom: 6px; border-bottom: 1px solid #999; padding-bottom: 2px; }
  table.lines { width: 100%; border-collapse: collapse; }
  table.lines th { background-color: #1a56db; color: #fff; padding: 6px; text-align: left; }
  table.lines td { padding: 5px 6px; border-bottom: 1px solid #ddd; }
  table.lines tr:nth-child(even) td { background-color: #f5f8ff; }
  .totals { float: right; margin-top: 10px; }
  .totals table td { padding: 3px 10px; }
  .totals .total-row td { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
  .badge { padding: 2px 6px; border-radius: 3px; color: #fff; font-size: 11px; }
  .badge-draft { background: #6b7280; }
  .badge-pending { background: #d97706; }
  .badge-approved { background: #2563eb; }
  .badge-partial { background: #7c3aed; }
  .badge-received { background: #059669; }
  .badge-cancelled { background: #dc2626; }
</style>
</head>
<body>
<h1>採 購 單</h1>
<table class="header-table">
  <tr>
    <td width="50%">
      <div class="section-title">供應商資訊</div>
      <p>名稱：{$supplierName}</p>
      <p>聯絡人：{$supplierContact}</p>
      <p>電話：{$supplierPhone}</p>
    </td>
    <td width="50%">
      <div class="section-title">採購單資訊</div>
      <p>採購單號：<strong>{$poNumber}</strong></p>
      <p>狀態：<span class="badge badge-{$status}">{$statusLabel}</span></p>
      <p>建立日期：{$createdAt}</p>
      <p>預計到貨：{$expectedDate}</p>
    </td>
  </tr>
</table>

<div class="section-title">明細</div>
<table class="lines">
  <thead>
    <tr>
      <th width="4%">#</th>
      <th width="12%">SKU ID</th>
      <th width="12%">訂購量</th>
      <th width="12%">已到量</th>
      <th width="16%">單價</th>
      <th width="16%">小計</th>
      <th>備註</th>
    </tr>
  </thead>
  <tbody>
    {$linesHtml}
  </tbody>
</table>

<div class="totals">
  <table>
    <tr><td>小計</td><td style="text-align:right">NT$ {$subtotal}</td></tr>
    <tr><td>稅額</td><td style="text-align:right">NT$ {$taxAmount}</td></tr>
    <tr class="total-row"><td>總計</td><td style="text-align:right">NT$ {$totalAmount}</td></tr>
  </table>
</div>
</body>
</html>
HTML;
    }
}
