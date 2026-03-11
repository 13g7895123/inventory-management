<script setup lang="ts">
// 儀表板首頁 — Sprint 14/15 改版
definePageMeta({ layout: 'default' })

import { ClipboardList, ShoppingCart, AlertTriangle, DollarSign, TrendingUp, Calendar, ArrowRight } from 'lucide-vue-next'

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
    icon:   ClipboardList,
    class:  'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400',
    link:   '/sales/orders',
  },
  {
    label:  '待審核採購單',
    value:  kpi.value ? String(kpi.value.pending_purchase_orders) : '—',
    icon:   ShoppingCart,
    class:  'bg-blue-50 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400',
    link:   '/purchase/orders',
  },
  {
    label:  '低庫存品項',
    value:  kpi.value ? String(kpi.value.low_stock_count) : '—',
    icon:   AlertTriangle,
    class:  kpi.value && kpi.value.low_stock_count > 0 ? 'bg-red-50 text-red-600 animate-pulse dark:bg-red-950/40 dark:text-red-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
    link:   '/inventory',
  },
  {
    label:  '本月銷售額',
    value:  kpi.value ? formatCurrency(kpi.value.monthly_sales_amount) : '—',
    icon:   DollarSign,
    class:  'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400',
    link:   '/reports/sales',
  },
])

// ── 折線圖運算 ────────────────────────────────────────────────────
const trendData = computed(() => reportsStore.salesTrend)

const chartWidth  = 800
const chartHeight = 280
const padLeft     = 60
const padRight    = 20
const padTop      = 20
const padBottom   = 30

const chartPoints = computed(() => {
  const data = trendData.value
  if (data.length === 0) return []

  const maxVal = Math.max(...data.map(d => d.amount), 1000)
  const n      = data.length

  return data.map((d, i) => ({
    x: padLeft + (i / (n - 1 || 1)) * (chartWidth - padLeft - padRight),
    y: padTop + (1 - d.amount / maxVal) * (chartHeight - padTop - padBottom),
    date:   d.date,
    amount: d.amount,
    count:  d.order_count,
  }))
})

// Generate SVG Path command for smooth area
const areaPath = computed(() => {
  if (chartPoints.value.length === 0) return ''
  const points = chartPoints.value
  const first = points[0]
  const last = points[points.length - 1]
  
  let path = `M ${first.x} ${first.y}`
  points.forEach(p => { path += ` L ${p.x} ${p.y}` })
  
  // Close the area
  path += ` L ${last.x} ${chartHeight - padBottom} L ${first.x} ${chartHeight - padBottom} Z`
  return path
})


const polyline = computed(() =>
  chartPoints.value.map(p => `${p.x},${p.y}`).join(' ')
)

const yLabels = computed(() => {
  const data   = trendData.value
  const maxVal = Math.max(...data.map(d => d.amount), 1000)
  const steps  = 5
  return Array.from({ length: steps + 1 }, (_, i) => {
    const val = (maxVal * i) / steps
    const y   = padTop + (1 - i / steps) * (chartHeight - padTop - padBottom)
    let label = String(Math.round(val))
    if (val >= 10000) label = `${(val / 10000).toFixed(1)}萬`
    return { y, label }
  })
})

const xLabels = computed(() => {
  const data = trendData.value
  if (data.length === 0) return []
  const total = data.length
  // Show roughly 6-8 labels
  const step  = Math.max(1, Math.floor(total / 7)) 
  
  return data
    .filter((_, i) => i % step === 0 || i === total - 1)
    .map((d) => {
      const origIndex = data.indexOf(d)
      const x = padLeft + (origIndex / (total - 1 || 1)) * (chartWidth - padLeft - padRight)
       // Format as M/D
      const dateStr = d.date.split('-').slice(1).join('/')
      return { x, label: dateStr } 
    })
})

const hoveredPoint = ref<any>(null)
</script>

<template>
  <div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
    <!-- Welcome Header -->
    <div class="flex flex-col gap-2">
       <h1 class="text-3xl font-bold tracking-tight text-foreground">儀表板</h1>
       <p class="text-muted-foreground flex items-center gap-2">
          <Calendar class="w-4 h-4" />
          {{ today }}
       </p>
    </div>

    <!-- KPI Cards Grid -->
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
      <NuxtLink
        v-for="(card, i) in kpiCards"
        :key="i"
        :to="card.link"
        class="group relative overflow-hidden rounded-xl border bg-card p-6 shadow-sm transition-all hover:shadow-md hover:border-primary/20 hover:-translate-y-1"
      >
        <div class="flex items-start justify-between">
          <div>
            <p class="text-sm font-medium text-muted-foreground group-hover:text-primary transition-colors">{{ card.label }}</p>
            <div class="mt-4 flex items-baseline gap-1">
              <span class="text-2xl font-bold tracking-tight text-foreground">{{ card.value }}</span>
            </div>
          </div>
          <div :class="['p-3 rounded-xl transition-transform group-hover:scale-110', card.class]">
            <component :is="card.icon" class="h-5 w-5" />
          </div>
        </div>
        
        <!-- Decorative bg circle -->
        <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full opacity-[0.03] bg-current pointer-events-none group-hover:scale-150 transition-transform duration-500" :class="card.class.split(' ')[1]"></div>
      </NuxtLink>
    </div>

    <!-- Chart Section -->
    <div class="grid gap-6 grid-cols-1">
      <div class="rounded-xl border bg-card text-card-foreground shadow-sm">
        <div class="p-6 border-b flex items-center justify-between">
           <div>
             <h3 class="font-semibold text-lg flex items-center gap-2">
               <TrendingUp class="w-5 h-5 text-primary" />
               銷售趨勢分析
             </h3>
             <p class="text-sm text-muted-foreground mt-1">近 30 天每日銷售金額統計</p>
           </div>
           <!-- Legend or filters could go here -->
        </div>

        <div class="p-6">
          <div class="w-full relative overflow-hidden" @mouseleave="hoveredPoint = null">
            <!-- SVG Container -->
            <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="w-full h-auto max-h-[400px]" preserveAspectRatio="xMidYMid meet">
              <defs>
                <linearGradient id="chartGradient" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stop-color="hsl(var(--primary))" stop-opacity="0.25"/>
                  <stop offset="100%" stop-color="hsl(var(--primary))" stop-opacity="0"/>
                </linearGradient>
              </defs>

              <!-- Grid -->
              <g class="stroke-border stroke-[1] stroke-dasharray-4">
                 <line v-for="yl in yLabels" :key="yl.y" :x1="padLeft" :y1="yl.y" :x2="chartWidth - padRight" :y2="yl.y" />
              </g>

              <!-- Area -->
              <path :d="areaPath" fill="url(#chartGradient)" class="transition-all duration-300" />

              <!-- Line -->
              <polyline
                :points="polyline"
                fill="none"
                class="stroke-primary stroke-[3]"
                stroke-linecap="round"
                stroke-linejoin="round"
              />

              <!-- Hover Interaction Area & Tooltip Logic -->
              <g v-for="(p, i) in chartPoints" :key="i" class="group/point">
                 <!-- Invisible hit area (larger) -->
                 <rect 
                    :x="p.x - (chartWidth / chartPoints.length / 2)" 
                    :y="padTop" 
                    :width="chartWidth / chartPoints.length" 
                    :height="chartHeight - padBottom - padTop" 
                    fill="transparent" 
                    @mouseenter="hoveredPoint = p"
                 />
                 
                 <!-- Visible Dot -->
                 <circle 
                   :cx="p.x" 
                   :cy="p.y" 
                   r="5" 
                   class="fill-background stroke-primary stroke-[3] transition-opacity pointer-events-none" 
                   :class="hoveredPoint === p ? 'opacity-100' : 'opacity-0'" 
                 />
              </g>

              <!-- Axes -->
              <line :x1="padLeft" :y1="padTop" :x2="padLeft" :y2="chartHeight - padBottom" class="stroke-border stroke-[2]" />
              <line :x1="padLeft" :y1="chartHeight - padBottom" :x2="chartWidth - padRight" :y2="chartHeight - padBottom" class="stroke-border stroke-[2]" />

              <!-- Labels -->
              <text v-for="yl in yLabels" :key="yl.y" :x="padLeft - 10" :y="yl.y + 4" text-anchor="end" class="text-[11px] fill-muted-foreground font-medium select-none">{{ yl.label }}</text>
              <text v-for="xl in xLabels" :key="xl.x" :x="xl.x" :y="chartHeight - 10" text-anchor="middle" class="text-[11px] fill-muted-foreground font-medium select-none">{{ xl.label }}</text>
            </svg>
            
            <!-- Floating Tooltip (HTML overlay on top of SVG) -->
            <div v-if="hoveredPoint"
                 class="absolute pointer-events-none bg-popover text-popover-foreground border shadow-lg rounded-lg p-3 text-sm z-10 transform -translate-x-1/2 -translate-y-[130%]"
                 :style="{ left: (hoveredPoint.x / chartWidth * 100) + '%', top: (hoveredPoint.y / chartHeight * 100) + '%' }"
            >
               <div class="font-bold mb-1">{{ hoveredPoint.date }}</div>
               <div class="flex items-center gap-2 text-primary font-mono font-bold">
                 <span>{{ formatCurrency(hoveredPoint.amount) }}</span>
               </div>
               <div class="text-xs text-muted-foreground mt-1">訂單數: {{ hoveredPoint.count }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
