// app/stores/items.ts
import { defineStore } from 'pinia'
import type { Item, ItemSku, ApiResponse, Pagination } from '~/app/types/api'

interface ItemState {
  items:       Item[]
  pagination:  Pagination | null
  current:     Item | null
  skus:        ItemSku[]
  loading:     boolean
  saving:      boolean
  error:       string | null
}

export const useItemStore = defineStore('items', {
  state: (): ItemState => ({
    items:      [],
    pagination: null,
    current:    null,
    skus:       [],
    loading:    false,
    saving:     false,
    error:      null,
  }),

  actions: {
    async fetchList(params: Record<string, unknown> = {}) {
      const { getPaginated } = useApi()
      this.loading = true
      this.error   = null

      try {
        const res = await getPaginated<Item>('/items', params)
        if (res.success) {
          this.items      = res.data
          this.pagination = res.pagination
        }
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入商品失敗'
      } finally {
        this.loading = false
      }
    },

    async fetchOne(id: number) {
      const { get } = useApi()
      this.loading = true
      this.error   = null
      this.current = null

      try {
        const res = await get<Item>(`/items/${id}`)
        if (res.success && res.data) {
          this.current = res.data
        }
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入商品失敗'
      } finally {
        this.loading = false
      }
    },

    async create(data: Record<string, unknown>): Promise<Item> {
      const { post } = useApi()
      this.saving = true
      this.error  = null

      try {
        const res = await post<Item>('/items', data)
        if (res.success && res.data) {
          return res.data
        }
        throw new Error(res.message)
      } finally {
        this.saving = false
      }
    },

    async update(id: number, data: Record<string, unknown>): Promise<Item> {
      const { put } = useApi()
      this.saving = true
      this.error  = null

      try {
        const res = await put<Item>(`/items/${id}`, data)
        if (res.success && res.data) {
          if (this.current?.id === id) {
            this.current = res.data
          }
          return res.data
        }
        throw new Error(res.message)
      } finally {
        this.saving = false
      }
    },

    async remove(id: number): Promise<void> {
      const { del } = useApi()
      const res = await del(`/items/${id}`)
      this.items = this.items.filter((i) => i.id !== id)
    },

    async toggleActive(id: number, isActive: boolean): Promise<void> {
      const { put } = useApi()
      await put(`/items/${id}`, { is_active: isActive })
      const item = this.items.find((i) => i.id === id)
      if (item) item.is_active = isActive
    },

    // ── SKU ─────────────────────────────────────────────────────────

    async fetchSkus(itemId: number) {
      const { get } = useApi()
      this.loading = true

      try {
        const res = await get<ItemSku[]>(`/items/${itemId}/skus`)
        if (res.success) {
          this.skus = res.data
        }
      } finally {
        this.loading = false
      }
    },

    async createSku(itemId: number, data: Record<string, unknown>): Promise<ItemSku> {
      const { post } = useApi()
      const res = await post<ItemSku>(`/items/${itemId}/skus`, data)
      if (res.success && res.data) {
        this.skus.push(res.data)
        return res.data
      }
      throw new Error(res.message)
    },

    async updateSku(skuId: number, data: Record<string, unknown>): Promise<ItemSku> {
      const { put } = useApi()
      const res = await put<ItemSku>(`/skus/${skuId}`, data)
      if (res.success && res.data) {
        const idx = this.skus.findIndex((s) => s.id === skuId)
        if (idx !== -1) this.skus[idx] = res.data
        return res.data
      }
      throw new Error(res.message)
    },

    async removeSku(skuId: number): Promise<void> {
      const { del } = useApi()
      await del(`/skus/${skuId}`)
      this.skus = this.skus.filter((s) => s.id !== skuId)
    },

    // ── 圖片上傳 ─────────────────────────────────────────────────────

    async uploadImage(itemId: number, file: File): Promise<string> {
      const config    = useRuntimeConfig()
      const authStore = useAuthStore()
      const baseURL   = config.public.apiBase as string

      const form = new FormData()
      form.append('image', file)

      const res = await $fetch<ApiResponse<{ path: string; url: string }>>(`${baseURL}/items/${itemId}/images`, {
        method:  'POST',
        headers: { Authorization: `Bearer ${authStore.accessToken}` },
        body:    form,
      })

      if (res.success && res.data) {
        if (this.current?.id === itemId) {
          this.current.image_path = res.data.path
        }
        return res.data.url
      }
      throw new Error(res.message)
    },
  },
})
