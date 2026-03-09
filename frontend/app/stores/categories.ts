// app/stores/categories.ts
import { defineStore } from 'pinia'
import type { Category } from '~/app/types/api'

interface CategoryState {
  categories: Category[]
  loading:    boolean
  saving:     boolean
  error:      string | null
}

export const useCategoryStore = defineStore('categories', {
  state: (): CategoryState => ({
    categories: [],
    loading:    false,
    saving:     false,
    error:      null,
  }),

  getters: {
    /** 樹狀結構（巢狀 children） */
    tree(): Category[] {
      const map = new Map<number, Category>()
      const roots: Category[] = []

      this.categories.forEach((c) => {
        map.set(c.id, { ...c, children: [] })
      })

      this.categories.forEach((c) => {
        const node = map.get(c.id)!
        if (c.parent_id && map.has(c.parent_id)) {
          map.get(c.parent_id)!.children!.push(node)
        } else {
          roots.push(node)
        }
      })

      return roots
    },

    /** 扁平列表（含 parent_name，供下拉選單使用） */
    flatList(): (Category & { parent_name?: string })[] {
      const map = new Map(this.categories.map((c) => [c.id, c]))
      return this.categories.map((c) => ({
        ...c,
        parent_name: c.parent_id ? map.get(c.parent_id)?.name : undefined,
      }))
    },
  },

  actions: {
    async fetchAll() {
      const { get } = useApi()
      this.loading = true
      this.error   = null

      try {
        const res = await get<Category[]>('/categories')
        if (res.success) {
          this.categories = res.data
        }
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入分類失敗'
      } finally {
        this.loading = false
      }
    },

    async create(data: Partial<Category>): Promise<Category> {
      const { post } = useApi()
      this.saving = true
      this.error  = null

      try {
        const res = await post<Category>('/categories', data)
        if (res.success && res.data) {
          this.categories.push(res.data)
          return res.data
        }
        throw new Error(res.message)
      } finally {
        this.saving = false
      }
    },

    async update(id: number, data: Partial<Category>): Promise<Category> {
      const { put } = useApi()
      this.saving = true
      this.error  = null

      try {
        const res = await put<Category>(`/categories/${id}`, data)
        if (res.success && res.data) {
          const idx = this.categories.findIndex((c) => c.id === id)
          if (idx !== -1) this.categories[idx] = res.data
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
        await del(`/categories/${id}`)
        this.categories = this.categories.filter((c) => c.id !== id)
      } finally {
        this.saving = false
      }
    },
  },
})
