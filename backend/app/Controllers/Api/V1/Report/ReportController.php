<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Report;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\ReportService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ReportController — 報表 API
 *
 * Routes:
 *   GET  /api/v1/reports/dashboard-kpi
 *   GET  /api/v1/reports/sales-trend
 *   GET  /api/v1/reports/inventory-summary
 *   GET  /api/v1/reports/inventory-summary/export
 *   GET  /api/v1/reports/sales
 *   GET  /api/v1/reports/sales/export
 *   GET  /api/v1/reports/purchase
 *   GET  /api/v1/reports/purchase/export
 *   GET  /api/v1/reports/profit
 *   GET  /api/v1/reports/turnover-rate
 */
class ReportController extends BaseApiController
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {
    }

    // ── T15-1  儀表板 KPI ────────────────────────────────────────

    /**
     * GET /api/v1/reports/dashboard-kpi
     */
    public function dashboardKpi(): ResponseInterface
    {
        $data = $this->reportService->getDashboardKpi();
        return api_success($data);
    }

    // ── T15-2  銷售趨勢 ──────────────────────────────────────────

    /**
     * GET /api/v1/reports/sales-trend?days=30
     */
    public function salesTrend(): ResponseInterface
    {
        $days = min(365, max(7, (int) ($this->request->getGet('days') ?? 30)));
        $data = $this->reportService->getSalesTrend($days);
        return api_success($data);
    }

    // ── T14-1 / T15-3  進銷存彙總表 ──────────────────────────────

    /**
     * GET /api/v1/reports/inventory-summary?date_from=&date_to=&warehouse_id=&q=
     */
    public function inventorySummary(): ResponseInterface
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        $warehouseId = $this->toNullableInt($this->request->getGet('warehouse_id'));
        $search      = $this->request->getGet('q') ?: null;

        $data = $this->reportService->getInventorySummary($dateFrom, $dateTo, $warehouseId, $search);
        return api_success($data);
    }

    /**
     * GET /api/v1/reports/inventory-summary/export
     */
    public function exportInventorySummary(): ResponseInterface
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        $warehouseId = $this->toNullableInt($this->request->getGet('warehouse_id'));

        $data    = $this->reportService->getInventorySummary($dateFrom, $dateTo, $warehouseId);
        $tmpPath = $this->reportService->exportInventorySummaryExcel($data);

        $filename = "inventory_summary_{$dateFrom}_{$dateTo}.xlsx";
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->setBody(file_get_contents($tmpPath));
    }

    // ── T14-2 / T15-4  銷售業績報表 ──────────────────────────────

    /**
     * GET /api/v1/reports/sales?date_from=&date_to=&customer_id=&warehouse_id=
     */
    public function salesReport(): ResponseInterface
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        $customerId  = $this->toNullableInt($this->request->getGet('customer_id'));
        $warehouseId = $this->toNullableInt($this->request->getGet('warehouse_id'));

        $data = $this->reportService->getSalesReport($dateFrom, $dateTo, $customerId, $warehouseId);
        return api_success($data);
    }

    /**
     * GET /api/v1/reports/sales/export
     */
    public function exportSalesReport(): ResponseInterface
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        $customerId  = $this->toNullableInt($this->request->getGet('customer_id'));
        $warehouseId = $this->toNullableInt($this->request->getGet('warehouse_id'));

        $data    = $this->reportService->getSalesReport($dateFrom, $dateTo, $customerId, $warehouseId);
        $tmpPath = $this->reportService->exportSalesReportExcel($data);

        $filename = "sales_report_{$dateFrom}_{$dateTo}.xlsx";
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->setBody(file_get_contents($tmpPath));
    }

    // ── T14-3 / T15-6  採購報表 ──────────────────────────────────

    /**
     * GET /api/v1/reports/purchase?date_from=&date_to=&supplier_id=
     */
    public function purchaseReport(): ResponseInterface
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        $supplierId = $this->toNullableInt($this->request->getGet('supplier_id'));

        $data = $this->reportService->getPurchaseReport($dateFrom, $dateTo, $supplierId);
        return api_success($data);
    }

    /**
     * GET /api/v1/reports/purchase/export
     */
    public function exportPurchaseReport(): ResponseInterface
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        $supplierId = $this->toNullableInt($this->request->getGet('supplier_id'));

        $data    = $this->reportService->getPurchaseReport($dateFrom, $dateTo, $supplierId);
        $tmpPath = $this->reportService->exportPurchaseReportExcel($data);

        $filename = "purchase_report_{$dateFrom}_{$dateTo}.xlsx";
        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->setBody(file_get_contents($tmpPath));
    }

    // ── T14-4 / T15-5  毛利分析報表 ──────────────────────────────

    /**
     * GET /api/v1/reports/profit?date_from=&date_to=&warehouse_id=
     */
    public function profitReport(): ResponseInterface
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        $warehouseId = $this->toNullableInt($this->request->getGet('warehouse_id'));

        $data = $this->reportService->getProfitReport($dateFrom, $dateTo, $warehouseId);
        return api_success($data);
    }

    // ── T14-5  庫存週轉率 ────────────────────────────────────────

    /**
     * GET /api/v1/reports/turnover-rate?date_from=&date_to=&warehouse_id=
     */
    public function turnoverRate(): ResponseInterface
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange();
        $warehouseId = $this->toNullableInt($this->request->getGet('warehouse_id'));

        $data = $this->reportService->getTurnoverRate($dateFrom, $dateTo, $warehouseId);
        return api_success($data);
    }

    // ── 私有輔助 ─────────────────────────────────────────────────

    /**
     * 解析日期範圍：預設為本月
     *
     * @return array{string, string}  [dateFrom, dateTo]  格式 Y-m-d
     */
    private function resolveDateRange(): array
    {
        $dateFrom = $this->request->getGet('date_from') ?: date('Y-m-01');
        $dateTo   = $this->request->getGet('date_to')   ?: date('Y-m-d');

        // 基本格式驗證與防注入
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = date('Y-m-d');
        }

        return [$dateFrom, $dateTo];
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }
}
