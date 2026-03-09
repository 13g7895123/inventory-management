<script setup lang="ts">
// pages/reports/sales.vue — 銷售業績報表（T15-4）

definePageMeta({ layout: 'default' })

import type { SalesReportSkuItem, SalesReportCustomerItem } from '~/app/types/api'

const reportsStore  = useReportsStore()
const customerStore = useCustomerStore()

const dateFrom    = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const dateTo      = ref(new Date().toISOString().slice(0, 10))
const customerId  = ref<number | null>(null)
const activeTab   = ref<'sku' | 'customer'>('sku')
const loading     = ref(false)

onMounted(async () => {
  await customerStore.fetchAll()
  await load()
})

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      date_from: dateFrom.value,
      date_to:   dateTo.value,
    }
    if (customerId.value) params.customer_id = customerId.value
    await reportsStore.fetchSalesReport(params)
  } finally {
    loading.value = false
  }
}

function handleExport() {
  const params: Record<string, unknown> = {
    date_from: dateFrom.value,
    date_to:   dateTo.value,
  }
  if (customerId.value) params.customer_id = customerId.value
  reportsStore.downloadExport('sales', params)
}

const report       = computed(() => reportsStore.salesReport)
const bySku        = computed((): SalesReportSkuItem[]      => report.value?.by_sku      ?? [])
const byCustomer   = computed((): SalesReportCustomerItem[] => report.value?.by_customer ?? [])
const summary      = computed(() => report.value?.summary)

const formatCurrency = (n: number) =>
  new Intl.NumberFormat('zh-TW', { style: 'currency', currency: 'TWD', maximumFractionDigits: 0 }).format(n)
const formatN = (n: number) =>
  new Intl.NumberFormat('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)

// 長條圖（Top 10 by_sku）
const chartBars = computed(() => {
  const top = bySku.value.slice(0, 10)
  const max = Math.max(...top.map(d => d.total_amount), 1)
  return top.map(d => ({
    label:   d.sku_code,
    tooltip: d.item_name,
    amount:  d.total_amount,
    width:   (d.total_amount / max) * 100,
  }))
})
</script>

<template>
  <div class="space-y-5">
    <!-- 標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold">銷售業績報表</h1>
        <p class="text-sm text-muted-foreground">依 SKU / 客戶彙整銷售量與銷售額</p>
      </div>
      <button
        class="inline-flex items-center gap-1.5 rounded-lg border border-green-300 bg-green-50 px-4 py-2 text-sm font-medium text-green-700 hover:bg-green-100 transition-colors"
        @click="handleExport"
      >
        ⬇ 匯出 Excel
      </button>
    </div>

    <!-- 篩選列 -->
    <div class="flex flex-wrap gap-3 rounded-xl border bg-card p-4">
      <div class="flex flex-col gap-1">
        <label class="text-xs text-muted-foreground">開始日期</label>
        <input v-model="dateFrom" type="date" class="rounded-md border px-3 py-1.5 text-sm" />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs text-muted-foreground">結束日期</label>
        <input v-model="dateTo" type="date" class="rounded-md border px-3 py-1.5 text-sm" />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs text-muted-foreground">客戶</label>
        <select v-model="customerId" class="rounded-md border px-3 py-1.5 text-sm">
          <option :value="null">全部客戶</option>
          <option v-for="c in customerStore.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div class="flex items-end">
        <button
          class="rounded-lg bg-primary px-4 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
          :disabled="loading"
          @click="load"
        >
          <span v-if="loading">查詢中…</span>
          <span v-else>查詢</span>
        </button>
      </div>
    </div>

    <!-- KPI 彙整 -->
    <div v-if="summary" class="grid grid-cols-2 gap-4">
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">銷售總額</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ formatCurrency(summary.total_revenue) }}</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">銷售訂單數</p>
        <p class="mt-1 text-2xl font-bold text-blue-600">{{ summary.total_orders }}</p>
      </div>
    </div>

    <!-- Top 10 長條圖 -->
    <div v-if="chartBars.length > 0" class="rounded-xl border bg-card p-5">
      <h2 class="mb-4 text-sm font-semibold">銷售前 10 品項（依銷售額）</h2>
      <div class="space-y-2">
        <div v-for="bar in chartBars" :key="bar.label" class="flex items-center gap-3">
          <div class="w-24 shrink-0 text-right text-xs font-mono text-muted-foreground truncate" :title="bar.tooltip">
            {{ bar.label }}
          </div>
          <div class="flex-1 h-5 rounded-full bg-muted overflow-hidden">
            <div
              class="h-full rounded-full bg-primary transition-all duration-500"
              :style="`width: ${bar.width}%`"
            />
          </div>
          <div class="w-28 shrink-0 text-right text-xs font-medium">{{ formatCurrency(bar.amount) }}</div>
        </div>
      </div>
    </div>

    <!-- 頁籤 -->
    <div class="flex gap-1 rounded-xl border bg-muted p-1 w-fit">
      <button
        :class="['rounded-lg px-4 py-1.5 text-sm font-medium transition-colors', activeTab === 'sku' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground']"
        @click="activeTab = 'sku'"
      >依 SKU</button>
      <button
        :class="['rounded-lg px-4 py-1.5 text-sm font-medium transition-colors', activeTab === 'customer' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground']"
        @click="activeTab = 'customer'"
      >依客戶</button>
    </div>

    <!-- 依 SKU 表格 -->
    <div v-if="activeTab === 'sku'" class="rounded-xl border bg-card overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/40">
              <th class="px-4 py-3 text-left font-medium">SKU 編號</th>
              <th class="px-4 py-3 text-left font-medium">品名</th>
              <th class="px-4 py-3 text-right font-medium">銷售數量</th>
              <th class="px-4 py-3 text-right font-medium">銷售金額</th>
              <th class="px-4 py-3 text-right font-medium">訂單數</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">載入中…</td>
            </tr>
            <tr v-else-if="bySku.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">查無資料</td>
            </tr>
            <tr v-for="item in bySku" :key="item.sku_id" class="border-b hover:bg-muted/20 transition-colors">
              <td class="px-4 py-3 font-mono text-xs">{{ item.sku_code }}</td>
              <td class="px-4 py-3">{{ item.item_name }}</td>
              <td class="px-4 py-3 text-right">{{ formatN(item.total_qty) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-green-700">{{ formatCurrency(item.total_amount) }}</td>
              <td class="px-4 py-3 text-right">{{ item.order_count }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 依客戶表格 -->
    <div v-if="activeTab === 'customer'" class="rounded-xl border bg-card overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/40">
              <th class="px-4 py-3 text-left font-medium">客戶名稱</th>
              <th class="px-4 py-3 text-right font-medium">訂單數</th>
              <th class="px-4 py-3 text-right font-medium">銷售金額</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">載入中…</td>
            </tr>
            <tr v-else-if="byCustomer.length === 0">
              <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">查無資料</td>
            </tr>
            <tr v-for="item in byCustomer" :key="item.customer_id" class="border-b hover:bg-muted/20 transition-colors">
              <td class="px-4 py-3 font-medium">{{ item.customer_name }}</td>
              <td class="px-4 py-3 text-right">{{ item.order_count }}</td>
              <td class="px-4 py-3 text-right font-semibold text-green-700">{{ formatCurrency(item.total_amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
