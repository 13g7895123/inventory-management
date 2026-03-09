// app/stores/units.ts
import { defineStore } from 'pinia'
import type { Unit } from '~/app/types/api'

interface UnitState {
  units:   Unit[]
  loading: boolean
  saving:  boolean
  error:   string | null
}

export const useUnitStore = defineStore('units', {
  state: (): UnitState => ({
    units:   [],
    loading: false,
    saving:  false,
    error:   null,
  }),

  getters: {
    activeUnits: (state) => state.units.filter((u) => u.is_active),
  },

  actions: {
    async fetchAll() {
      const { get } = useApi()
      this.loading = true
      this.error   = null

      try {
        const res = await get<Unit[]>('/units')
        if (res.success) {
          this.units = res.data
        }
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入單位失敗'
      } finally {
        this.loading = false
      }
    },

    async create(data: Partial<Unit>): Promise<Unit> {
      const { post } = useApi()
      this.saving = true
      this.error  = null

      try {
        const res = await post<Unit>('/units', data)
        if (res.success && res.data) {
          this.units.push(res.data)
          return res.data
        }
        throw new Error(res.message)
      } finally {
        this.saving = false
      }
    },

    async update(id: number, data: Partial<Unit>): Promise<Unit> {
      const { put } = useApi()
      this.saving = true
      this.error  = null

      try {
        const res = await put<Unit>(`/units/${id}`, data)
        if (res.success && res.data) {
          const idx = this.units.findIndex((u) => u.id === id)
          if (idx !== -1) this.units[idx] = res.data
          return res.data
        }
        throw new Error(res.message)
      } finally {
        this.saving = false
      }
    },

    async remove(id: number): Promise<void> {
      const { del } = useApi()
      this.saving = true
      this.error  = null

      try {
        await del(`/units/${id}`)
        this.units = this.units.filter((u) => u.id !== id)
      } finally {
        this.saving = false
      }
    },
  },
})
