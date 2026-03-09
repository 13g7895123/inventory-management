<script setup lang="ts">
import type { SalesOrderStatus } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const soStore       = useSalesOrderStore()
const customerStore = useCustomerStore()

onMounted(async () => {
  await customerStore.fetchAll()
  loadOrders()
})

// ── 篩選 ─────────────────────────────────────────────────────────────
const statusFilter   = ref<SalesOrderStatus | ''>('')
const customerFilter = ref<number | ''>('')
const page           = ref(1)

function loadOrders() {
  const params: Record<string, unknown> = { page: page.value, per_page: 20 }
  if (statusFilter.value)   params.status      = statusFilter.value
  if (customerFilter.value) params.customer_id = customerFilter.value
  soStore.fetchList(params)
}

function doFilter() {
  page.value = 1
  loadOrders()
}

function onPageChange(p: number) {
  page.value = p
  loadOrders()
}

// ── 狀態顯示 ─────────────────────────────────────────────────────────
const statusLabels: Record<string, string> = {
  draft:     '草稿',
  confirmed: '已確認',
  partial:   '部分出貨',
  shipped:   '已出貨',
  cancelled: '已取消',
}

const statusClasses: Record<string, string> = {
  draft:     'bg-gray-100 text-gray-700',
  confirmed: 'bg-blue-100 text-blue-700',
  partial:   'bg-purple-100 text-purple-700',
  shipped:   'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
}

function formatDate(dt: string | null) {
  if (!dt) return '-'
  return dt.substring(0, 10)
}

function formatMoney(val: number | string | null) {
  if (val === null || val === undefined) return '-'
  return Number(val).toLocaleString('zh-TW', { minimumFractionDigits: 0 })
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">銷售訂單列表</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          共 {{ soStore.pagination?.total ?? soStore.orders.length }} 筆
        </p>
      </div>
      <NuxtLink
        to="/sales/orders/create"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
      >
        + 新增銷售單
      </NuxtLink>
    </div>

    <!-- 篩選列 -->
    <div class="flex flex-wrap gap-3">
      <select
        v-model="statusFilter"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm w-36 focus-visible:outline-none"
        @change="doFilter"
      >
        <option value="">全部狀態</option>
        <option value="draft">草稿</option>
        <option value="confirmed">已確認</option>
        <option value="partial">部分出貨</option>
        <option value="shipped">已出貨</option>
        <option value="cancelled">已取消</option>
      </select>
      <select
        v-model="customerFilter"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm w-48 focus-visible:outline-none"
        @change="doFilter"
      >
        <option value="">全部客戶</option>
        <option v-for="c in customerStore.customers" :key="c.id" :value="c.id">
          {{ c.name }}
        </option>
      </select>
    </div>

    <!-- 載入中 -->
    <div v-if="soStore.loading" class="flex justify-center py-16">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <!-- 表格 -->
    <div v-else class="rounded-lg border overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-muted/50">
          <tr>
            <th class="px-4 py-3 text-left font-medium">訂單號</th>
            <th class="px-4 py-3 text-left font-medium">客戶</th>
            <th class="px-4 py-3 text-center font-medium">狀態</th>
            <th class="px-4 py-3 text-right font-medium">總金額</th>
            <th class="px-4 py-3 text-center font-medium">訂單日期</th>
            <th class="px-4 py-3 text-center font-medium">預計出貨</th>
            <th class="px-4 py-3 text-center font-medium">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="so in soStore.orders"
            :key="so.id"
            class="hover:bg-muted/30 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs font-medium">{{ so.so_number }}</td>
            <td class="px-4 py-3">{{ so.customer_name ?? `客戶 #${so.customer_id}` }}</td>
            <td class="px-4 py-3 text-center">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusClasses[so.status]"
              >
                {{ statusLabels[so.status] ?? so.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-medium">{{ formatMoney(so.total_amount) }}</td>
            <td class="px-4 py-3 text-center text-muted-foreground">{{ formatDate(so.order_date) }}</td>
            <td class="px-4 py-3 text-center text-muted-foreground">{{ formatDate(so.expected_ship_date) }}</td>
            <td class="px-4 py-3 text-center">
              <NuxtLink
                :to="`/sales/orders/${so.id}`"
                class="text-sm text-blue-600 hover:underline"
              >
                查看
              </NuxtLink>
            </td>
          </tr>
          <tr v-if="soStore.orders.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
              尚無銷售訂單資料
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div
      v-if="soStore.pagination && soStore.pagination.total_pages > 1"
      class="flex items-center justify-center gap-2"
    >
      <button
        :disabled="page <= 1"
        class="rounded border px-3 py-1.5 text-sm disabled:opacity-40"
        @click="onPageChange(page - 1)"
      >
        上一頁
      </button>
      <span class="text-sm text-muted-foreground">
        {{ page }} / {{ soStore.pagination.total_pages }}
      </span>
      <button
        :disabled="page >= soStore.pagination.total_pages"
        class="rounded border px-3 py-1.5 text-sm disabled:opacity-40"
        @click="onPageChange(page + 1)"
      >
        下一頁
      </button>
    </div>
  </div>
</template>
