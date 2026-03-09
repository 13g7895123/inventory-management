<script setup lang="ts">
import type { InventoryTxType } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const invStore  = useInventoryStore()
const whStore   = useWarehousesStore()

const warehouseId = ref<string>('')
const skuCode     = ref('')
const txType      = ref<string>('')
const dateFrom    = ref('')
const dateTo      = ref('')

async function load() {
  const params: Record<string, unknown> = {}
  if (warehouseId.value) params.warehouse_id = warehouseId.value
  if (skuCode.value)     params.sku_code      = skuCode.value
  if (txType.value)      params.tx_type       = txType.value
  if (dateFrom.value)    params.date_from      = dateFrom.value
  if (dateTo.value)      params.date_to        = dateTo.value
  await invStore.fetchTransactions(params)
}

onMounted(async () => {
  await Promise.all([whStore.fetchAll(), load()])
})

function doSearch() { load() }

const txTypeLabels: Record<InventoryTxType, string> = {
  DEDUCT:       '扣減出庫',
  REPLENISH:    '補貨入庫',
  ADJUST:       '庫存調整',
  TRANSFER_IN:  '調撥入庫',
  TRANSFER_OUT: '調撥出庫',
}

const txTypeColors: Record<InventoryTxType, string> = {
  DEDUCT:       'bg-red-100 text-red-700',
  REPLENISH:    'bg-green-100 text-green-700',
  ADJUST:       'bg-blue-100 text-blue-700',
  TRANSFER_IN:  'bg-teal-100 text-teal-700',
  TRANSFER_OUT: 'bg-orange-100 text-orange-700',
}

function formatDate(dt: string) {
  return new Date(dt).toLocaleString('zh-TW', { hour12: false })
}

function qtyClass(qty: number) {
  return qty > 0 ? 'text-green-600' : qty < 0 ? 'text-red-600' : ''
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">庫存異動日誌</h1>
        <p class="mt-1 text-sm text-muted-foreground">共 {{ invStore.transactions.length }} 筆記錄</p>
      </div>
      <NuxtLink
        to="/inventory"
        class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
      >
        返回庫存查詢
      </NuxtLink>
    </div>

    <!-- 篩選列 -->
    <div class="flex flex-wrap gap-3">
      <select
        v-model="warehouseId"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @change="doSearch"
      >
        <option value="">全部倉庫</option>
        <option v-for="w in whStore.warehouses" :key="w.id" :value="String(w.id)">
          {{ w.name }}
        </option>
      </select>

      <input
        v-model="skuCode"
        type="text"
        placeholder="SKU 代碼…"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-40"
        @keyup.enter="doSearch"
      />

      <select
        v-model="txType"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @change="doSearch"
      >
        <option value="">全部類型</option>
        <option v-for="(label, key) in txTypeLabels" :key="key" :value="key">{{ label }}</option>
      </select>

      <input
        v-model="dateFrom"
        type="date"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @change="doSearch"
      />
      <span class="self-center text-muted-foreground text-sm">至</span>
      <input
        v-model="dateTo"
        type="date"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @change="doSearch"
      />

      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="doSearch"
      >
        搜尋
      </button>
    </div>

    <!-- 錯誤 -->
    <div v-if="invStore.error" class="rounded-md border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
      {{ invStore.error }}
    </div>

    <!-- 時間軸 -->
    <div v-if="invStore.loadingTx" class="py-16 text-center text-muted-foreground">載入中…</div>

    <div v-else-if="invStore.transactions.length === 0" class="py-16 text-center text-muted-foreground">
      查無異動記錄
    </div>

    <div v-else class="relative space-y-0 border-l-2 border-muted ml-4">
      <div
        v-for="tx in invStore.transactions"
        :key="tx.id"
        class="relative pl-8 pb-6"
      >
        <!-- 時間軸節點 -->
        <div
          class="absolute -left-[9px] top-1 h-4 w-4 rounded-full border-2 border-card"
          :class="txTypeColors[tx.tx_type]?.replace('text-', 'bg-').split(' ')[0] ?? 'bg-gray-300'"
        />

        <div class="rounded-xl border bg-card p-4 shadow-sm space-y-2">
          <div class="flex flex-wrap items-center gap-2">
            <span
              :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', txTypeColors[tx.tx_type] ?? 'bg-gray-100 text-gray-600']"
            >
              {{ txTypeLabels[tx.tx_type] ?? tx.tx_type }}
            </span>
            <span class="text-xs text-muted-foreground">{{ formatDate(tx.occurred_at) }}</span>
            <span v-if="tx.warehouse_name" class="text-xs text-muted-foreground">· {{ tx.warehouse_name }}</span>
          </div>

          <div class="flex flex-wrap gap-6 text-sm">
            <div>
              <span class="text-muted-foreground">SKU：</span>
              <span class="font-mono text-xs">{{ tx.sku_code || tx.sku_id }}</span>
              <span v-if="tx.item_name" class="ml-1 text-muted-foreground">{{ tx.item_name }}</span>
            </div>
            <div>
              <span class="text-muted-foreground">數量變動：</span>
              <span :class="['font-semibold', qtyClass(tx.qty_change)]">
                {{ tx.qty_change > 0 ? '+' : '' }}{{ tx.qty_change }}
              </span>
            </div>
            <div>
              <span class="text-muted-foreground">存後數量：</span>
              <span>{{ tx.qty_after }}</span>
            </div>
            <div v-if="tx.unit_cost != null">
              <span class="text-muted-foreground">單位成本：</span>
              <span>${{ Number(tx.unit_cost).toFixed(2) }}</span>
            </div>
          </div>

          <div v-if="tx.note" class="text-xs text-muted-foreground">備註：{{ tx.note }}</div>
        </div>
      </div>
    </div>
  </div>
</template>
