<script setup lang="ts">
// pages/reports/turnover.vue — 庫存週轉率（T14-5 前端）

import type { TurnoverRateItem } from '~/app/types/api'

const reportsStore  = useReportsStore()
const warehouseStore = useWarehouseStore()

const dateFrom    = ref(new Date(new Date().getFullYear(), new Date().getMonth() - 2, 1).toISOString().slice(0, 10))
const dateTo      = ref(new Date().toISOString().slice(0, 10))
const warehouseId = ref<number | null>(null)
const loading     = ref(false)
const sortKey     = ref<keyof TurnoverRateItem>('turnover_rate')
const sortAsc     = ref(false)

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
    await reportsStore.fetchTurnoverRate(params)
  } finally {
    loading.value = false
  }
}

const rows = computed((): TurnoverRateItem[] => {
  const data = [...(reportsStore.turnoverRate ?? [])]
  return data.sort((a, b) => {
    const av = a[sortKey.value] ?? 0
    const bv = b[sortKey.value] ?? 0
    return sortAsc.value ? (av as number) - (bv as number) : (bv as number) - (av as number)
  })
})

function setSort(key: keyof TurnoverRateItem) {
  if (sortKey.value === key) sortAsc.value = !sortAsc.value
  else { sortKey.value = key; sortAsc.value = false }
}

const formatN = (n: number | null, digits = 2) =>
  n == null ? '—' : new Intl.NumberFormat('zh-TW', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(n)

const formatCurrency = (n: number | null) =>
  n == null ? '—' : new Intl.NumberFormat('zh-TW', { style: 'currency', currency: 'TWD', maximumFractionDigits: 0 }).format(n)

// 週轉率高低評級顏色
function rateColor(rate: number | null): string {
  if (rate == null) return 'bg-muted text-muted-foreground'
  if (rate >= 6)    return 'bg-green-100 text-green-700'
  if (rate >= 3)    return 'bg-blue-100 text-blue-700'
  if (rate >= 1)    return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-600'
}

function rateLabel(rate: number | null): string {
  if (rate == null) return '無資料'
  if (rate >= 6)    return '高'
  if (rate >= 3)    return '中'
  if (rate >= 1)    return '低'
  return '極低'
}

// 彙總
const summary = computed(() => {
  const data = reportsStore.turnoverRate ?? []
  const withRate = data.filter(d => d.turnover_rate != null)
  if (withRate.length === 0) return null
  const avg = withRate.reduce((s, d) => s + (d.turnover_rate ?? 0), 0) / withRate.length
  const totalCogs = data.reduce((s, d) => s + d.total_cogs, 0)
  const totalStock = data.reduce((s, d) => s + d.avg_inventory_value, 0)
  const slow = data.filter(d => d.turnover_rate != null && d.turnover_rate < 1).length
  return { avg, totalCogs, totalStock, slow, total: data.length }
})

// 長條圖 Top10
const chartBars = computed(() => {
  const data = (reportsStore.turnoverRate ?? [])
    .filter(d => d.turnover_rate != null)
    .sort((a, b) => (b.turnover_rate ?? 0) - (a.turnover_rate ?? 0))
    .slice(0, 10)
  const max = Math.max(...data.map(d => d.turnover_rate ?? 0), 1)
  return data.map(d => ({
    label: `${d.sku_code}`,
    name:  d.item_name,
    rate:  d.turnover_rate ?? 0,
    width: ((d.turnover_rate ?? 0) / max) * 100,
    color: rateColor(d.turnover_rate),
  }))
})
</script>

<template>
  <div class="space-y-5">
    <!-- 標題 -->
    <div>
      <h1 class="text-xl font-semibold">庫存週轉率</h1>
      <p class="text-sm text-muted-foreground">分析各 SKU 的存貨週轉效率，找出滯銷品項</p>
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
          <option v-for="w in warehouseStore.warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
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

    <!-- 彙總 KPI -->
    <div v-if="summary" class="grid grid-cols-2 gap-4 md:grid-cols-4">
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">品項數（SKU）</p>
        <p class="mt-1 text-2xl font-bold">{{ summary.total }}</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">平均週轉率</p>
        <p class="mt-1 text-2xl font-bold text-blue-600">{{ formatN(summary.avg) }}×</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">低週轉品項（&lt;1×）</p>
        <p class="mt-1 text-2xl font-bold text-red-500">{{ summary.slow }}</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">平均庫存值</p>
        <p class="mt-1 text-2xl font-bold text-amber-600">{{ formatCurrency(summary.totalStock / summary.total) }}</p>
      </div>
    </div>

    <!-- Top10 長條圖 -->
    <div v-if="chartBars.length > 0" class="rounded-xl border bg-card p-5">
      <h2 class="mb-4 text-sm font-semibold">Top 10 高週轉 SKU</h2>
      <div class="space-y-3">
        <div v-for="bar in chartBars" :key="bar.label" class="space-y-1">
          <div class="flex items-baseline justify-between gap-2 text-xs">
            <span class="font-mono font-medium">{{ bar.label }}</span>
            <span class="truncate text-muted-foreground max-w-[160px]">{{ bar.name }}</span>
            <span class="ml-auto shrink-0 font-semibold">{{ formatN(bar.rate) }}×</span>
          </div>
          <div class="h-3 rounded-full bg-muted overflow-hidden">
            <div
              class="h-full rounded-full transition-all duration-500"
              :class="bar.rate >= 6 ? 'bg-green-500' : bar.rate >= 3 ? 'bg-blue-500' : 'bg-amber-400'"
              :style="`width: ${bar.width}%`"
            />
          </div>
        </div>
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
              <th
                class="cursor-pointer px-4 py-3 text-right font-medium hover:text-foreground"
                :class="sortKey === 'total_cogs' ? 'text-foreground' : 'text-muted-foreground'"
                @click="setSort('total_cogs')"
              >
                期間出庫成本 {{ sortKey === 'total_cogs' ? (sortAsc ? '↑' : '↓') : '' }}
              </th>
              <th
                class="cursor-pointer px-4 py-3 text-right font-medium hover:text-foreground"
                :class="sortKey === 'avg_inventory_value' ? 'text-foreground' : 'text-muted-foreground'"
                @click="setSort('avg_inventory_value')"
              >
                平均庫存值 {{ sortKey === 'avg_inventory_value' ? (sortAsc ? '↑' : '↓') : '' }}
              </th>
              <th
                class="cursor-pointer px-4 py-3 text-right font-medium hover:text-foreground"
                :class="sortKey === 'turnover_rate' ? 'text-foreground' : 'text-muted-foreground'"
                @click="setSort('turnover_rate')"
              >
                週轉率 {{ sortKey === 'turnover_rate' ? (sortAsc ? '↑' : '↓') : '' }}
              </th>
              <th
                class="cursor-pointer px-4 py-3 text-right font-medium hover:text-foreground"
                :class="sortKey === 'days_on_hand' ? 'text-foreground' : 'text-muted-foreground'"
                @click="setSort('days_on_hand')"
              >
                週轉天數 {{ sortKey === 'days_on_hand' ? (sortAsc ? '↑' : '↓') : '' }}
              </th>
              <th class="px-4 py-3 text-center font-medium">評級</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">載入中…</td>
            </tr>
            <tr v-else-if="rows.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">查無資料</td>
            </tr>
            <tr v-for="row in rows" :key="`${row.sku_id}-${row.warehouse_id ?? 0}`" class="border-b hover:bg-muted/20 transition-colors">
              <td class="px-4 py-3 font-mono text-xs">{{ row.sku_code }}</td>
              <td class="px-4 py-3">{{ row.item_name }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(row.total_cogs) }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(row.avg_inventory_value) }}</td>
              <td class="px-4 py-3 text-right font-semibold">
                {{ formatN(row.turnover_rate) }}{{ row.turnover_rate != null ? '×' : '' }}
              </td>
              <td class="px-4 py-3 text-right">
                {{ row.days_on_hand != null ? formatN(row.days_on_hand, 0) + ' 天' : '—' }}
              </td>
              <td class="px-4 py-3 text-center">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="rateColor(row.turnover_rate)"
                >{{ rateLabel(row.turnover_rate) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 說明 -->
    <div class="rounded-xl border bg-muted/30 p-4 text-xs text-muted-foreground space-y-1">
      <p class="font-medium text-foreground">計算方式</p>
      <p>週轉率 = 期間出庫成本 ÷ 期間平均庫存值</p>
      <p>週轉天數 = 期間天數 ÷ 週轉率</p>
      <div class="mt-2 flex flex-wrap gap-3">
        <span class="flex items-center gap-1"><span class="inline-block h-2 w-3 rounded bg-green-500" /> 高（≥6×）</span>
        <span class="flex items-center gap-1"><span class="inline-block h-2 w-3 rounded bg-blue-500" /> 中（3~6×）</span>
        <span class="flex items-center gap-1"><span class="inline-block h-2 w-3 rounded bg-amber-400" /> 低（1~3×）</span>
        <span class="flex items-center gap-1"><span class="inline-block h-2 w-3 rounded bg-red-500" /> 極低（&lt;1×）</span>
      </div>
    </div>
  </div>
</template>
