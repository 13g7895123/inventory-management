import { defineStore } from 'pinia'
import type { SalesPayment, SalesPaymentForm } from '~/app/types/api'

interface SalesPaymentsState {
  payments: SalesPayment[]
  loading: boolean
  saving: boolean
  error: string | null
}

export const useSalesPaymentStore = defineStore('salesPayments', {
  state: (): SalesPaymentsState => ({
    payments: [],
    loading: false,
    saving: false,
    error: null,
  }),

  actions: {
    async fetchList(salesOrderId: number) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<SalesPayment[]>(`/sales-orders/${salesOrderId}/payments`)
        this.payments = Array.isArray(res.data) ? res.data : []
        return this.payments
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入收款記錄失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async create(salesOrderId: number, data: SalesPaymentForm) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<SalesPayment>(`/sales-orders/${salesOrderId}/payments`, data)
        this.payments.unshift(res.data)
        return res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '新增收款記錄失敗'
        throw e
      } finally {
        this.saving = false
      }
    },
  },
})
