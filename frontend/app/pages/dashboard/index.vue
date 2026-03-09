<script setup lang="ts">
// 儀表板首頁
// auth.global.ts 全域 middleware 已保護此頁

const authStore = useAuthStore()
const invStore  = useInventoryStore()

onMounted(() => invStore.fetchLowStock())

const lowStockCount = computed(() => invStore.lowStockItems.length)

const kpiCards = computed(() => [
  { label: '待確認銷售單', value: '—',   icon: '📋', color: 'text-amber-600', link: null },
  { label: '待審核採購單', value: '—',   icon: '🛒', color: 'text-blue-600',  link: null },
  {
    label: '低庫存品項',
    value: invStore.loading ? '…' : String(lowStockCount.value),
    icon:  '⚠️',
    color: lowStockCount.value > 0 ? 'text-red-600 animate-pulse' : 'text-muted-foreground',
    link:  '/inventory',
  },
  { label: '本月銷售額', value: '—',   icon: '💰', color: 'text-green-600', link: null },
])
</script>

<template>
  <div class="space-y-6">
    <!-- 歡迎列 -->
    <div>
      <h1 class="text-2xl font-semibold">
        歡迎回來，{{ authStore.user?.name ?? '使用者' }}
      </h1>
      <p class="mt-1 text-sm text-muted-foreground">
        {{ new Date().toLocaleDateString('zh-TW', { year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' }) }}
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

    <!-- 提示區 -->
    <div class="rounded-xl border border-dashed bg-muted/20 p-8 text-center text-muted-foreground">
      <p class="text-sm">📊 圖表與即時數據將於 Sprint 14–15 實作</p>
    </div>
  </div>
</template>
