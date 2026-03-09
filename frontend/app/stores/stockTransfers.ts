// app/stores/stockTransfers.ts
import { defineStore } from 'pinia'
import type { StockTransfer, StockTransferLine, StockTransferLineForm } from '~/app/types/api'

interface StockTransfersState {
  transfers: StockTransfer[]
  total: number
  current: StockTransfer | null
  lines: StockTransferLine[]
  loading: boolean
  saving: boolean
  confirming: boolean
  cancelling: boolean
  error: string | null
}

export const useStockTransfersStore = defineStore('stockTransfers', {
  state: (): StockTransfersState => ({
    transfers: [],
    total: 0,
    current: null,
    lines: [],
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
        const { get } = useApi()
        const res = await get<{ data: StockTransfer[]; total: number }>('/stock-transfers', params)
        this.transfers = res.data?.data ?? []
        this.total = res.data?.total ?? 0
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入調撥列表失敗'
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
        const res = await get<{ transfer: StockTransfer; lines: StockTransferLine[] }>(`/stock-transfers/${id}`)
        if (res.data) {
          this.current = res.data.transfer
          this.lines = res.data.lines ?? []
        }
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入調撥單失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async create(data: {
      from_warehouse_id: number
      to_warehouse_id: number
      reason?: string
      notes?: string
      lines: StockTransferLineForm[]
    }): Promise<StockTransfer> {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<StockTransfer>('/stock-transfers', data)
        if (res.success && res.data) {
          this.transfers.unshift(res.data)
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立調撥單失敗'
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
        const res = await post<StockTransfer>(`/stock-transfers/${id}/confirm`, {})
        if (res.success && res.data) {
          const idx = this.transfers.findIndex(t => t.id === id)
          if (idx !== -1) this.transfers[idx] = res.data
          if (this.current?.id === id) this.current = res.data
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '確認調撥失敗'
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
        const res = await post<StockTransfer>(`/stock-transfers/${id}/cancel`, {})
        if (res.success && res.data) {
          const idx = this.transfers.findIndex(t => t.id === id)
          if (idx !== -1) this.transfers[idx] = res.data
          if (this.current?.id === id) this.current = res.data
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '取消調撥失敗'
        throw e
      } finally {
        this.cancelling = false
      }
    },
  },
})
