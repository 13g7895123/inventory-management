<script setup lang="ts">
import type { BatchSerialType, BatchSerialStatus } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const bsStore  = useBatchSerialsStore()
const whStore  = useWarehousesStore()

const batchNumber  = ref('')
const serialNumber = ref('')
const skuCode      = ref('')
const warehouseId  = ref<string>('')
const type         = ref<string>('')
const status       = ref<string>('')
const page         = ref(1)
const perPage      = 20

async function load() {
  const params: Record<string, unknown> = { page: page.value, per_page: perPage }
  if (batchNumber.value)  params.batch_number  = batchNumber.value
  if (serialNumber.value) params.serial_number = serialNumber.value
  if (skuCode.value)      params.sku_code       = skuCode.value
  if (warehouseId.value)  params.warehouse_id   = warehouseId.value
  if (type.value)         params.type           = type.value
  if (status.value)       params.status         = status.value
  await bsStore.fetchList(params)
}

onMounted(async () => {
  await Promise.all([whStore.fetchAll(), load()])
})

function doSearch() {
  page.value = 1
  load()
}

function onPageChange(p: number) {
  page.value = p
  load()
}

const totalPages = computed(() => Math.ceil(bsStore.total / perPage))

const typeLabels: Record<BatchSerialType, string> = {
  batch:  '批號',
  serial: '序號',
}

const statusLabels: Record<BatchSerialStatus, string> = {
  available: '可用',
  reserved:  '保留中',
  consumed:  '已消耗',
  expired:   '已過期',
}

const statusColors: Record<BatchSerialStatus, string> = {
  available: 'bg-green-100 text-green-700',
  reserved:  'bg-blue-100 text-blue-700',
  consumed:  'bg-gray-100 text-gray-500',
  expired:   'bg-red-100 text-red-600',
}

function formatDate(dt: string | null) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('zh-TW')
}

function isNearExpiry(expiryDate: string | null): boolean {
  if (!expiryDate) return false
  const diff = new Date(expiryDate).getTime() - Date.now()
  return diff > 0 && diff < 30 * 24 * 60 * 60 * 1000 // 30 天內
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">批號 / 序號追蹤</h1>
        <p class="mt-1 text-sm text-muted-foreground">依批號或序號查詢庫存異動軌跡，共 {{ bsStore.total }} 筆記錄</p>
      </div>
    </div>

    <!-- 篩選列 -->
    <div class="rounded-xl border bg-card p-4 space-y-3">
      <div class="flex flex-wrap gap-3">
        <input
          v-model="batchNumber"
          type="text"
          placeholder="批號搜尋…"
          class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-40"
          @keyup.enter="doSearch"
        />
        <input
          v-model="serialNumber"
          type="text"
          placeholder="序號搜尋…"
          class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-40"
          @keyup.enter="doSearch"
        />
        <input
          v-model="skuCode"
          type="text"
          placeholder="SKU 代碼…"
          class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-36"
          @keyup.enter="doSearch"
        />
        <select
          v-model="warehouseId"
          class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          @change="doSearch"
        >
          <option value="">全部倉庫</option>
          <option v-for="w in whStore.warehouses" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
        </select>
        <select
          v-model="type"
          class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          @change="doSearch"
        >
          <option value="">全部類型</option>
          <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
        </select>
        <select
          v-model="status"
          class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          @change="doSearch"
        >
          <option value="">全部狀態</option>
          <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
        </select>
        <button
          class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
          @click="doSearch"
        >
          搜尋
        </button>
      </div>
    </div>

    <!-- 錯誤 -->
    <div v-if="bsStore.error" class="rounded-md border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
      {{ bsStore.error }}
    </div>

    <!-- 表格 -->
    <div class="rounded-xl border bg-card overflow-hidden">
      <div v-if="bsStore.loading" class="py-16 text-center text-muted-foreground">載入中…</div>

      <table v-else class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">類型</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">批號 / 序號</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">SKU 代碼</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">商品名稱</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">倉庫</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">數量</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">單位成本</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">製造日期</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">到期日</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">狀態</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="bsStore.items.length === 0">
            <td colspan="10" class="py-16 text-center text-muted-foreground">查無批號 / 序號記錄</td>
          </tr>
          <tr
            v-for="bs in bsStore.items"
            :key="bs.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
            :class="bs.status === 'expired' ? 'opacity-60' : ''"
          >
            <td class="px-4 py-3">
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">
                {{ typeLabels[bs.type] }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="font-mono text-xs font-semibold">{{ bs.batch_number }}</div>
              <div v-if="bs.serial_number" class="mt-0.5 font-mono text-xs text-muted-foreground">{{ bs.serial_number }}</div>
            </td>
            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ bs.sku_code || bs.sku_id }}</td>
            <td class="px-4 py-3">{{ bs.item_name || '—' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ bs.warehouse_name || bs.warehouse_id }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ bs.quantity }}</td>
            <td class="px-4 py-3 text-right text-muted-foreground">
              {{ bs.unit_cost != null ? `$${Number(bs.unit_cost).toFixed(2)}` : '—' }}
            </td>
            <td class="px-4 py-3 text-muted-foreground">{{ formatDate(bs.manufactured_date) }}</td>
            <td class="px-4 py-3">
              <span
                :class="[
                  isNearExpiry(bs.expiry_date) ? 'text-amber-600 font-semibold' : 'text-muted-foreground',
                ]"
              >
                {{ formatDate(bs.expiry_date) }}
                <span v-if="isNearExpiry(bs.expiry_date)" class="ml-1 text-xs text-amber-500">⚠ 即將到期</span>
              </span>
            </td>
            <td class="px-4 py-3">
              <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', statusColors[bs.status]]">
                {{ statusLabels[bs.status] }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div v-if="totalPages > 1" class="flex items-center gap-2 justify-end">
      <button
        :disabled="page <= 1"
        class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted disabled:opacity-40 transition-colors"
        @click="onPageChange(page - 1)"
      >
        ‹ 上一頁
      </button>
      <span class="text-sm text-muted-foreground">{{ page }} / {{ totalPages }}</span>
      <button
        :disabled="page >= totalPages"
        class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted disabled:opacity-40 transition-colors"
        @click="onPageChange(page + 1)"
      >
        下一頁 ›
      </button>
    </div>
  </div>
</template>
