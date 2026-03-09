<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * ReportService — 報表與分析業務邏輯
 *
 * 涵蓋：
 *   - 儀表板 KPI 彙整
 *   - 近 N 天銷售趨勢
 *   - 進銷存彙總表（期初/期末庫存）
 *   - 銷售業績報表（依 SKU / 客戶）
 *   - 採購報表（依廠商）
 *   - 毛利分析報表
 *   - 庫存週轉率報表
 *   - Excel 匯出
 */
class ReportService
{
    private readonly BaseConnection $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    // ────────────────────────────────────────────────────────────────
    // T15-1 / T14-1  儀表板 KPI
    // ────────────────────────────────────────────────────────────────

    /**
     * 取得儀表板 KPI 彙整資料
     *
     * @return array{
     *   pending_sales_orders: int,
     *   pending_purchase_orders: int,
     *   low_stock_count: int,
     *   monthly_sales_amount: float,
     *   monthly_purchase_amount: float,
     *   monthly_sales_orders: int,
     * }
     */
    public function getDashboardKpi(): array
    {
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd   = date('Y-m-t 23:59:59');

        // 待確認銷售單（draft 狀態）
        $pendingSO = (int) $this->db
            ->table('sales_orders')
            ->where('status', 'draft')
            ->whereNull('deleted_at')
            ->countAllResults();

        // 待審核採購單（pending 狀態）
        $pendingPO = (int) $this->db
            ->table('purchase_orders')
            ->where('status', 'pending')
            ->whereNull('deleted_at')
            ->countAllResults();

        // 低庫存品項（可用庫存 < 安全庫存）
        $lowStockCount = (int) $this->db
            ->table('inventory i')
            ->join('item_skus s', 's.id = i.sku_id')
            ->join('items it', 'it.id = s.item_id')
            ->where('(i.on_hand_qty - i.reserved_qty) <', $this->db->escape('it.safety_stock'), false)
            ->where('it.safety_stock >', 0)
            ->whereNull('it.deleted_at')
            ->countAllResults();

        // 本月銷售總額（confirmed/partial/shipped）
        $monthlySales = (float) ($this->db
            ->table('sales_orders')
            ->selectSum('total_amount')
            ->whereIn('status', ['confirmed', 'partial', 'shipped'])
            ->where('order_date >=', $monthStart)
            ->where('order_date <=', $monthEnd)
            ->whereNull('deleted_at')
            ->get()->getRow()?->total_amount ?? 0);

        // 本月採購總額（approved/partial/received）
        $monthlyPurchase = (float) ($this->db
            ->table('purchase_orders')
            ->selectSum('total_amount')
            ->whereIn('status', ['approved', 'partial', 'received'])
            ->where('order_date >=', $monthStart)
            ->where('order_date <=', $monthEnd)
            ->whereNull('deleted_at')
            ->get()->getRow()?->total_amount ?? 0);

        // 本月銷售訂單數
        $monthlySalesOrders = (int) $this->db
            ->table('sales_orders')
            ->whereIn('status', ['confirmed', 'partial', 'shipped'])
            ->where('order_date >=', $monthStart)
            ->where('order_date <=', $monthEnd)
            ->whereNull('deleted_at')
            ->countAllResults();

        return [
            'pending_sales_orders'    => $pendingSO,
            'pending_purchase_orders' => $pendingPO,
            'low_stock_count'         => $lowStockCount,
            'monthly_sales_amount'    => $monthlySales,
            'monthly_purchase_amount' => $monthlyPurchase,
            'monthly_sales_orders'    => $monthlySalesOrders,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // T15-2  近 N 天銷售趨勢
    // ────────────────────────────────────────────────────────────────

    /**
     * 取得近 N 天每日銷售趨勢
     *
     * @return array<array{date: string, amount: float, order_count: int}>
     */
    public function getSalesTrend(int $days = 30): array
    {
        $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

        $rows = $this->db
            ->table('sales_orders')
            ->select('DATE(order_date) as date, SUM(total_amount) as amount, COUNT(*) as order_count')
            ->whereIn('status', ['confirmed', 'partial', 'shipped'])
            ->where('order_date >=', $dateFrom . ' 00:00:00')
            ->whereNull('deleted_at')
            ->groupBy('DATE(order_date)')
            ->orderBy('date', 'ASC')
            ->get()->getResultArray();

        // 補齊沒有銷售的日期
        $result   = [];
        $dateMap  = array_column($rows, null, 'date');
        $current  = strtotime($dateFrom);
        $today    = strtotime(date('Y-m-d'));

        while ($current <= $today) {
            $dateStr = date('Y-m-d', $current);
            $result[] = [
                'date'        => $dateStr,
                'amount'      => $dateMap[$dateStr] ? (float) $dateMap[$dateStr]['amount'] : 0.0,
                'order_count' => $dateMap[$dateStr] ? (int) $dateMap[$dateStr]['order_count'] : 0,
            ];
            $current = strtotime('+1 day', $current);
        }

        return $result;
    }

    // ────────────────────────────────────────────────────────────────
    // T14-1  進銷存彙總表
    // ────────────────────────────────────────────────────────────────

    /**
     * 進銷存彙總表：期初庫存 + 本期入庫 - 本期出庫 = 期末庫存
     *
     * @return array{
     *   items: array<array{
     *     sku_id: int, sku_code: string, item_name: string,
     *     warehouse_id: int, warehouse_name: string,
     *     opening_qty: float, in_qty: float, out_qty: float,
     *     adjust_qty: float, closing_qty: float,
     *     avg_cost: float, closing_value: float
     *   }>,
     *   summary: array{total_opening_value: float, total_closing_value: float}
     * }
     */
    public function getInventorySummary(
        string  $dateFrom,
        string  $dateTo,
        ?int    $warehouseId = null,
        ?string $search = null,
    ): array {
        $dateFromFull = $dateFrom . ' 00:00:00';
        $dateTofull   = $dateTo  . ' 23:59:59';

        // 取所有 SKU + 倉庫庫存組合
        $query = $this->db
            ->table('inventory inv')
            ->select('
                inv.sku_id,
                s.sku_code,
                it.name   AS item_name,
                inv.warehouse_id,
                w.name    AS warehouse_name,
                inv.on_hand_qty   AS closing_qty,
                inv.avg_cost
            ')
            ->join('item_skus s',    's.id  = inv.sku_id')
            ->join('items it',       'it.id = s.item_id')
            ->join('warehouses w',   'w.id  = inv.warehouse_id')
            ->whereNull('it.deleted_at')
            ->whereNull('w.deleted_at');

        if ($warehouseId !== null) {
            $query->where('inv.warehouse_id', $warehouseId);
        }
        if ($search !== null && $search !== '') {
            $query->groupStart()
                  ->like('s.sku_code', $search)
                  ->orLike('it.name', $search)
                  ->groupEnd();
        }

        $skuRows = $query->get()->getResultArray();

        if (empty($skuRows)) {
            return ['items' => [], 'summary' => ['total_opening_value' => 0.0, 'total_closing_value' => 0.0]];
        }

        // 取期間內庫存異動
        $txQuery = $this->db
            ->table('inventory_transactions tx')
            ->select('
                tx.sku_id,
                tx.warehouse_id,
                tx.transaction_type,
                tx.quantity,
                tx.unit_cost
            ')
            ->where('tx.performed_at >=', $dateFromFull)
            ->where('tx.performed_at <=', $dateTofull);

        if ($warehouseId !== null) {
            $txQuery->where('tx.warehouse_id', $warehouseId);
        }

        $txRows = $txQuery->get()->getResultArray();

        // 依 sku_id + warehouse_id 彙整異動
        $txMap = [];
        foreach ($txRows as $tx) {
            $key = $tx['sku_id'] . '_' . $tx['warehouse_id'];
            if (!isset($txMap[$key])) {
                $txMap[$key] = ['in' => 0.0, 'out' => 0.0, 'adjust' => 0.0];
            }
            $qty = (float) $tx['quantity'];
            switch ($tx['transaction_type']) {
                case 'IN':
                case 'TRANSFER_IN':
                    $txMap[$key]['in'] += $qty;
                    break;
                case 'OUT':
                case 'TRANSFER_OUT':
                    $txMap[$key]['out'] += abs($qty);
                    break;
                case 'ADJUST':
                case 'COUNT':
                    if ($qty >= 0) {
                        $txMap[$key]['adjust'] += $qty;
                    } else {
                        $txMap[$key]['adjust'] += $qty; // 負調整
                    }
                    break;
            }
        }

        $items           = [];
        $totalOpeningVal = 0.0;
        $totalClosingVal = 0.0;

        foreach ($skuRows as $row) {
            $key        = $row['sku_id'] . '_' . $row['warehouse_id'];
            $tx         = $txMap[$key] ?? ['in' => 0.0, 'out' => 0.0, 'adjust' => 0.0];
            $closing    = (float) $row['closing_qty'];
            $avgCost    = (float) $row['avg_cost'];
            // 期初 = 期末 - 本期入庫 + 本期出庫 - 調整
            $opening    = $closing - $tx['in'] + $tx['out'] - $tx['adjust'];
            $closingVal = $closing * $avgCost;
            $openingVal = $opening * $avgCost;

            $totalOpeningVal += $openingVal;
            $totalClosingVal += $closingVal;

            $items[] = [
                'sku_id'         => (int) $row['sku_id'],
                'sku_code'       => $row['sku_code'],
                'item_name'      => $row['item_name'],
                'warehouse_id'   => (int) $row['warehouse_id'],
                'warehouse_name' => $row['warehouse_name'],
                'opening_qty'    => round($opening, 4),
                'in_qty'         => round($tx['in'], 4),
                'out_qty'        => round($tx['out'], 4),
                'adjust_qty'     => round($tx['adjust'], 4),
                'closing_qty'    => round($closing, 4),
                'avg_cost'       => round($avgCost, 4),
                'closing_value'  => round($closingVal, 4),
            ];
        }

        return [
            'items'   => $items,
            'summary' => [
                'total_opening_value' => round($totalOpeningVal, 4),
                'total_closing_value' => round($totalClosingVal, 4),
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // T14-2  銷售業績報表
    // ────────────────────────────────────────────────────────────────

    /**
     * 銷售業績報表：依 SKU 彙整銷售量與銷售額
     *
     * @return array{
     *   by_sku: array,
     *   by_customer: array,
     *   summary: array{total_revenue: float, total_orders: int}
     * }
     */
    public function getSalesReport(
        string $dateFrom,
        string $dateTo,
        ?int   $customerId = null,
        ?int   $warehouseId = null,
    ): array {
        $dateFromFull = $dateFrom . ' 00:00:00';
        $dateTofull   = $dateTo  . ' 23:59:59';

        $baseQuery = $this->db
            ->table('sales_orders so')
            ->join('sales_order_lines sol', 'sol.sales_order_id = so.id')
            ->whereIn('so.status', ['confirmed', 'partial', 'shipped'])
            ->where('so.order_date >=', $dateFromFull)
            ->where('so.order_date <=', $dateTofull)
            ->whereNull('so.deleted_at');

        if ($customerId !== null) {
            $baseQuery->where('so.customer_id', $customerId);
        }
        if ($warehouseId !== null) {
            $baseQuery->where('so.warehouse_id', $warehouseId);
        }

        // 依 SKU 彙整
        $bySku = (clone $baseQuery)
            ->select('
                sol.sku_id,
                s.sku_code,
                it.name               AS item_name,
                SUM(sol.ordered_qty)  AS total_qty,
                SUM(sol.line_total)   AS total_amount,
                COUNT(DISTINCT so.id) AS order_count
            ')
            ->join('item_skus s', 's.id = sol.sku_id')
            ->join('items it',    'it.id = s.item_id')
            ->groupBy('sol.sku_id')
            ->orderBy('total_amount', 'DESC')
            ->get()->getResultArray();

        // 依客戶彙整
        $byCustomer = (clone $baseQuery)
            ->select('
                so.customer_id,
                c.name                AS customer_name,
                COUNT(DISTINCT so.id) AS order_count,
                SUM(so.total_amount)  AS total_amount
            ')
            ->join('customers c', 'c.id = so.customer_id')
            ->groupBy('so.customer_id')
            ->orderBy('total_amount', 'DESC')
            ->get()->getResultArray();

        // 彙總
        $summary = $this->db
            ->table('sales_orders')
            ->selectSum('total_amount', 'total_revenue')
            ->selectCount('id', 'total_orders')
            ->whereIn('status', ['confirmed', 'partial', 'shipped'])
            ->where('order_date >=', $dateFromFull)
            ->where('order_date <=', $dateTofull)
            ->whereNull('deleted_at');

        if ($customerId !== null) {
            $summary->where('customer_id', $customerId);
        }
        if ($warehouseId !== null) {
            $summary->where('warehouse_id', $warehouseId);
        }

        $summaryRow = $summary->get()->getRow();

        return [
            'by_sku'      => array_map(fn($r) => [
                'sku_id'       => (int) $r['sku_id'],
                'sku_code'     => $r['sku_code'],
                'item_name'    => $r['item_name'],
                'total_qty'    => (float) $r['total_qty'],
                'total_amount' => (float) $r['total_amount'],
                'order_count'  => (int) $r['order_count'],
            ], $bySku),
            'by_customer' => array_map(fn($r) => [
                'customer_id'   => (int) $r['customer_id'],
                'customer_name' => $r['customer_name'],
                'order_count'   => (int) $r['order_count'],
                'total_amount'  => (float) $r['total_amount'],
            ], $byCustomer),
            'summary' => [
                'total_revenue' => (float) ($summaryRow?->total_revenue ?? 0),
                'total_orders'  => (int)   ($summaryRow?->total_orders  ?? 0),
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // T14-3  採購報表
    // ────────────────────────────────────────────────────────────────

    /**
     * 採購報表：依廠商彙整採購金額、收貨數量
     *
     * @return array{by_supplier: array, by_item: array, summary: array}
     */
    public function getPurchaseReport(
        string $dateFrom,
        string $dateTo,
        ?int   $supplierId = null,
    ): array {
        $dateFromFull = $dateFrom . ' 00:00:00';
        $dateTofull   = $dateTo  . ' 23:59:59';

        $base = $this->db
            ->table('purchase_orders po')
            ->whereIn('po.status', ['approved', 'partial', 'received'])
            ->where('po.order_date >=', $dateFromFull)
            ->where('po.order_date <=', $dateTofull)
            ->whereNull('po.deleted_at');

        if ($supplierId !== null) {
            $base->where('po.supplier_id', $supplierId);
        }

        // 依廠商
        $bySupplier = (clone $base)
            ->select('
                po.supplier_id,
                s.name                AS supplier_name,
                COUNT(DISTINCT po.id) AS order_count,
                SUM(po.total_amount)  AS total_amount,
                SUM(po.paid_amount)   AS paid_amount
            ')
            ->join('suppliers s', 's.id = po.supplier_id')
            ->groupBy('po.supplier_id')
            ->orderBy('total_amount', 'DESC')
            ->get()->getResultArray();

        // 依品項
        $byItem = $this->db
            ->table('purchase_order_lines pol')
            ->select('
                pol.sku_id,
                s.sku_code,
                it.name                AS item_name,
                SUM(pol.ordered_qty)   AS total_ordered_qty,
                SUM(pol.received_qty)  AS total_received_qty,
                SUM(pol.line_total)    AS total_amount
            ')
            ->join('purchase_orders po', 'po.id = pol.purchase_order_id')
            ->join('item_skus s',        's.id  = pol.sku_id')
            ->join('items it',           'it.id = s.item_id')
            ->whereIn('po.status', ['approved', 'partial', 'received'])
            ->where('po.order_date >=', $dateFromFull)
            ->where('po.order_date <=', $dateTofull)
            ->whereNull('po.deleted_at');

        if ($supplierId !== null) {
            $byItem->where('po.supplier_id', $supplierId);
        }

        $byItemRows = $byItem
            ->groupBy('pol.sku_id')
            ->orderBy('total_amount', 'DESC')
            ->get()->getResultArray();

        // 彙總
        $summaryBase = $this->db
            ->table('purchase_orders')
            ->selectSum('total_amount', 'total_purchase')
            ->selectCount('id', 'total_orders')
            ->whereIn('status', ['approved', 'partial', 'received'])
            ->where('order_date >=', $dateFromFull)
            ->where('order_date <=', $dateTofull)
            ->whereNull('deleted_at');

        if ($supplierId !== null) {
            $summaryBase->where('supplier_id', $supplierId);
        }

        $summaryRow = $summaryBase->get()->getRow();

        return [
            'by_supplier' => array_map(fn($r) => [
                'supplier_id'   => (int) $r['supplier_id'],
                'supplier_name' => $r['supplier_name'],
                'order_count'   => (int) $r['order_count'],
                'total_amount'  => (float) $r['total_amount'],
                'paid_amount'   => (float) $r['paid_amount'],
            ], $bySupplier),
            'by_item' => array_map(fn($r) => [
                'sku_id'              => (int) $r['sku_id'],
                'sku_code'            => $r['sku_code'],
                'item_name'           => $r['item_name'],
                'total_ordered_qty'   => (float) $r['total_ordered_qty'],
                'total_received_qty'  => (float) $r['total_received_qty'],
                'total_amount'        => (float) $r['total_amount'],
            ], $byItemRows),
            'summary' => [
                'total_purchase' => (float) ($summaryRow?->total_purchase ?? 0),
                'total_orders'   => (int)   ($summaryRow?->total_orders   ?? 0),
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // T14-4  毛利分析報表
    // ────────────────────────────────────────────────────────────────

    /**
     * 毛利分析：銷售收入 - 銷售成本（出庫時的 avg_cost）
     *
     * @return array{
     *   daily: array<array{date: string, revenue: float, cogs: float, gross_profit: float, gross_margin: float}>,
     *   by_sku: array,
     *   summary: array{total_revenue: float, total_cogs: float, gross_profit: float, gross_margin: float}
     * }
     */
    public function getProfitReport(
        string $dateFrom,
        string $dateTo,
        ?int   $warehouseId = null,
    ): array {
        $dateFromFull = $dateFrom . ' 00:00:00';
        $dateTofull   = $dateTo  . ' 23:59:59';

        // 每日毛利（使用 inventory_transactions 出庫成本 + sales_order_lines 銷售額）
        $dailyQuery = $this->db
            ->table('sales_orders so')
            ->select('
                DATE(so.order_date)       AS date,
                SUM(so.total_amount)      AS revenue,
                SUM(
                    COALESCE((
                        SELECT SUM(ABS(tx.quantity) * tx.unit_cost)
                        FROM inventory_transactions tx
                        WHERE tx.source_document_type = \'sales_order\'
                          AND tx.source_document_id   = so.id
                          AND tx.transaction_type     = \'OUT\'
                    ), 0)
                )                         AS cogs
            ')
            ->whereIn('so.status', ['confirmed', 'partial', 'shipped'])
            ->where('so.order_date >=', $dateFromFull)
            ->where('so.order_date <=', $dateTofull)
            ->whereNull('so.deleted_at');

        if ($warehouseId !== null) {
            $dailyQuery->where('so.warehouse_id', $warehouseId);
        }

        $dailyRows = $dailyQuery
            ->groupBy('DATE(so.order_date)')
            ->orderBy('date', 'ASC')
            ->get()->getResultArray();

        $daily = array_map(function ($r) {
            $revenue      = (float) $r['revenue'];
            $cogs         = (float) $r['cogs'];
            $grossProfit  = $revenue - $cogs;
            $grossMargin  = $revenue > 0 ? round($grossProfit / $revenue * 100, 2) : 0.0;
            return [
                'date'         => $r['date'],
                'revenue'      => round($revenue, 4),
                'cogs'         => round($cogs, 4),
                'gross_profit' => round($grossProfit, 4),
                'gross_margin' => $grossMargin,
            ];
        }, $dailyRows);

        // 依 SKU 毛利
        $bySkuQuery = $this->db
            ->table('sales_order_lines sol')
            ->select('
                sol.sku_id,
                sk.sku_code,
                it.name               AS item_name,
                SUM(sol.ordered_qty)  AS sold_qty,
                SUM(sol.line_total)   AS revenue,
                SUM(
                    COALESCE((
                        SELECT SUM(ABS(tx2.quantity) * tx2.unit_cost)
                        FROM inventory_transactions tx2
                        WHERE tx2.source_document_type = \'sales_order\'
                          AND tx2.source_document_id   = sol.sales_order_id
                          AND tx2.sku_id               = sol.sku_id
                          AND tx2.transaction_type     = \'OUT\'
                    ), 0)
                )                     AS cogs
            ')
            ->join('sales_orders so', 'so.id = sol.sales_order_id')
            ->join('item_skus sk',    'sk.id = sol.sku_id')
            ->join('items it',        'it.id = sk.item_id')
            ->whereIn('so.status', ['confirmed', 'partial', 'shipped'])
            ->where('so.order_date >=', $dateFromFull)
            ->where('so.order_date <=', $dateTofull)
            ->whereNull('so.deleted_at');

        if ($warehouseId !== null) {
            $bySkuQuery->where('so.warehouse_id', $warehouseId);
        }

        $bySkuRows = $bySkuQuery
            ->groupBy('sol.sku_id')
            ->orderBy('revenue', 'DESC')
            ->get()->getResultArray();

        $bySku = array_map(function ($r) {
            $revenue     = (float) $r['revenue'];
            $cogs        = (float) $r['cogs'];
            $grossProfit = $revenue - $cogs;
            $grossMargin = $revenue > 0 ? round($grossProfit / $revenue * 100, 2) : 0.0;
            return [
                'sku_id'       => (int) $r['sku_id'],
                'sku_code'     => $r['sku_code'],
                'item_name'    => $r['item_name'],
                'sold_qty'     => (float) $r['sold_qty'],
                'revenue'      => round($revenue, 4),
                'cogs'         => round($cogs, 4),
                'gross_profit' => round($grossProfit, 4),
                'gross_margin' => $grossMargin,
            ];
        }, $bySkuRows);

        // 總彙整
        $totalRevenue = array_sum(array_column($daily, 'revenue'));
        $totalCogs    = array_sum(array_column($daily, 'cogs'));
        $totalGP      = $totalRevenue - $totalCogs;
        $totalMargin  = $totalRevenue > 0 ? round($totalGP / $totalRevenue * 100, 2) : 0.0;

        return [
            'daily'   => $daily,
            'by_sku'  => $bySku,
            'summary' => [
                'total_revenue' => round($totalRevenue, 4),
                'total_cogs'    => round($totalCogs, 4),
                'gross_profit'  => round($totalGP, 4),
                'gross_margin'  => $totalMargin,
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // T14-5  庫存週轉率報表
    // ────────────────────────────────────────────────────────────────

    /**
     * 庫存週轉率 = 期間銷售成本 / 平均庫存成本
     *
     * @return array{items: array, summary: array}
     */
    public function getTurnoverRate(
        string $dateFrom,
        string $dateTo,
        ?int   $warehouseId = null,
    ): array {
        $dateFromFull = $dateFrom . ' 00:00:00';
        $dateTofull   = $dateTo  . ' 23:59:59';

        // 出庫成本（OUT type）
        $cogQuery = $this->db
            ->table('inventory_transactions tx')
            ->select('
                tx.sku_id,
                tx.warehouse_id,
                SUM(ABS(tx.quantity) * tx.unit_cost) AS cogs
            ')
            ->where('tx.transaction_type', 'OUT')
            ->where('tx.performed_at >=', $dateFromFull)
            ->where('tx.performed_at <=', $dateTofull)
            ->groupBy(['tx.sku_id', 'tx.warehouse_id']);

        if ($warehouseId !== null) {
            $cogQuery->where('tx.warehouse_id', $warehouseId);
        }

        $cogRows = $cogQuery->get()->getResultArray();
        $cogMap  = [];
        foreach ($cogRows as $r) {
            $cogMap[$r['sku_id'] . '_' . $r['warehouse_id']] = (float) $r['cogs'];
        }

        // 當前庫存（用作期末平均估計）
        $invQuery = $this->db
            ->table('inventory inv')
            ->select('
                inv.sku_id,
                inv.warehouse_id,
                s.sku_code,
                it.name     AS item_name,
                w.name      AS warehouse_name,
                inv.on_hand_qty,
                inv.avg_cost
            ')
            ->join('item_skus s',  's.id = inv.sku_id')
            ->join('items it',     'it.id = s.item_id')
            ->join('warehouses w', 'w.id  = inv.warehouse_id')
            ->whereNull('it.deleted_at')
            ->whereNull('w.deleted_at');

        if ($warehouseId !== null) {
            $invQuery->where('inv.warehouse_id', $warehouseId);
        }

        $invRows = $invQuery->get()->getResultArray();

        $items = [];
        foreach ($invRows as $row) {
            $key          = $row['sku_id'] . '_' . $row['warehouse_id'];
            $cogs         = $cogMap[$key] ?? 0.0;
            $currentValue = (float) $row['on_hand_qty'] * (float) $row['avg_cost'];
            // 簡化：以期末庫存價值作為平均庫存
            $turnoverRate = $currentValue > 0 ? round($cogs / $currentValue, 4) : null;
            // 週轉天數（期間天數 / 週轉率）
            $periodDays   = max(1, (int) ceil((strtotime($dateTo) - strtotime($dateFrom)) / 86400));
            $daysTurnover = $turnoverRate !== null && $turnoverRate > 0
                ? round($periodDays / $turnoverRate, 1)
                : null;

            $items[] = [
                'sku_id'        => (int) $row['sku_id'],
                'sku_code'      => $row['sku_code'],
                'item_name'     => $row['item_name'],
                'warehouse_id'  => (int) $row['warehouse_id'],
                'warehouse_name'=> $row['warehouse_name'],
                'cogs'          => round($cogs, 4),
                'current_value' => round($currentValue, 4),
                'turnover_rate' => $turnoverRate,
                'days_turnover' => $daysTurnover,
            ];
        }

        // 依週轉率排序（高 → 低）
        usort($items, fn($a, $b) => ($b['turnover_rate'] ?? -INF) <=> ($a['turnover_rate'] ?? -INF));

        return [
            'items'   => $items,
            'summary' => [
                'total_cogs'          => round(array_sum(array_column($items, 'cogs')), 4),
                'total_inventory_val' => round(array_sum(array_column($items, 'current_value')), 4),
            ],
        ];
    }

    // ────────────────────────────────────────────────────────────────
    // T14-6  Excel 匯出
    // ────────────────────────────────────────────────────────────────

    /**
     * 進銷存彙總表 Excel 匯出
     * 回傳暫存檔路徑
     */
    public function exportInventorySummaryExcel(array $reportData): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('進銷存彙總');

        // 標題列
        $headers = ['SKU 編號', '品名', '倉庫', '期初庫存', '入庫', '出庫', '調整', '期末庫存', '平均成本', '期末庫存值'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DBEAFE');
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // 資料列
        foreach ($reportData['items'] as $i => $item) {
            $row = $i + 2;
            $sheet->setCellValue("A{$row}", $item['sku_code']);
            $sheet->setCellValue("B{$row}", $item['item_name']);
            $sheet->setCellValue("C{$row}", $item['warehouse_name']);
            $sheet->setCellValue("D{$row}", $item['opening_qty']);
            $sheet->setCellValue("E{$row}", $item['in_qty']);
            $sheet->setCellValue("F{$row}", $item['out_qty']);
            $sheet->setCellValue("G{$row}", $item['adjust_qty']);
            $sheet->setCellValue("H{$row}", $item['closing_qty']);
            $sheet->setCellValue("I{$row}", $item['avg_cost']);
            $sheet->setCellValue("J{$row}", $item['closing_value']);
        }

        // 自動欄寬
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // 匯總列
        if (!empty($reportData['items'])) {
            $lastRow = count($reportData['items']) + 2;
            $sheet->setCellValue("A{$lastRow}", '合計')
                  ->setCellValue("J{$lastRow}", $reportData['summary']['total_closing_value']);
            $sheet->getStyle("A{$lastRow}")->getFont()->setBold(true);
        }

        $tmpPath = sys_get_temp_dir() . '/inventory_summary_' . uniqid() . '.xlsx';
        $writer  = new Xlsx($spreadsheet);
        $writer->save($tmpPath);

        return $tmpPath;
    }

    /**
     * 銷售業績報表 Excel 匯出
     */
    public function exportSalesReportExcel(array $reportData): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('銷售業績');

        $headers = ['SKU 編號', '品名', '銷售數量', '銷售金額', '訂單數'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('D1FAE5');
        }

        foreach ($reportData['by_sku'] as $i => $item) {
            $row = $i + 2;
            $sheet->setCellValue("A{$row}", $item['sku_code']);
            $sheet->setCellValue("B{$row}", $item['item_name']);
            $sheet->setCellValue("C{$row}", $item['total_qty']);
            $sheet->setCellValue("D{$row}", $item['total_amount']);
            $sheet->setCellValue("E{$row}", $item['order_count']);
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmpPath = sys_get_temp_dir() . '/sales_report_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return $tmpPath;
    }

    /**
     * 採購報表 Excel 匯出
     */
    public function exportPurchaseReportExcel(array $reportData): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('採購報表');

        $headers = ['廠商名稱', '訂單數', '採購總額', '已付金額'];
        foreach ($headers as $col => $header) {
            $cell = chr(65 + $col) . '1';
            $sheet->setCellValue($cell, $header);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FEF3C7');
        }

        foreach ($reportData['by_supplier'] as $i => $item) {
            $row = $i + 2;
            $sheet->setCellValue("A{$row}", $item['supplier_name']);
            $sheet->setCellValue("B{$row}", $item['order_count']);
            $sheet->setCellValue("C{$row}", $item['total_amount']);
            $sheet->setCellValue("D{$row}", $item['paid_amount']);
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmpPath = sys_get_temp_dir() . '/purchase_report_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return $tmpPath;
    }
}
