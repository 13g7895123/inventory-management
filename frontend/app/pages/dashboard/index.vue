<script setup lang="ts">
// 儀表板首頁 — Sprint 14/15：KPI 卡片 + 銷售趨勢折線圖
// auth.global.ts 全域 middleware 已保護此頁

definePageMeta({ layout: 'default' })

const authStore    = useAuthStore()
const invStore     = useInventoryStore()
const reportsStore = useReportsStore()

const today = computed(() => new Date().toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' }))

onMounted(async () => {
  await Promise.all([
    reportsStore.fetchDashboardKpi(),
    reportsStore.fetchSalesTrend(30),
    invStore.fetchLowStock(),
  ])
})

const kpi = computed(() => reportsStore.kpi)

const formatCurrency = (val: number) =>
  new Intl.NumberFormat('zh-TW', { style: 'currency', currency: 'TWD', maximumFractionDigits: 0 }).format(val)

const kpiCards = computed(() => [
  {
    label:  '待確認銷售單',
    value:  kpi.value ? String(kpi.value.pending_sales_orders) : '—',
    icon:   '📋',
    color:  'text-amber-600',
    link:   '/sales/orders',
  },
  {
    label:  '待審核採購單',
    value:  kpi.value ? String(kpi.value.pending_purchase_orders) : '—',
    icon:   '🛒',
    color:  'text-blue-600',
    link:   '/purchase/orders',
  },
  {
    label:  '低庫存品項',
    value:  kpi.value ? String(kpi.value.low_stock_count) : '—',
    icon:   '⚠️',
    color:  kpi.value && kpi.value.low_stock_count > 0 ? 'text-red-600 animate-pulse' : 'text-muted-foreground',
    link:   '/inventory',
  },
  {
    label:  '本月銷售額',
    value:  kpi.value ? formatCurrency(kpi.value.monthly_sales_amount) : '—',
    icon:   '💰',
    color:  'text-green-600',
    link:   '/reports/sales',
  },
])

// ── 折線圖運算 ────────────────────────────────────────────────────
const trendData = computed(() => reportsStore.salesTrend)

const chartWidth  = 700
const chartHeight = 200
const padLeft     = 50
const padRight    = 10
const padTop      = 10
const padBottom   = 30

const chartPoints = computed(() => {
  const data = trendData.value
  if (data.length === 0) return []

  const maxVal = Math.max(...data.map(d => d.amount), 1)
  const n      = data.length

  return data.map((d, i) => ({
    x: padLeft + (i / (n - 1 || 1)) * (chartWidth - padLeft - padRight),
    y: padTop + (1 - d.amount / maxVal) * (chartHeight - padTop - padBottom),
    date:   d.date,
    amount: d.amount,
    count:  d.order_count,
  }))
})

const polyline = computed(() =>
  chartPoints.value.map(p => `${p.x},${p.y}`).join(' ')
)

const yLabels = computed(() => {
  const data   = trendData.value
  const maxVal = Math.max(...data.map(d => d.amount), 1)
  const steps  = 4
  return Array.from({ length: steps + 1 }, (_, i) => {
    const val = (maxVal * i) / steps
    const y   = padTop + (1 - i / steps) * (chartHeight - padTop - padBottom)
    return { y, label: val >= 10000 ? `${(val / 10000).toFixed(1)}萬` : String(Math.round(val)) }
  })
})

const xLabels = computed(() => {
  const data = trendData.value
  if (data.length === 0) return []
  const total = data.length
  const step  = Math.max(1, Math.floor(total / 6))
  return data
    .filter((_, i) => i % step === 0 || i === total - 1)
    .map((d, _, arr) => {
      const origIndex = data.indexOf(d)
      const x = padLeft + (origIndex / (total - 1 || 1)) * (chartWidth - padLeft - padRight)
      return { x, label: d.date.slice(5) } // MM-DD
    })
})

// Tooltip 狀態
const tooltip = ref<{ x: number; y: number; date: string; amount: number; count: number } | null>(null)

function onMouseEnter(pt: typeof chartPoints.value[0]) {
  tooltip.value = pt
}
function onMouseLeave() {
  tooltip.value = null
}
</script>

<template>
  <div class="space-y-6">
    <!-- 歡迎列 -->
    <div>
      <h1 class="text-2xl font-semibold">
        歡迎回來，{{ authStore.user?.name ?? '使用者' }}
      </h1>
      <p class="mt-1 text-sm text-muted-foreground">
        {{ today }}
      </p>
    </div>

    <!-- KPI 卡片 -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <component
        :is="card.link ? 'NuxtLink' : 'div'"
        v-for="card in kpiCards"
        :key="card.label"
        :to="card.link ?? undefined"
        class="rounded-xl border bg-card p-5 shadow-sm"
        :class="card.link ? 'hover:border-primary/40 transition-colors cursor-pointer' : ''"
      >
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium text-muted-foreground">{{ card.label }}</p>
          <span class="text-2xl">{{ card.icon }}</span>
        </div>
        <p :class="['mt-2 text-3xl font-bold', card.color]">{{ card.value }}</p>
      </component>
    </div>

    <!-- 近 30 天銷售趨勢折線圖（T15-2） -->
    <div class="rounded-xl border bg-card p-5 shadow-sm">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-base font-semibold">近 30 天銷售趨勢</h2>
        <NuxtLink to="/reports/sales" class="text-xs text-primary hover:underline">查看完整報表 →</NuxtLink>
      </div>

      <div v-if="reportsStore.loading" class="flex h-48 items-center justify-center text-muted-foreground text-sm">
        載入中…
      </div>
      <div v-else-if="trendData.length === 0" class="flex h-48 items-center justify-center text-muted-foreground text-sm">
        本期間無銷售資料
      </div>
      <div v-else class="relative overflow-x-auto">
        <svg
          :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
          class="w-full"
          :style="`height:${chartHeight}px`"
          @mouseleave="onMouseLeave"
        >
          <!-- 格線 -->
          <line
            v-for="label in yLabels"
            :key="label.y"
            :x1="padLeft"
            :y1="label.y"
            :x2="chartWidth - padRight"
            :y2="label.y"
            class="stroke-border"
            stroke-dasharray="3,3"
          />

          <!-- Y 軸標籤 -->
          <text
            v-for="label in yLabels"
            :key="'y' + label.y"
            :x="padLeft - 4"
            :y="label.y + 4"
            text-anchor="end"
            class="fill-muted-foreground text-[10px]"
            font-size="10"
          >{{ label.label }}</text>

          <!-- X 軸標籤 -->
          <text
            v-for="label in xLabels"
            :key="'x' + label.x"
            :x="label.x"
            :y="chartHeight - 4"
            text-anchor="middle"
            class="fill-muted-foreground text-[10px]"
            font-size="10"
          >{{ label.label }}</text>

          <!-- 折線填充區域 -->
          <polygon
            v-if="chartPoints.length"
            :points="`${padLeft},${chartHeight - padBottom} ${polyline} ${chartWidth - padRight},${chartHeight - padBottom}`"
            fill="hsl(var(--primary) / 0.08)"
          />

          <!-- 折線 -->
          <polyline
            v-if="chartPoints.length"
            :points="polyline"
            fill="none"
            stroke="hsl(var(--primary))"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
          />

          <!-- 資料點（可 hover） -->
          <circle
            v-for="pt in chartPoints"
            :key="pt.date"
            :cx="pt.x"
            :cy="pt.y"
            r="4"
            fill="hsl(var(--primary))"
            class="cursor-pointer opacity-0 hover:opacity-100 transition-opacity"
            @mouseenter="onMouseEnter(pt)"
          />
        </svg>

        <!-- Tooltip -->
        <div
          v-if="tooltip"
          class="pointer-events-none absolute z-10 rounded-lg border bg-popover px-3 py-2 text-xs shadow-lg"
          :style="{ left: `${tooltip.x}px`, top: `${tooltip.y - 60}px`, transform: 'translateX(-50%)' }"
        >
          <p class="font-medium">{{ tooltip.date }}</p>
          <p class="text-muted-foreground">訂單數：{{ tooltip.count }}</p>
          <p class="text-primary font-semibold">{{ formatCurrency(tooltip.amount) }}</p>
        </div>
      </div>
    </div>

    <!-- 快捷入口 -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      <NuxtLink
        v-for="item in [
          { label: '進銷存彙總', to: '/reports/inventory-summary', icon: '📦' },
          { label: '銷售業績',   to: '/reports/sales',             icon: '📈' },
          { label: '毛利分析',   to: '/reports/profit',            icon: '💹' },
          { label: '採購報表',   to: '/reports/purchase',          icon: '🏭' },
        ]"
        :key="item.label"
        :to="item.to"
        class="flex flex-col items-center gap-1 rounded-xl border bg-card p-4 text-center shadow-sm hover:border-primary/40 transition-colors"
      >
        <span class="text-2xl">{{ item.icon }}</span>
        <span class="text-xs font-medium">{{ item.label }}</span>
      </NuxtLink>
    </div>
  </div>
</template>
