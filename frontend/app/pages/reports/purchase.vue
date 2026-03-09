<script setup lang="ts">
// pages/reports/purchase.vue — 採購報表（T15-6）

definePageMeta({ layout: 'default' })

import type { PurchaseReportSupplierItem, PurchaseReportItemItem } from '~/app/types/api'

const reportsStore  = useReportsStore()
const supplierStore = useSupplierStore()

const dateFrom   = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10))
const dateTo     = ref(new Date().toISOString().slice(0, 10))
const supplierId = ref<number | null>(null)
const activeTab  = ref<'supplier' | 'item'>('supplier')
const loading    = ref(false)

onMounted(async () => {
  await supplierStore.fetchAll()
  await load()
})

async function load() {
  loading.value = true
  try {
    const params: Record<string, unknown> = {
      date_from: dateFrom.value,
      date_to:   dateTo.value,
    }
    if (supplierId.value) params.supplier_id = supplierId.value
    await reportsStore.fetchPurchaseReport(params)
  } finally {
    loading.value = false
  }
}

function handleExport() {
  const params: Record<string, unknown> = {
    date_from: dateFrom.value,
    date_to:   dateTo.value,
  }
  if (supplierId.value) params.supplier_id = supplierId.value
  reportsStore.downloadExport('purchase', params)
}

const report      = computed(() => reportsStore.purchaseReport)
const bySupplier  = computed((): PurchaseReportSupplierItem[] => report.value?.by_supplier ?? [])
const byItem      = computed((): PurchaseReportItemItem[]     => report.value?.by_item     ?? [])
const summary     = computed(() => report.value?.summary)

const formatCurrency = (n: number) =>
  new Intl.NumberFormat('zh-TW', { style: 'currency', currency: 'TWD', maximumFractionDigits: 0 }).format(n)
const formatN = (n: number) =>
  new Intl.NumberFormat('zh-TW', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)

// 圓餅圖替代（長條圖）— 廠商採購佔比
const supplierBars = computed(() => {
  const data = bySupplier.value.slice(0, 8)
  const max  = Math.max(...data.map(d => d.total_amount), 1)
  return data.map(d => ({
    name:   d.supplier_name,
    amount: d.total_amount,
    paid:   d.paid_amount,
    width:  (d.total_amount / max) * 100,
  }))
})
</script>

<template>
  <div class="space-y-5">
    <!-- 標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-xl font-semibold">採購報表</h1>
        <p class="text-sm text-muted-foreground">依廠商彙整採購金額與付款狀況</p>
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
        <label class="text-xs text-muted-foreground">廠商</label>
        <select v-model="supplierId" class="rounded-md border px-3 py-1.5 text-sm">
          <option :value="null">全部廠商</option>
          <option v-for="s in supplierStore.suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
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

    <!-- 彙總 -->
    <div v-if="summary" class="grid grid-cols-2 gap-4">
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">採購總額</p>
        <p class="mt-1 text-2xl font-bold text-blue-600">{{ formatCurrency(summary.total_purchase) }}</p>
      </div>
      <div class="rounded-xl border bg-card p-4">
        <p class="text-xs text-muted-foreground">採購訂單數</p>
        <p class="mt-1 text-2xl font-bold text-amber-600">{{ summary.total_orders }}</p>
      </div>
    </div>

    <!-- 廠商長條圖 -->
    <div v-if="supplierBars.length > 0" class="rounded-xl border bg-card p-5">
      <h2 class="mb-4 text-sm font-semibold">廠商採購佔比（依採購額）</h2>
      <div class="space-y-3">
        <div v-for="bar in supplierBars" :key="bar.name" class="space-y-1">
          <div class="flex justify-between text-xs">
            <span class="font-medium truncate max-w-[200px]">{{ bar.name }}</span>
            <span class="text-muted-foreground">{{ formatCurrency(bar.amount) }}</span>
          </div>
          <div class="flex h-4 gap-0.5 rounded-full bg-muted overflow-hidden">
            <div
              class="h-full bg-blue-500 rounded-full transition-all duration-500"
              :style="`width: ${bar.width * (bar.paid / bar.amount)}%`"
            />
            <div
              class="h-full bg-blue-200 transition-all duration-500"
              :style="`width: ${bar.width - bar.width * (bar.paid / bar.amount)}%`"
            />
          </div>
          <p class="text-[10px] text-muted-foreground">
            已付 {{ formatCurrency(bar.paid) }}（{{ bar.amount > 0 ? ((bar.paid / bar.amount) * 100).toFixed(0) : 0 }}%）
          </p>
        </div>
      </div>
      <div class="mt-3 flex gap-4 text-xs text-muted-foreground">
        <span class="flex items-center gap-1"><span class="inline-block h-2 w-4 rounded bg-blue-500" /> 已付款</span>
        <span class="flex items-center gap-1"><span class="inline-block h-2 w-4 rounded bg-blue-200" /> 未付款</span>
      </div>
    </div>

    <!-- 頁籤 -->
    <div class="flex gap-1 rounded-xl border bg-muted p-1 w-fit">
      <button
        :class="['rounded-lg px-4 py-1.5 text-sm font-medium transition-colors', activeTab === 'supplier' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground']"
        @click="activeTab = 'supplier'"
      >依廠商</button>
      <button
        :class="['rounded-lg px-4 py-1.5 text-sm font-medium transition-colors', activeTab === 'item' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground']"
        @click="activeTab = 'item'"
      >依品項</button>
    </div>

    <!-- 依廠商表格 -->
    <div v-if="activeTab === 'supplier'" class="rounded-xl border bg-card overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/40">
              <th class="px-4 py-3 text-left font-medium">廠商名稱</th>
              <th class="px-4 py-3 text-right font-medium">訂單數</th>
              <th class="px-4 py-3 text-right font-medium">採購總額</th>
              <th class="px-4 py-3 text-right font-medium">已付金額</th>
              <th class="px-4 py-3 text-right font-medium">付款率</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">載入中…</td>
            </tr>
            <tr v-else-if="bySupplier.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">查無資料</td>
            </tr>
            <tr v-for="item in bySupplier" :key="item.supplier_id" class="border-b hover:bg-muted/20 transition-colors">
              <td class="px-4 py-3 font-medium">{{ item.supplier_name }}</td>
              <td class="px-4 py-3 text-right">{{ item.order_count }}</td>
              <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(item.total_amount) }}</td>
              <td class="px-4 py-3 text-right text-green-600">{{ formatCurrency(item.paid_amount) }}</td>
              <td class="px-4 py-3 text-right">
                <span
                  class="rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="item.total_amount > 0 && item.paid_amount >= item.total_amount
                    ? 'bg-green-100 text-green-700'
                    : item.paid_amount > 0 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-600'"
                >
                  {{ item.total_amount > 0 ? ((item.paid_amount / item.total_amount) * 100).toFixed(0) : 0 }}%
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 依品項表格 -->
    <div v-if="activeTab === 'item'" class="rounded-xl border bg-card overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b bg-muted/40">
              <th class="px-4 py-3 text-left font-medium">SKU 編號</th>
              <th class="px-4 py-3 text-left font-medium">品名</th>
              <th class="px-4 py-3 text-right font-medium">採購量</th>
              <th class="px-4 py-3 text-right font-medium">已驗收量</th>
              <th class="px-4 py-3 text-right font-medium">採購金額</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">載入中…</td>
            </tr>
            <tr v-else-if="byItem.length === 0">
              <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">查無資料</td>
            </tr>
            <tr v-for="item in byItem" :key="item.sku_id" class="border-b hover:bg-muted/20 transition-colors">
              <td class="px-4 py-3 font-mono text-xs">{{ item.sku_code }}</td>
              <td class="px-4 py-3">{{ item.item_name }}</td>
              <td class="px-4 py-3 text-right">{{ formatN(item.total_ordered_qty) }}</td>
              <td class="px-4 py-3 text-right text-green-600">{{ formatN(item.total_received_qty) }}</td>
              <td class="px-4 py-3 text-right font-semibold">{{ formatCurrency(item.total_amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
