// app/stores/suppliers.ts
import { defineStore } from 'pinia'
import type { Supplier, Pagination } from '~/app/types/api'

interface SupplierState {
  suppliers:  Supplier[]
  pagination: Pagination | null
  current:    Supplier | null
  loading:    boolean
  saving:     boolean
  error:      string | null
}

export const useSupplierStore = defineStore('suppliers', {
  state: (): SupplierState => ({
    suppliers:  [],
    pagination: null,
    current:    null,
    loading:    false,
    saving:     false,
    error:      null,
  }),

  getters: {
    activeSuppliers: (state) => state.suppliers.filter((s) => s.is_active),
  },

  actions: {
    async fetchList(params: Record<string, unknown> = {}) {
      const { getPaginated } = useApi()
      this.loading = true
      this.error   = null
      try {
        const res = await getPaginated<Supplier>('/suppliers', params)
        if (res.success) {
          this.suppliers  = res.data
          this.pagination = res.pagination
        }
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入供應商失敗'
      } finally {
        this.loading = false
      }
    },

    async fetchAll() {
      await this.fetchList({ per_page: 200 })
    },

    async fetchOne(id: number) {
      const { get } = useApi()
      this.loading = true
      this.current = null
      try {
        const res = await get<Supplier>(`/suppliers/${id}`)
        if (res.success && res.data) this.current = res.data
      } finally {
        this.loading = false
      }
    },

    async create(data: Record<string, unknown>): Promise<Supplier> {
      const { post } = useApi()
      this.saving = true
      try {
        const res = await post<Supplier>('/suppliers', data)
        if (res.success && res.data) {
          this.suppliers.unshift(res.data)
          return res.data
        }
        throw new Error(res.message)
      } finally {
        this.saving = false
      }
    },

    async update(id: number, data: Record<string, unknown>): Promise<Supplier> {
      const { put } = useApi()
      this.saving = true
      try {
        const res = await put<Supplier>(`/suppliers/${id}`, data)
        if (res.success && res.data) {
          const idx = this.suppliers.findIndex((s) => s.id === id)
          if (idx !== -1) this.suppliers[idx] = res.data
          if (this.current?.id === id) this.current = res.data
          return res.data
        }
        throw new Error(res.message)
      } finally {
        this.saving = false
      }
    },
  },
})
