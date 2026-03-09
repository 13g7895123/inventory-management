import { defineStore } from 'pinia'
import type { SalesReturn, SalesReturnLine } from '~/app/types/api'

interface SalesReturnsState {
  returns: SalesReturn[]
  current: SalesReturn | null
  currentLines: SalesReturnLine[]
  loading: boolean
  saving: boolean
  confirming: boolean
  cancelling: boolean
  error: string | null
}

export const useSalesReturnStore = defineStore('salesReturns', {
  state: (): SalesReturnsState => ({
    returns: [],
    current: null,
    currentLines: [],
    loading: false,
    saving: false,
    confirming: false,
    cancelling: false,
    error: null,
  }),

  actions: {
    async fetchByOrder(salesOrderId: number) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<SalesReturn[]>(`/sales-orders/${salesOrderId}/returns`)
        this.returns = Array.isArray(res.data) ? res.data : []
        return this.returns
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入退貨單失敗'
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
        const res = await get<{ sales_return: SalesReturn; lines: SalesReturnLine[] }>(`/sales-returns/${id}`)
        this.current = res.data.sales_return
        this.currentLines = res.data.lines
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入退貨單失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async create(salesOrderId: number, data: Record<string, unknown>) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<SalesReturn>(`/sales-orders/${salesOrderId}/returns`, data)
        this.returns.unshift(res.data)
        return res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立退貨單失敗'
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
        const res = await post<SalesReturn>(`/sales-returns/${id}/confirm`)
        if (this.current?.id === id) this.current = res.data
        return res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '確認退貨單失敗'
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
        const res = await post<SalesReturn>(`/sales-returns/${id}/cancel`)
        if (this.current?.id === id) this.current = res.data
        return res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '取消退貨單失敗'
        throw e
      } finally {
        this.cancelling = false
      }
    },
  },
})
