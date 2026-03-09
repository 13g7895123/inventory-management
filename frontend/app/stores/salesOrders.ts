import { defineStore } from 'pinia'
import type { SalesOrder, SalesOrderLine, Shipment, Pagination } from '~/app/types/api'

interface SalesOrdersState {
  orders: SalesOrder[]
  pagination: Pagination | null
  current: SalesOrder | null
  currentLines: SalesOrderLine[]
  shipments: Shipment[]
  loading: boolean
  saving: boolean
  confirming: boolean
  cancelling: boolean
  error: string | null
}

export const useSalesOrderStore = defineStore('salesOrders', {
  state: (): SalesOrdersState => ({
    orders: [],
    pagination: null,
    current: null,
    currentLines: [],
    shipments: [],
    loading: false,
    saving: false,
    confirming: false,
    cancelling: false,
    error: null,
  }),

  actions: {
    async fetchList(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { getPaginated } = useApi()
        const res = await getPaginated<SalesOrder>('/sales-orders', params)
        this.orders = res.data
        this.pagination = res.pagination
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入銷售訂單失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchOne(id: number) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<{ sales_order: SalesOrder; lines: SalesOrderLine[] }>(`/sales-orders/${id}`)
        this.current = res.data.sales_order
        this.currentLines = res.data.lines
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入銷售訂單失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async create(data: Record<string, unknown>) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<SalesOrder>('/sales-orders', data)
        return res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立銷售訂單失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async confirm(id: number) {
      this.confirming = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<SalesOrder>(`/sales-orders/${id}/confirm`)
        this.current = res.data
        return res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '確認銷售訂單失敗'
        throw e
      } finally {
        this.confirming = false
      }
    },

    async cancel(id: number) {
      this.cancelling = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<SalesOrder>(`/sales-orders/${id}/cancel`)
        this.current = res.data
        return res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '取消銷售訂單失敗'
        throw e
      } finally {
        this.cancelling = false
      }
    },

    async fetchShipments(id: number) {
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<Shipment[]>(`/sales-orders/${id}/shipments`)
        this.shipments = Array.isArray(res.data) ? res.data : []
        return this.shipments
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入出貨單失敗'
        throw e
      }
    },
  },
})
