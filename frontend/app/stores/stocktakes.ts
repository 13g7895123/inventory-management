// app/stores/stocktakes.ts
import { defineStore } from 'pinia'
import type { Stocktake, StocktakeLine } from '~/app/types/api'

interface StocktakesState {
  stocktakes: Stocktake[]
  total: number
  current: Stocktake | null
  lines: StocktakeLine[]
  loading: boolean
  saving: boolean
  error: string | null
}

export const useStocktakesStore = defineStore('stocktakes', {
  state: (): StocktakesState => ({
    stocktakes: [],
    total: 0,
    current: null,
    lines: [],
    loading: false,
    saving: false,
    error: null,
  }),

  actions: {
    async fetchList(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<{ data: Stocktake[]; total: number }>('/stocktakes', params)
        this.stocktakes = res.data?.data ?? []
        this.total = res.data?.total ?? 0
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入盤點列表失敗'
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
        const res = await get<{ stocktake: Stocktake; lines: StocktakeLine[] }>(`/stocktakes/${id}`)
        if (res.data) {
          this.current = res.data.stocktake
          this.lines = res.data.lines ?? []
        }
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入盤點任務失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async create(data: { warehouse_id: number; notes?: string }): Promise<Stocktake> {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<Stocktake>('/stocktakes', data)
        if (res.success && res.data) {
          this.stocktakes.unshift(res.data)
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立盤點任務失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async start(id: number) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<Stocktake>(`/stocktakes/${id}/start`, {})
        if (res.success && res.data) {
          const idx = this.stocktakes.findIndex(s => s.id === id)
          if (idx !== -1) this.stocktakes[idx] = res.data
          if (this.current?.id === id) this.current = res.data
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '開始盤點失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async updateCount(id: number, sku_id: number, actual_qty: number) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<StocktakeLine>(`/stocktakes/${id}/count`, { sku_id, actual_qty })
        if (res.success && res.data) {
          const idx = this.lines.findIndex(l => l.sku_id === sku_id)
          if (idx !== -1) this.lines[idx] = res.data
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '更新盤點數量失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async confirm(id: number) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<Stocktake>(`/stocktakes/${id}/confirm`, {})
        if (res.success && res.data) {
          const idx = this.stocktakes.findIndex(s => s.id === id)
          if (idx !== -1) this.stocktakes[idx] = res.data
          if (this.current?.id === id) this.current = res.data
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '確認盤點失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async cancel(id: number) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<Stocktake>(`/stocktakes/${id}/cancel`, {})
        if (res.success && res.data) {
          const idx = this.stocktakes.findIndex(s => s.id === id)
          if (idx !== -1) this.stocktakes[idx] = res.data
          if (this.current?.id === id) this.current = res.data
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '取消盤點失敗'
        throw e
      } finally {
        this.saving = false
      }
    },
  },
})
