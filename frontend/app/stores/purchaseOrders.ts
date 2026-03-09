import { defineStore } from 'pinia'
import type {
  PurchaseOrder,
  PaginationMeta,
  ReceiveLineForm,
  PurchasePayment,
  PurchasePaymentForm,
  PurchaseReturn,
  PurchaseReturnLine,
} from '~/app/types/api'

interface PurchaseOrdersState {
  orders: PurchaseOrder[]
  pagination: PaginationMeta | null
  current: PurchaseOrder | null
  payments: PurchasePayment[]
  returns: PurchaseReturn[]
  currentReturn: PurchaseReturn | null
  currentReturnLines: PurchaseReturnLine[]
  loading: boolean
  saving: boolean
  submitting: boolean
  approving: boolean
  cancelling: boolean
  receiving: boolean
  paymentSaving: boolean
  returnSaving: boolean
  returnConfirming: boolean
  error: string | null
}

export const usePurchaseOrderStore = defineStore('purchaseOrders', {
  state: (): PurchaseOrdersState => ({
    orders: [],
    pagination: null,
    current: null,
    payments: [],
    returns: [],
    currentReturn: null,
    currentReturnLines: [],
    loading: false,
    saving: false,
    submitting: false,
    approving: false,
    cancelling: false,
    receiving: false,
    paymentSaving: false,
    returnSaving: false,
    returnConfirming: false,
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

    // ── 付款記錄 ──────────────────────────────────────────────────

    async fetchPayments(orderId: number) {
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<PurchasePayment[]>(`/purchase-orders/${orderId}/payments`)
        this.payments = Array.isArray(res) ? res : (res as { data: PurchasePayment[] }).data ?? []
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入付款記錄失敗'
        throw e
      }
    },

    async addPayment(orderId: number, form: PurchasePaymentForm) {
      this.paymentSaving = true
      this.error = null
      try {
        const { post, get } = useApi()
        const res = await post<PurchasePayment>(`/purchase-orders/${orderId}/payments`, form)
        this.payments.unshift(res)
        // 重新載入採購單以更新 payment_status / paid_amount
        const updated = await get<PurchaseOrder>(`/purchase-orders/${orderId}`)
        const po = (updated as { purchase_order?: PurchaseOrder }).purchase_order ?? updated as PurchaseOrder
        this.current = po
        this._updateInList(po)
        return res
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '新增付款記錄失敗'
        throw e
      } finally {
        this.paymentSaving = false
      }
    },

    // ── 採購退貨 ──────────────────────────────────────────────────

    async fetchReturns(orderId: number) {
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<PurchaseReturn[]>(`/purchase-orders/${orderId}/returns`)
        this.returns = Array.isArray(res) ? res : (res as { data: PurchaseReturn[] }).data ?? []
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入退貨記錄失敗'
        throw e
      }
    },

    async createReturn(orderId: number, data: Record<string, unknown>) {
      this.returnSaving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<PurchaseReturn>(`/purchase-orders/${orderId}/returns`, data)
        this.returns.unshift(res)
        return res
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立退貨單失敗'
        throw e
      } finally {
        this.returnSaving = false
      }
    },

    async confirmReturn(returnId: number) {
      this.returnConfirming = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<PurchaseReturn>(`/purchase-returns/${returnId}/confirm`, {})
        const idx = this.returns.findIndex(r => r.id === returnId)
        if (idx !== -1) this.returns[idx] = res
        if (this.currentReturn?.id === returnId) this.currentReturn = res
        return res
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '確認退貨單失敗'
        throw e
      } finally {
        this.returnConfirming = false
      }
    },

    async cancelReturn(returnId: number) {
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<PurchaseReturn>(`/purchase-returns/${returnId}/cancel`, {})
        const idx = this.returns.findIndex(r => r.id === returnId)
        if (idx !== -1) this.returns[idx] = res
        if (this.currentReturn?.id === returnId) this.currentReturn = res
        return res
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '取消退貨單失敗'
        throw e
      }
    },
  },
})
