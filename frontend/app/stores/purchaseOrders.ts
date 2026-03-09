import { defineStore } from 'pinia'
import type { PurchaseOrder, PaginationMeta, ReceiveLineForm } from '~/app/types/api'

interface PurchaseOrdersState {
  orders: PurchaseOrder[]
  pagination: PaginationMeta | null
  current: PurchaseOrder | null
  loading: boolean
  saving: boolean
  submitting: boolean
  approving: boolean
  cancelling: boolean
  receiving: boolean
  error: string | null
}

export const usePurchaseOrderStore = defineStore('purchaseOrders', {
  state: (): PurchaseOrdersState => ({
    orders: [],
    pagination: null,
    current: null,
    loading: false,
    saving: false,
    submitting: false,
    approving: false,
    cancelling: false,
    receiving: false,
    error: null,
  }),

  actions: {
    async fetchList(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { getPaginated } = useApi()
        const res = await getPaginated<PurchaseOrder>('/purchase-orders', params)
        this.orders = res.data
        this.pagination = res.meta
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入採購單失敗'
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
        const res = await get<PurchaseOrder>(`/purchase-orders/${id}`)
        this.current = res
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入採購單失敗'
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
        const res = await post<PurchaseOrder>('/purchase-orders', data)
        this.orders.unshift(res)
        return res
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立採購單失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async submit(id: number) {
      this.submitting = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<PurchaseOrder>(`/purchase-orders/${id}/submit`, {})
        this.current = res
        this._updateInList(res)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '提交審核失敗'
        throw e
      } finally {
        this.submitting = false
      }
    },

    async approve(id: number) {
      this.approving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<PurchaseOrder>(`/purchase-orders/${id}/approve`, {})
        this.current = res
        this._updateInList(res)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '核准失敗'
        throw e
      } finally {
        this.approving = false
      }
    },

    async cancel(id: number) {
      this.cancelling = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<PurchaseOrder>(`/purchase-orders/${id}/cancel`, {})
        this.current = res
        this._updateInList(res)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '取消失敗'
        throw e
      } finally {
        this.cancelling = false
      }
    },

    async receive(id: number, lines: ReceiveLineForm[]) {
      this.receiving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<PurchaseOrder>(`/purchase-orders/${id}/receive`, { lines })
        this.current = res
        this._updateInList(res)
        return res
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '驗收失敗'
        throw e
      } finally {
        this.receiving = false
      }
    },

    _updateInList(order: PurchaseOrder) {
      const idx = this.orders.findIndex(o => o.id === order.id)
      if (idx !== -1) this.orders[idx] = order
    },
  },
})
