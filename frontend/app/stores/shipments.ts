import { defineStore } from 'pinia'
import type { Shipment, ShipmentLine } from '~/app/types/api'

interface ShipmentsState {
  current: Shipment | null
  currentLines: ShipmentLine[]
  loading: boolean
  saving: boolean
  error: string | null
}

export const useShipmentStore = defineStore('shipments', {
  state: (): ShipmentsState => ({
    current: null,
    currentLines: [],
    loading: false,
    saving: false,
    error: null,
  }),

  actions: {
    async fetchOne(id: number) {
      this.loading = true
      this.error = null
      try {
        const { get } = useApi()
        const res = await get<{ shipment: Shipment; lines: ShipmentLine[] }>(`/shipments/${id}`)
        this.current = res.data.shipment
        this.currentLines = res.data.lines
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入出貨單失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async create(salesOrderId: number, data: Record<string, unknown>) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<{ shipment: Shipment; lines: ShipmentLine[] }>(
          `/sales-orders/${salesOrderId}/shipments`,
          data,
        )
        this.current = res.data.shipment
        this.currentLines = res.data.lines
        return res.data
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立出貨單失敗'
        throw e
      } finally {
        this.saving = false
      }
    },
  },
})
