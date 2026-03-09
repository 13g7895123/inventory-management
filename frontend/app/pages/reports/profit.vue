<script setup lang="ts">
// pages/reports/profit.vue — 毛利分析報表（T15-5）

import type { ProfitDailyItem, ProfitSkuItem } from '~/app/types/api'

const reportsStore = useReportsStore()
const warehouseStore = useWarehouseStore()

const dateFrom    = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const dateTo      = ref(new Date().toISOString().slice(0, 10))
const warehouseId = ref<number | null>(null)
const activeTab   = ref<'daily' | 'sku'>('daily')
const loading     = ref(false)

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
    await reportsStore.fetchProfitReport(params)
  } finally {
    loading.value = false
  }
}

const report   = computed(() => reportsStore.profitReport)
const daily    = computed((): ProfitDailyItem[] => report.value?.daily    ?? [])
const bySku    = computed((): ProfitSkuItem[]   => report.value?.by_sku   ?? [])
const summary  = computed(() => report.value?.summary)

const formatCurrency = (n: number) =>
  new Intl.NumberFormat('zh-TW', { style: 'currency', currency: 'TWD', maximumFractionDigits: 0 }).format(n)
const formatPct = (n: number) => `${n.toFixed(1)}%`

// SVG 折線圖（毛利率趨勢）
const chartWidth  = 700
const chartHeight = 180
const padL = 40
const padR = 10
const padT = 10
const padB = 30

const marginPoints = computed(() => {
  const data = daily.value.filter(d => d.revenue > 0)
  if (data.length === 0) return []
  const n = data.length
  const maxM = Math.max(...data.map(d => d.gross_margin), 1)
  return data.map((d, i) => ({
    x: padL + (i / (n - 1 || 1)) * (chartWidth - padL - padR),
    y: padT + (1 - d.gross_margin / maxM) * (chartHeight - padT - padB),
    date: d.date,
    margin: d.gross_margin,
    profit: d.gross_profit,
  }))
})

const marginPolyline = computed(() =>
  marginPoints.value.map(p => `${p.x},${p.y}`).join(' ')
)

const tooltip = ref<typeof marginPoints.value[0] | null>(null)
</script>

<template>
  <div class="space-y-5">
    <!-- 標題 -->
    <div>
      <h1 class="text-xl font-semibold">毛利分析報表</h1>
      <p class="text-sm text-muted-foreground">銷售收入扣除銷售成本，計算毛利率與趨勢</p>
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
    <div v-if="summary" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">總銷售額</p>
        <p class="mt-1 text-xl font-bold text-blue-600">{{ formatCurrency(summary.total_revenue) }}</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">銷售成本</p>
        <p class="mt-1 text-xl font-bold text-red-500">{{ formatCurrency(summary.total_cogs) }}</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">毛利</p>
        <p class="mt-1 text-xl font-bold text-green-600">{{ formatCurrency(summary.gross_profit) }}</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">毛利率</p>
        <p
          class="mt-1 text-xl font-bold"
          :class="summary.gross_margin >= 20 ? 'text-green-600' : summary.gross_margin >= 10 ? 'text-amber-500' : 'text-red-500'"
        >{{ formatPct(summary.gross_margin) }}</p>
      </div>
    </div>

    <!-- 毛利率趨勢折線圖 -->
    <div class="rounded-xl border bg-card p-5">
      <h2 class="mb-4 text-sm font-semibold">毛利率趨勢（%）</h2>
      <div v-if="marginPoints.length === 0" class="flex h-40 items-center justify-center text-muted-foreground text-sm">
        查無有效銷售資料
      </div>
      <div v-else class="relative" @mouseleave="tooltip = null">
        <svg
          :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
          class="w-full"
          :style="`height:${chartHeight}px`"
        >
          <polyline
            :points="marginPolyline"
            fill="none"
            stroke="hsl(var(--primary))"
            stroke-width="2"
            stroke-linejoin="round"
          />
          <circle
            v-for="pt in marginPoints"
            :key="pt.date"
            :cx="pt.x"
            :cy="pt.y"
            r="4"
            fill="hsl(var(--primary))"
            class="cursor-pointer opacity-0 hover:opacity-100"
            @mouseenter="tooltip = pt"
          />
        </svg>
        <div
          v-if="tooltip"
          class="pointer-events-none absolute z-10 rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg"
          :style="{ left: `${tooltip.x}px`, top: `${tooltip.y - 60}px`, transform: 'translateX(-50%)' }"
        >
          <p class="font-medium">{{ tooltip.date }}</p>
          <p class="text-muted-foreground">毛利：{{ formatCurrency(tooltip.profit) }}</p>
          <p class="text-primary font-semibold">毛利率：{{ formatPct(tooltip.margin) }}</p>
        </div>
      </div>
    </div>

    <!-- 頁籤 -->
    <div class="flex gap-1 rounded-xl border bg-muted p-1 w-fit">
      <button
        :class="['rounded-lg px-4 py-1.5 text-sm font-medium transition-colors', activeTab === 'daily' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground']"
        @click="activeTab = 'daily'"
      >每日明細</button>
      <button
        :class="['rounded-lg px-4 py-1.5 text-sm font-medium transition-colors', activeTab === 'sku' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground']"
        @click="activeTab = 'sku'"
      >依 SKU</button>
    </div>

    <!-- 每日表格 -->
    <div v-if="activeTab === 'daily'" class="rounded-xl border bg-card overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/40">
              <th class="px-4 py-3 text-left font-medium">日期</th>
              <th class="px-4 py-3 text-right font-medium">銷售額</th>
              <th class="px-4 py-3 text-right font-medium">銷售成本</th>
              <th class="px-4 py-3 text-right font-medium">毛利</th>
              <th class="px-4 py-3 text-right font-medium">毛利率</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">載入中…</td>
            </tr>
            <tr v-else-if="daily.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">查無資料</td>
            </tr>
            <tr v-for="item in daily" :key="item.date" class="border-b hover:bg-muted/20 transition-colors">
              <td class="px-4 py-3 font-mono text-xs">{{ item.date }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(item.revenue) }}</td>
              <td class="px-4 py-3 text-right text-muted-foreground">{{ formatCurrency(item.cogs) }}</td>
              <td class="px-4 py-3 text-right font-semibold" :class="item.gross_profit >= 0 ? 'text-green-600' : 'text-red-500'">
                {{ formatCurrency(item.gross_profit) }}
              </td>
              <td
                class="px-4 py-3 text-right font-semibold"
                :class="item.gross_margin >= 20 ? 'text-green-600' : item.gross_margin >= 10 ? 'text-amber-500' : 'text-red-500'"
              >
                {{ formatPct(item.gross_margin) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 依 SKU 表格 -->
    <div v-if="activeTab === 'sku'" class="rounded-xl border bg-card overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/40">
              <th class="px-4 py-3 text-left font-medium">SKU 編號</th>
              <th class="px-4 py-3 text-left font-medium">品名</th>
              <th class="px-4 py-3 text-right font-medium">銷售量</th>
              <th class="px-4 py-3 text-right font-medium">銷售額</th>
              <th class="px-4 py-3 text-right font-medium">銷售成本</th>
              <th class="px-4 py-3 text-right font-medium">毛利</th>
              <th class="px-4 py-3 text-right font-medium">毛利率</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">載入中…</td>
            </tr>
            <tr v-else-if="bySku.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">查無資料</td>
            </tr>
            <tr v-for="item in bySku" :key="item.sku_id" class="border-b hover:bg-muted/20 transition-colors">
              <td class="px-4 py-3 font-mono text-xs">{{ item.sku_code }}</td>
              <td class="px-4 py-3">{{ item.item_name }}</td>
              <td class="px-4 py-3 text-right">{{ item.sold_qty }}</td>
              <td class="px-4 py-3 text-right">{{ formatCurrency(item.revenue) }}</td>
              <td class="px-4 py-3 text-right text-muted-foreground">{{ formatCurrency(item.cogs) }}</td>
              <td class="px-4 py-3 text-right font-semibold" :class="item.gross_profit >= 0 ? 'text-green-600' : 'text-red-500'">
                {{ formatCurrency(item.gross_profit) }}
              </td>
              <td
                class="px-4 py-3 text-right font-semibold"
                :class="item.gross_margin >= 20 ? 'text-green-600' : item.gross_margin >= 10 ? 'text-amber-500' : 'text-red-500'"
              >
                {{ formatPct(item.gross_margin) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
