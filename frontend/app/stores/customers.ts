import { defineStore } from 'pinia'
import type { Customer, CustomerAddress, Pagination } from '~/app/types/api'

interface CustomersState {
  customers: Customer[]
  pagination: Pagination | null
  current: Customer | null
  addresses: CustomerAddress[]
  loading: boolean
  saving: boolean
  error: string | null
}

export const useCustomerStore = defineStore('customers', {
  state: (): CustomersState => ({
    customers: [],
    pagination: null,
    current: null,
    addresses: [],
    loading: false,
    saving: false,
    error: null,
  }),

  actions: {
    async fetchList(params: Record<string, unknown> = {}) {
      this.loading = true
      this.error = null
      try {
        const { getPaginated } = useApi()
        const res = await getPaginated<Customer>('/customers', params)
        this.customers = res.data
        this.pagination = res.pagination
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入客戶列表失敗'
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
        const res = await get<{ customer: Customer; addresses: CustomerAddress[] }>(`/customers/${id}`)
        this.current = res.data.customer
        this.addresses = res.data.addresses
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '載入客戶資料失敗'
        throw e
      } finally {
        this.loading = false
      }
    },

    async fetchAll() {
      await this.fetchList({ per_page: 200 })
    },

    async create(data: Record<string, unknown>) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<{ customer: Customer }>('/customers', data)
        return res.data.customer
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '建立客戶失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async update(id: number, data: Record<string, unknown>) {
      this.saving = true
      this.error = null
      try {
        const { put } = useApi()
        const res = await put<{ customer: Customer }>(`/customers/${id}`, data)
        return res.data.customer
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '更新客戶失敗'
        throw e
      } finally {
        this.saving = false
      }
    },

    async addAddress(customerId: number, data: Record<string, unknown>) {
      this.saving = true
      this.error = null
      try {
        const { post } = useApi()
        const res = await post<{ address: CustomerAddress }>(`/customers/${customerId}/addresses`, data)
        this.addresses.push(res.data.address)
        return res.data.address
      } catch (e: unknown) {
        this.error = e instanceof Error ? e.message : '新增地址失敗'
        throw e
      } finally {
        this.saving = false
      }
    },
  },
})
