// app/stores/batchSerials.ts
import { defineStore } from 'pinia'
import type { BatchSerial } from '~/app/types/api'

interface BatchSerialsState {
  items: BatchSerial[]
  total: number
  loading: boolean
  error: string | null
}

export const useBatchSerialsStore = defineStore('batchSerials', {
  state: (): BatchSerialsState => ({
    items: [],
    total: 0,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchList(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<{ data: BatchSerial[]; total: number; page: number }>('/batch-serials', params)
        this.items = res.data?.data ?? []
        this.total = res.data?.total ?? 0
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入批號資料失敗'
        throw e
      } finally {
        this.loading = false
      }
    },
  },
})
