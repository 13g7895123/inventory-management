<script setup lang="ts">
import type { PurchaseOrderStatus } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const purchaseStore = usePurchaseOrderStore()
const supplierStore = useSupplierStore()

onMounted(async () => {
  await supplierStore.fetchAll()
  loadOrders()
})

// ── 篩選 ─────────────────────────────────────────────────────────────
const statusFilter   = ref<PurchaseOrderStatus | ''>('')
const supplierFilter = ref<number | ''>('')
const page           = ref(1)

function loadOrders() {
  const params: Record<string, unknown> = { page: page.value, per_page: 20 }
  if (statusFilter.value)   params.status      = statusFilter.value
  if (supplierFilter.value) params.supplier_id = supplierFilter.value
  purchaseStore.fetchList(params)
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
  pending:   '待審核',
  approved:  '已核准',
  partial:   '部分到貨',
  received:  '全部到貨',
  cancelled: '已取消',
}

const statusClasses: Record<string, string> = {
  draft:     'bg-gray-100 text-gray-700',
  pending:   'bg-yellow-100 text-yellow-700',
  approved:  'bg-blue-100 text-blue-700',
  partial:   'bg-purple-100 text-purple-700',
  received:  'bg-green-100 text-green-700',
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
        <h1 class="text-2xl font-semibold">採購單列表</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          共 {{ purchaseStore.pagination?.total ?? purchaseStore.orders.length }} 筆
        </p>
      </div>
      <NuxtLink
        to="/purchase/orders/create"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
      >
        + 新增採購單
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
        <option value="pending">待審核</option>
        <option value="approved">已核准</option>
        <option value="partial">部分到貨</option>
        <option value="received">全部到貨</option>
        <option value="cancelled">已取消</option>
      </select>
      <select
        v-model="supplierFilter"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm w-48 focus-visible:outline-none"
        @change="doFilter"
      >
        <option value="">全部供應商</option>
        <option v-for="s in supplierStore.suppliers" :key="s.id" :value="s.id">
          {{ s.name }}
        </option>
      </select>
    </div>

    <!-- 載入中 -->
    <div v-if="purchaseStore.loading" class="flex justify-center py-16">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <!-- 表格 -->
    <div v-else class="rounded-lg border overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-muted/50">
          <tr>
            <th class="px-4 py-3 text-left font-medium">採購單號</th>
            <th class="px-4 py-3 text-left font-medium">供應商</th>
            <th class="px-4 py-3 text-center font-medium">狀態</th>
            <th class="px-4 py-3 text-right font-medium">總金額</th>
            <th class="px-4 py-3 text-center font-medium">預計到貨</th>
            <th class="px-4 py-3 text-center font-medium">建立日期</th>
            <th class="px-4 py-3 text-center font-medium">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="order in purchaseStore.orders"
            :key="order.id"
            class="hover:bg-muted/30 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs font-medium">{{ order.po_number }}</td>
            <td class="px-4 py-3">{{ order.supplier_name ?? '-' }}</td>
            <td class="px-4 py-3 text-center">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusClasses[order.status]"
              >
                {{ statusLabels[order.status] ?? order.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-medium">
              {{ formatMoney(order.total_amount) }}
            </td>
            <td class="px-4 py-3 text-center text-muted-foreground">
              {{ formatDate(order.expected_date) }}
            </td>
            <td class="px-4 py-3 text-center text-muted-foreground">
              {{ formatDate(order.created_at) }}
            </td>
            <td class="px-4 py-3 text-center">
              <NuxtLink
                :to="`/purchase/orders/${order.id}`"
                class="text-sm text-blue-600 hover:underline"
              >
                查看
              </NuxtLink>
            </td>
          </tr>
          <tr v-if="purchaseStore.orders.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
              尚無採購單資料
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div
      v-if="purchaseStore.pagination && purchaseStore.pagination.total_pages > 1"
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
        第 {{ page }} / {{ purchaseStore.pagination.total_pages }} 頁
      </span>
      <button
        :disabled="page >= purchaseStore.pagination.total_pages"
        class="rounded border px-3 py-1.5 text-sm disabled:opacity-40"
        @click="onPageChange(page + 1)"
      >
        下一頁
      </button>
    </div>
  </div>
</template>
