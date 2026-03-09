// app/stores/inventory.ts
import { defineStore } from 'pinia'
import type { Inventory, InventoryTransaction, Pagination } from '~/app/types/api'

interface InventoryState {
  items: Inventory[]
  total: number
  transactions: InventoryTransaction[]
  lowStockItems: Inventory[]
  loading: boolean
  loadingTx: boolean
  adjusting: boolean
  error: string | null
}

export const useInventoryStore = defineStore('inventory', {
  state: (): InventoryState => ({
    items: [],
    total: 0,
    transactions: [],
    lowStockItems: [],
    loading: false,
    loadingTx: false,
    adjusting: false,
    error: null,
  }),

  actions: {
    async fetchList(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<{ data: Inventory[]; total: number; page: number }>('/inventories', params)
        this.items = res.data?.data ?? []
        this.total = res.data?.total ?? 0
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入庫存失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchLowStock(warehouseId?: number) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const params: Record<string, unknown> = {}
        if (warehouseId) params.warehouse_id = warehouseId
        const res = await get<Inventory[]>('/inventories/low-stock', params)
        this.lowStockItems = res.data ?? []
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入低庫存失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchBySku(skuId: number) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<Inventory[]>(`/skus/${skuId}/inventories`)
        this.items = res.data ?? []
        this.total = this.items.length
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入 SKU 庫存失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchTransactions(params: Record<string, unknown> = {}) {
      this.loadingTx = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<{ data: InventoryTransaction[]; total: number; page: number }>('/inventory-transactions', params)
        this.transactions = res.data?.data ?? []
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入異動日誌失敗'
        throw e
      } finally {
        this.loadingTx = false
      }
    },

    async adjust(data: { sku_id: number; warehouse_id: number; qty: number; reason: string }): Promise<Inventory> {
      this.adjusting = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<Inventory>('/inventories/adjust', data)
        if (res.success && res.data) {
          const idx = this.items.findIndex(
            i => i.sku_id === data.sku_id && i.warehouse_id === data.warehouse_id,
          )
          if (idx !== -1) this.items[idx] = res.data
          return res.data
        }
        throw new Error(res.message)
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '庫存調整失敗'
        throw e
      } finally {
        this.adjusting = false
      }
    },
  },
})
