<?php

declare(strict_types=1);

namespace App\Services;

use App\Entities\Customer;
use App\Entities\SalesOrder;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * SalesOrderPdfService — 銷售發票 PDF 產生
 *
 * 使用 DomPDF 產生含明細的客戶發票 PDF。
 */
class SalesOrderPdfService
{
    public function __construct(
        private readonly SalesOrderRepositoryInterface $soRepo,
        private readonly CustomerRepositoryInterface   $customerRepo,
    ) {
    }

    /**
     * 產生發票 PDF，回傳 PDF binary string
     */
    public function generate(int $salesOrderId): string
    {
        $so = $this->soRepo->findById($salesOrderId);
        if ($so === null) {
            throw new \RuntimeException("找不到銷售訂單 #{$salesOrderId}");
        }

        $customer = $this->customerRepo->findById($so->customer_id);
        $lines    = $this->soRepo->findLines($salesOrderId);

        $html = $this->renderHtml($so, $customer, $lines);

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

    private function renderHtml(SalesOrder $so, ?Customer $customer, array $lines): string
    {
        $customerName  = htmlspecialchars($customer?->name ?? '-');
        $customerTaxId = htmlspecialchars($customer?->tax_id ?? '-');
        $soNumber      = htmlspecialchars($so->so_number);
        $status        = htmlspecialchars($so->status ?? '');
        $orderDate     = $so->order_date ? date('Y-m-d', strtotime((string) $so->order_date)) : '-';
        $shipDate      = $so->expected_ship_date ? date('Y-m-d', strtotime((string) $so->expected_ship_date)) : '-';
        $shippingName  = htmlspecialchars($so->shipping_name ?? '-');
        $shippingAddr  = htmlspecialchars($so->shipping_address ?? '-');

        $statusLabels = [
            'draft'      => '草稿',
            'confirmed'  => '已確認',
            'partial'    => '部分出貨',
            'shipped'    => '已出貨',
            'cancelled'  => '已取消',
        ];
        $statusLabel = $statusLabels[$status] ?? $status;

        $linesHtml = '';
        foreach ($lines as $i => $line) {
            $no           = $i + 1;
            $unitPrice    = number_format((float) $line->unit_price, 4);
            $discountRate = number_format((float) $line->discount_rate, 2);
            $lineTotal    = number_format((float) $line->line_total, 2);
            $linesHtml .= "<tr>
                <td style='text-align:center'>{$no}</td>
                <td>" . htmlspecialchars((string) $line->sku_id) . "</td>
                <td style='text-align:right'>{$line->ordered_qty}</td>
                <td style='text-align:right'>{$line->shipped_qty}</td>
                <td style='text-align:right'>{$unitPrice}</td>
                <td style='text-align:right'>{$discountRate}%</td>
                <td style='text-align:right'>{$lineTotal}</td>
                <td>" . htmlspecialchars($line->notes ?? '') . "</td>
            </tr>";
        }

        $subtotal      = number_format((float) $so->subtotal, 2);
        $discountAmt   = number_format((float) $so->discount_amount, 2);
        $taxAmount     = number_format((float) $so->tax_amount, 2);
        $totalAmount   = number_format((float) $so->total_amount, 2);

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
  table.lines th { background-color: #0d7f4f; color: #fff; padding: 6px; text-align: left; }
  table.lines td { padding: 5px 6px; border-bottom: 1px solid #ddd; }
  table.lines tr:nth-child(even) td { background-color: #f2faf7; }
  .totals { float: right; margin-top: 10px; }
  .totals table td { padding: 3px 10px; }
  .totals .total-row td { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
  .badge { padding: 2px 6px; border-radius: 3px; color: #fff; font-size: 11px; }
  .badge-draft { background: #6b7280; }
  .badge-confirmed { background: #2563eb; }
  .badge-partial { background: #7c3aed; }
  .badge-shipped { background: #059669; }
  .badge-cancelled { background: #dc2626; }
  .footer { margin-top: 60px; font-size: 11px; color: #888; text-align: center; }
</style>
</head>
<body>
<h1>銷 售 發 票</h1>
<table class="header-table">
  <tr>
    <td width="50%">
      <div class="section-title">客戶資訊</div>
      <p>名稱：{$customerName}</p>
      <p>統一編號：{$customerTaxId}</p>
      <p>收件人：{$shippingName}</p>
      <p>收件地址：{$shippingAddr}</p>
    </td>
    <td width="50%">
      <div class="section-title">訂單資訊</div>
      <p>訂單號：<strong>{$soNumber}</strong></p>
      <p>狀態：<span class="badge badge-{$status}">{$statusLabel}</span></p>
      <p>訂單日期：{$orderDate}</p>
      <p>預計出貨：{$shipDate}</p>
    </td>
  </tr>
</table>

<div class="section-title">訂單明細</div>
<table class="lines">
  <thead>
    <tr>
      <th width="4%">#</th>
      <th width="12%">SKU ID</th>
      <th width="10%">訂購量</th>
      <th width="10%">已出量</th>
      <th width="15%">單價</th>
      <th width="10%">折扣</th>
      <th width="14%">小計</th>
      <th>備註</th>
    </tr>
  </thead>
  <tbody>
    {$linesHtml}
  </tbody>
</table>

<div class="totals">
  <table>
    <tr><td>商品小計</td><td style="text-align:right">NT$ {$subtotal}</td></tr>
    <tr><td>折扣金額</td><td style="text-align:right">- NT$ {$discountAmt}</td></tr>
    <tr><td>稅額</td><td style="text-align:right">NT$ {$taxAmount}</td></tr>
    <tr class="total-row"><td>應付總額</td><td style="text-align:right">NT$ {$totalAmount}</td></tr>
  </table>
</div>

<div class="footer">本發票由系統自動產生，如有疑問請聯絡客服。</div>
</body>
</html>
HTML;
    }
}
