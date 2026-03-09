<script setup lang="ts">
// 儀表板首頁
// auth.global.ts 全域 middleware 已保護此頁
// TODO Sprint 14-15：加入 KPI 卡片、銷售折線圖、低庫存警示

const authStore = useAuthStore()

// KPI 卡片（Sprint 15 前暫用靜態）
const kpiCards = [
  { label: '待確認銷售單',   value: '—', icon: '📋', color: 'text-amber-600' },
  { label: '待審核採購單',   value: '—', icon: '🛒', color: 'text-blue-600'  },
  { label: '低庫存品項',     value: '—', icon: '⚠️', color: 'text-red-600'  },
  { label: '本月銷售額',     value: '—', icon: '💰', color: 'text-green-600' },
]
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
      <div
        v-for="card in kpiCards"
        :key="card.label"
        class="rounded-xl border bg-card p-5 shadow-sm"
      >
        <div class="flex items-center justify-between">
          <p class="text-sm font-medium text-muted-foreground">{{ card.label }}</p>
          <span class="text-2xl">{{ card.icon }}</span>
        </div>
        <p :class="['mt-2 text-3xl font-bold', card.color]">{{ card.value }}</p>
      </div>
    </div>

    <!-- 提示區 -->
    <div class="rounded-xl border border-dashed bg-muted/20 p-8 text-center text-muted-foreground">
      <p class="text-sm">📊 圖表與即時數據將於 Sprint 14–15 實作</p>
    </div>
  </div>
</template>
