<script setup lang="ts">
definePageMeta({ layout: 'default' })

const invStore  = useInventoryStore()
const whStore   = useWarehousesStore()

const warehouseId = ref<string>('')
const skuCode     = ref('')
const page        = ref(1)
const perPage     = 20

async function load() {
  const params: Record<string, unknown> = { page: page.value, per_page: perPage }
  if (warehouseId.value) params.warehouse_id = warehouseId.value
  if (skuCode.value)     params.sku_code      = skuCode.value
  await invStore.fetchList(params)
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

const totalPages = computed(() => Math.ceil(invStore.total / perPage))

function stockClass(item: { on_hand_qty: number; available_qty: number }) {
  if (item.available_qty <= 0) return 'text-destructive font-semibold'
  if (item.available_qty <= 5) return 'text-amber-600 font-semibold'
  return ''
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">庫存查詢</h1>
        <p class="mt-1 text-sm text-muted-foreground">共 {{ invStore.total }} 筆庫存記錄</p>
      </div>
      <NuxtLink
        to="/inventory/transactions"
        class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
      >
        異動日誌
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
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-44"
        @keyup.enter="doSearch"
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

    <!-- 表格 -->
    <div class="rounded-xl border bg-card overflow-hidden">
      <div v-if="invStore.loading" class="py-16 text-center text-muted-foreground">載入中…</div>

      <table v-else class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">SKU 代碼</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">商品名稱</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">倉庫</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">現有量</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">保留量</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">在途量</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">可用量</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">平均成本</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="invStore.items.length === 0">
            <td colspan="8" class="py-16 text-center text-muted-foreground">查無庫存資料</td>
          </tr>
          <tr
            v-for="item in invStore.items"
            :key="item.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ item.sku_code || item.sku_id }}</td>
            <td class="px-4 py-3">{{ item.item_name || '—' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ item.warehouse_name || item.warehouse_id }}</td>
            <td class="px-4 py-3 text-right">{{ item.on_hand_qty }}</td>
            <td class="px-4 py-3 text-right text-amber-600">{{ item.reserved_qty }}</td>
            <td class="px-4 py-3 text-right text-blue-600">{{ item.on_order_qty }}</td>
            <td class="px-4 py-3 text-right" :class="stockClass(item)">{{ item.available_qty }}</td>
            <td class="px-4 py-3 text-right text-muted-foreground">
              {{ item.avg_cost != null ? `$${Number(item.avg_cost).toFixed(2)}` : '—' }}
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
