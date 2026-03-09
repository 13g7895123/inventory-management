<script setup lang="ts">
// pages/reports/inventory-summary.vue — 進銷存彙總表（T15-3）

definePageMeta({ layout: 'default' })

import type { InventorySummaryItem } from '~/app/types/api'

const reportsStore   = useReportsStore()
const warehouseStore = useWarehouseStore()

const dateFrom = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const dateTo   = ref(new Date().toISOString().slice(0, 10))
const warehouseId = ref<number | null>(null)
const search   = ref('')
const loading  = ref(false)

onMounted(async () => {
  await warehouseStore.fetchAll()
  await load()
})

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      date_from: dateFrom.value,
      date_to:   dateTo.value,
    }
    if (warehouseId.value) params.warehouse_id = warehouseId.value
    if (search.value.trim()) params.q = search.value.trim()
    await reportsStore.fetchInventorySummary(params)
  } finally {
    loading.value = false
  }
}

function handleExport() {
  const params: Record<string, unknown> = {
    date_from: dateFrom.value,
    date_to:   dateTo.value,
  }
  if (warehouseId.value) params.warehouse_id = warehouseId.value
  reportsStore.downloadExport('inventory-summary', params)
}

const report   = computed(() => reportsStore.inventorySummary)
const items    = computed((): InventorySummaryItem[] => report.value?.items ?? [])
const summary  = computed(() => report.value?.summary)

const formatN = (n: number) =>
  new Intl.NumberFormat('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
const formatCurrency = (n: number) =>
  new Intl.NumberFormat('zh-TW', { style: 'currency', currency: 'TWD', maximumFractionDigits: 0 }).format(n)
</script>

<template>
  <div class="space-y-5">
    <!-- 標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold">進銷存彙總表</h1>
        <p class="text-sm text-muted-foreground">期初/期末庫存、入出庫量彙整</p>
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
        <label class="text-xs text-muted-foreground">倉庫</label>
        <select v-model="warehouseId" class="rounded-md border px-3 py-1.5 text-sm">
          <option :value="null">全部倉庫</option>
          <option v-for="wh in warehouseStore.warehouses" :key="wh.id" :value="wh.id">{{ wh.name }}</option>
        </select>
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs text-muted-foreground">搜尋 SKU / 品名</label>
        <input v-model="search" type="text" placeholder="輸入關鍵字…" class="rounded-md border px-3 py-1.5 text-sm w-48" />
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

    <!-- 彙總數字 -->
    <div v-if="summary" class="grid grid-cols-2 gap-4 sm:grid-cols-2">
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">期初庫存值</p>
        <p class="mt-1 text-2xl font-bold text-blue-600">{{ formatCurrency(summary.total_opening_value) }}</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">期末庫存值</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ formatCurrency(summary.total_closing_value) }}</p>
      </div>
    </div>

    <!-- 資料表格 -->
    <div class="rounded-xl border bg-card overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/40">
              <th class="px-4 py-3 text-left font-medium">SKU 編號</th>
              <th class="px-4 py-3 text-left font-medium">品名</th>
              <th class="px-4 py-3 text-left font-medium">倉庫</th>
              <th class="px-4 py-3 text-right font-medium">期初庫存</th>
              <th class="px-4 py-3 text-right font-medium">入庫</th>
              <th class="px-4 py-3 text-right font-medium">出庫</th>
              <th class="px-4 py-3 text-right font-medium">調整</th>
              <th class="px-4 py-3 text-right font-medium">期末庫存</th>
              <th class="px-4 py-3 text-right font-medium">平均成本</th>
              <th class="px-4 py-3 text-right font-medium">期末庫存值</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">載入中…</td>
            </tr>
            <tr v-else-if="items.length === 0">
              <td colspan="10" class="px-4 py-8 text-center text-muted-foreground">查無資料</td>
            </tr>
            <tr
              v-for="item in items"
              :key="`${item.sku_id}_${item.warehouse_id}`"
              class="border-b hover:bg-muted/20 transition-colors"
            >
              <td class="px-4 py-3 font-mono text-xs">{{ item.sku_code }}</td>
              <td class="px-4 py-3">{{ item.item_name }}</td>
              <td class="px-4 py-3 text-muted-foreground text-xs">{{ item.warehouse_name }}</td>
              <td class="px-4 py-3 text-right">{{ formatN(item.opening_qty) }}</td>
              <td class="px-4 py-3 text-right text-green-600">+{{ formatN(item.in_qty) }}</td>
              <td class="px-4 py-3 text-right text-red-500">-{{ formatN(item.out_qty) }}</td>
              <td
                class="px-4 py-3 text-right"
                :class="item.adjust_qty >= 0 ? 'text-blue-600' : 'text-orange-500'"
              >
                {{ item.adjust_qty >= 0 ? '+' : '' }}{{ formatN(item.adjust_qty) }}
              </td>
              <td class="px-4 py-3 text-right font-semibold">{{ formatN(item.closing_qty) }}</td>
              <td class="px-4 py-3 text-right text-muted-foreground">{{ formatN(item.avg_cost) }}</td>
              <td class="px-4 py-3 text-right font-semibold text-green-700">{{ formatCurrency(item.closing_value) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
