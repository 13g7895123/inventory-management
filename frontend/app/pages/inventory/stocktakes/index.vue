<script setup lang="ts">
import type { StocktakeStatus } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const store   = useStocktakesStore()
const whStore = useWarehousesStore()

onMounted(async () => {
  await Promise.all([whStore.fetchAll(), store.fetchList()])
})

async function doStart(id: number) {
  if (!confirm('確定要開始盤點？開始後將鎖定庫存快照。')) return
  await store.start(id)
}

async function doCancel(id: number) {
  if (!confirm('確定要取消此盤點任務？')) return
  await store.cancel(id)
}

const statusLabels: Record<StocktakeStatus, string> = {
  draft:       '草稿',
  in_progress: '盤點中',
  confirmed:   '已確認',
  cancelled:   '已取消',
}
const statusColors: Record<StocktakeStatus, string> = {
  draft:       'bg-gray-100 text-gray-600',
  in_progress: 'bg-blue-100 text-blue-700',
  confirmed:   'bg-green-100 text-green-700',
  cancelled:   'bg-red-100 text-red-600',
}

function formatDate(dt: string) {
  return new Date(dt).toLocaleDateString('zh-TW')
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">盤點管理</h1>
        <p class="mt-1 text-sm text-muted-foreground">共 {{ store.total }} 筆</p>
      </div>
      <NuxtLink
        to="/inventory/stocktakes/create"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
      >
        + 新增盤點任務
      </NuxtLink>
    </div>

    <!-- 錯誤 -->
    <div v-if="store.error" class="rounded-md border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
      {{ store.error }}
    </div>

    <!-- 表格 -->
    <div class="rounded-xl border bg-card overflow-hidden">
      <div v-if="store.loading" class="py-16 text-center text-muted-foreground">載入中…</div>

      <table v-else class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">盤點單號</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">倉庫</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">狀態</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">建立日期</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.stocktakes.length === 0">
            <td colspan="5" class="py-16 text-center text-muted-foreground">查無盤點記錄</td>
          </tr>
          <tr
            v-for="s in store.stocktakes"
            :key="s.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs font-semibold">{{ s.stocktake_number }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ s.warehouse_name || s.warehouse_id }}</td>
            <td class="px-4 py-3">
              <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', statusColors[s.status]]">
                {{ statusLabels[s.status] }}
              </span>
            </td>
            <td class="px-4 py-3 text-muted-foreground">{{ formatDate(s.created_at) }}</td>
            <td class="px-4 py-3 text-right space-x-2">
              <button
                v-if="s.status === 'draft'"
                :disabled="store.saving"
                class="text-sm text-blue-600 hover:underline disabled:opacity-50"
                @click="doStart(s.id)"
              >
                開始盤點
              </button>
              <NuxtLink
                v-if="s.status === 'in_progress'"
                :to="`/inventory/stocktakes/${s.id}`"
                class="text-sm text-primary hover:underline"
              >
                執行盤點
              </NuxtLink>
              <NuxtLink
                v-if="s.status === 'in_progress'"
                :to="`/inventory/stocktakes/${s.id}/confirm`"
                class="text-sm text-green-600 hover:underline"
              >
                確認盤點
              </NuxtLink>
              <button
                v-if="s.status === 'draft' || s.status === 'in_progress'"
                :disabled="store.saving"
                class="text-sm text-destructive hover:underline disabled:opacity-50"
                @click="doCancel(s.id)"
              >
                取消
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
