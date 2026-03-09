<script setup lang="ts">
// pages/reports/index.vue — 報表中心（T15 導覽頁）

const reports = [
  {
    title:       '進銷存彙總表',
    description: '查詢指定期間的期初/期末庫存、入出庫量，支援 Excel 匯出',
    icon:        '📦',
    to:          '/reports/inventory-summary',
    color:       'border-blue-200 hover:border-blue-400',
    badge:       'T15-3',
  },
  {
    title:       '銷售業績報表',
    description: '依 SKU 與客戶彙整銷售量、銷售額，附圖表分析',
    icon:        '📈',
    to:          '/reports/sales',
    color:       'border-green-200 hover:border-green-400',
    badge:       'T15-4',
  },
  {
    title:       '毛利分析報表',
    description: '銷售收入扣除銷售成本，計算毛利率與每日趨勢',
    icon:        '💹',
    to:          '/reports/profit',
    color:       'border-purple-200 hover:border-purple-400',
    badge:       'T15-5',
  },
  {
    title:       '採購報表',
    description: '依廠商彙整採購金額、付款狀況，附品項採購明細',
    icon:        '🏭',
    to:          '/reports/purchase',
    color:       'border-amber-200 hover:border-amber-400',
    badge:       'T15-6',
  },
  {
    title:       '庫存週轉率',
    description: '計算各 SKU 在指定期間的庫存週轉率與週轉天數',
    icon:        '🔄',
    to:          '/reports/turnover',
    color:       'border-rose-200 hover:border-rose-400',
    badge:       'T14-5',
  },
]
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-semibold">報表中心</h1>
      <p class="mt-1 text-sm text-muted-foreground">選擇所需報表進行查詢與匯出</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <NuxtLink
        v-for="report in reports"
        :key="report.title"
        :to="report.to"
        :class="['flex flex-col gap-3 rounded-xl border bg-card p-6 shadow-sm transition-colors cursor-pointer', report.color]"
      >
        <div class="flex items-start justify-between">
          <span class="text-3xl">{{ report.icon }}</span>
          <span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-mono text-muted-foreground">{{ report.badge }}</span>
        </div>
        <div>
          <p class="font-semibold">{{ report.title }}</p>
          <p class="mt-1 text-sm text-muted-foreground leading-relaxed">{{ report.description }}</p>
        </div>
        <p class="text-xs text-primary font-medium">前往查詢 →</p>
      </NuxtLink>
    </div>
  </div>
</template>
