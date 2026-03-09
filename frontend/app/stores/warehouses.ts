// app/stores/warehouses.ts
import { defineStore } from 'pinia'
import type { Warehouse, Pagination } from '~/app/types/api'

interface WarehouseState {
  warehouses: Warehouse[]
  pagination: Pagination | null
  current: Warehouse | null
  loading: boolean
  saving: boolean
  error: string | null
}

export const useWarehouseStore = defineStore('warehouses', {
  state: (): WarehouseState => ({
    warehouses: [],
    pagination: null,
    current: null,
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
        const res = await get<{ data: Warehouse[]; total: number }>('/warehouses', params)
        this.warehouses = res.data?.data ?? []
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入倉庫失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchAll() {
      await this.fetchList({ per_page: 200 })
    },

    async fetchOne(id: number) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<Warehouse>(`/warehouses/${id}`)
        this.current = res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入倉庫失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async create(data: Record<string, unknown>): Promise<Warehouse> {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<Warehouse>('/warehouses', data)
        if (res.success && res.data) {
          this.warehouses.unshift(res.data)
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立倉庫失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async update(id: number, data: Record<string, unknown>): Promise<Warehouse> {
      this.saving = true
      this.error = null
      try {
        const { put } = useApi()
        const res = await put<Warehouse>(`/warehouses/${id}`, data)
        if (res.success && res.data) {
          const idx = this.warehouses.findIndex(w => w.id === id)
          if (idx !== -1) this.warehouses[idx] = res.data
          if (this.current?.id === id) this.current = res.data
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '更新倉庫失敗'
        throw e
      } finally {
        this.saving = false
      }
    },
  },
})
