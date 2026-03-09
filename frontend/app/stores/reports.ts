// app/stores/reports.ts
import { defineStore } from 'pinia'
import type {
  DashboardKpi,
  SalesTrendItem,
  InventorySummaryReport,
  SalesReport,
  PurchaseReport,
  ProfitReport,
  TurnoverRateItem,
} from '~/app/types/api'

interface ReportsState {
  kpi: DashboardKpi | null
  salesTrend: SalesTrendItem[]
  inventorySummary: InventorySummaryReport | null
  salesReport: SalesReport | null
  purchaseReport: PurchaseReport | null
  profitReport: ProfitReport | null
  turnoverRate: TurnoverRateItem[]
  loading: boolean
  error: string | null
}

export const useReportsStore = defineStore('reports', {
  state: (): ReportsState => ({
    kpi: null,
    salesTrend: [],
    inventorySummary: null,
    salesReport: null,
    purchaseReport: null,
    profitReport: null,
    turnoverRate: [],
    loading: false,
    error: null,
  }),

  actions: {
    async fetchDashboardKpi() {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<DashboardKpi>('/reports/dashboard-kpi')
        this.kpi = res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入 KPI 失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchSalesTrend(days = 30) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<SalesTrendItem[]>('/reports/sales-trend', { days })
        this.salesTrend = res.data ?? []
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入銷售趨勢失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchInventorySummary(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<InventorySummaryReport>('/reports/inventory-summary', params)
        this.inventorySummary = res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入進銷存彙總失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchSalesReport(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<SalesReport>('/reports/sales', params)
        this.salesReport = res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入銷售報表失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchPurchaseReport(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<PurchaseReport>('/reports/purchase', params)
        this.purchaseReport = res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入採購報表失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchProfitReport(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<ProfitReport>('/reports/profit', params)
        this.profitReport = res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入毛利報表失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchTurnoverRate(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<{ items: TurnoverRateItem[]; summary: unknown }>('/reports/turnover-rate', params)
        this.turnoverRate = (res.data as any)?.items ?? []
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入週轉率失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    /** 觸發 Excel 匯出（開啟下載連結） */
    downloadExport(type: 'inventory-summary' | 'sales' | 'purchase', params: Record<string, unknown> = {}) {
      const config  = useRuntimeConfig()
      const authStore = useAuthStore()
      const query   = new URLSearchParams(
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== null).map(([k, v]) => [k, String(v)]))
      ).toString()
      const url     = `${config.public.apiBase}/reports/${type}/export${query ? '?' + query : ''}`

      // 建立隱藏 <a> 並攜帶 Authorization header（透過 fetch + Blob）
      const token = authStore.token
      fetch(url, { headers: { Authorization: `Bearer ${token}` } })
        .then(r => r.blob())
        .then(blob => {
          const a  = document.createElement('a')
          a.href   = URL.createObjectURL(blob)
          a.download = `${type}_export.xlsx`
          a.click()
          URL.revokeObjectURL(a.href)
        })
    },
  },
})
