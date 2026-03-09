<script setup lang="ts">
import type { StockTransferStatus } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const store   = useStockTransfersStore()
const whStore = useWarehousesStore()

const statusFilter = ref<string>('')

async function load() {
  const params: Record<string, unknown> = {}
  if (statusFilter.value) params.status = statusFilter.value
  await store.fetchList(params)
}

onMounted(async () => {
  await Promise.all([whStore.fetchAll(), load()])
})

// ── 確認 / 取消 ───────────────────────────────────────────────────────
async function doConfirm(id: number) {
  if (!confirm('確定要確認此調撥單並扣減庫存？')) return
  await store.confirm(id)
}

async function doCancel(id: number) {
  if (!confirm('確定要取消此調撥單？')) return
  await store.cancel(id)
}

// ── 輔助 ─────────────────────────────────────────────────────────────
const statusLabels: Record<StockTransferStatus, string> = {
  draft:     '草稿',
  confirmed: '已確認',
  cancelled: '已取消',
}
const statusColors: Record<StockTransferStatus, string> = {
  draft:     'bg-gray-100 text-gray-600',
  confirmed: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-600',
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
        <h1 class="text-2xl font-semibold">庫存調撥</h1>
        <p class="mt-1 text-sm text-muted-foreground">共 {{ store.total }} 筆</p>
      </div>
      <NuxtLink
        to="/inventory/transfers/create"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
      >
        + 新增調撥單
      </NuxtLink>
    </div>

    <!-- 篩選列 -->
    <div class="flex gap-3">
      <select
        v-model="statusFilter"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @change="load"
      >
        <option value="">全部狀態</option>
        <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
      </select>
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
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">調撥單號</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">來源倉庫</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">目標倉庫</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">狀態</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">建立日期</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.transfers.length === 0">
            <td colspan="6" class="py-16 text-center text-muted-foreground">查無調撥記錄</td>
          </tr>
          <tr
            v-for="t in store.transfers"
            :key="t.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs font-semibold">{{ t.transfer_number }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ t.from_warehouse_name || t.from_warehouse_id }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ t.to_warehouse_name || t.to_warehouse_id }}</td>
            <td class="px-4 py-3">
              <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', statusColors[t.status]]">
                {{ statusLabels[t.status] }}
              </span>
            </td>
            <td class="px-4 py-3 text-muted-foreground">{{ formatDate(t.created_at) }}</td>
            <td class="px-4 py-3 text-right space-x-2">
              <button
                v-if="t.status === 'draft'"
                :disabled="store.confirming"
                class="text-sm text-green-600 hover:underline disabled:opacity-50"
                @click="doConfirm(t.id)"
              >
                確認
              </button>
              <button
                v-if="t.status === 'draft'"
                :disabled="store.cancelling"
                class="text-sm text-destructive hover:underline disabled:opacity-50"
                @click="doCancel(t.id)"
              >
                取消
              </button>
              <span v-if="t.status !== 'draft'" class="text-sm text-muted-foreground">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
