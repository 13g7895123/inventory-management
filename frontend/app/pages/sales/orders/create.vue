<script setup lang="ts">
import type { SalesOrderLineForm, Item, ItemSku } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const router        = useRouter()
const soStore       = useSalesOrderStore()
const customerStore = useCustomerStore()
const { get }       = useApi()

onMounted(() => customerStore.fetchAll())

// ── 基本欄位 ─────────────────────────────────────────────────────────
const customerId       = ref<number | ''>('')
const warehouseId      = ref<number | ''>('')
const orderDate        = ref(new Date().toISOString().substring(0, 10))
const expectedShipDate = ref('')
const taxRate          = ref(5)
const discountAmount   = ref(0)
const notes            = ref('')
const errorMsg         = ref('')

// ── 明細行 ───────────────────────────────────────────────────────────
interface LineRow extends SalesOrderLineForm {
  _key: number
  availableQty: number | null
}

const lines  = ref<LineRow[]>([])
let nextKey  = 0

function addEmptyLine() {
  lines.value.push({
    _key:         nextKey++,
    sku_id:       0,
    sku_code:     '',
    item_name:    '',
    ordered_qty:  1,
    unit_price:   0,
    discount_rate: 0,
    notes:        '',
    availableQty: null,
  })
}

function removeLine(key: number) {
  lines.value = lines.value.filter(l => l._key !== key)
}

// ── 庫存即時查詢 ─────────────────────────────────────────────────────
async function fetchInventory(line: LineRow) {
  if (!line.sku_id || !warehouseId.value) { line.availableQty = null; return }
  try {
    const res = await get<{ available_qty: number }>(`/inventory?sku_id=${line.sku_id}&warehouse_id=${warehouseId.value}`)
    line.availableQty = (res as { available_qty: number }).available_qty ?? null
  } catch {
    line.availableQty = null
  }
}

// 倉庫變更時，重新查詢所有行的庫存
watch(warehouseId, () => {
  lines.value.forEach(l => { if (l.sku_id) fetchInventory(l) })
})

// ── 小計 / Tax / 總計 ────────────────────────────────────────────────
function lineTotal(l: LineRow): number {
  const base = Number(l.ordered_qty) * Number(l.unit_price)
  const disc = Math.round(base * Number(l.discount_rate ?? 0)) / 100
  return base - disc
}

const subtotal   = computed(() => lines.value.reduce((s, l) => s + lineTotal(l), 0))
const afterDisc  = computed(() => subtotal.value - Number(discountAmount.value ?? 0))
const taxAmount  = computed(() => Math.round(afterDisc.value * taxRate.value) / 100)
const total      = computed(() => afterDisc.value + taxAmount.value)

function fmt(n: number) {
  return n.toLocaleString('zh-TW', { minimumFractionDigits: 0 })
}

// ── SKU 搜尋 Dialog ───────────────────────────────────────────────────
const skuDialogOpen    = ref(false)
const skuSearchKeyword = ref('')
const skuSearchLoading = ref(false)
const skuSearchItems   = ref<Item[]>([])
const skuSearchSkus    = ref<ItemSku[]>([])
const skuSearchItemId  = ref<number | null>(null)
const targetLineKey    = ref<number | null>(null)

function openSkuSearch(key: number) {
  targetLineKey.value    = key
  skuSearchKeyword.value = ''
  skuSearchItems.value   = []
  skuSearchSkus.value    = []
  skuSearchItemId.value  = null
  skuDialogOpen.value    = true
}

async function searchItems() {
  if (!skuSearchKeyword.value.trim()) return
  skuSearchLoading.value = true
  try {
    const res = await get<{ data: Item[] }>(`/items?keyword=${encodeURIComponent(skuSearchKeyword.value)}&per_page=20`)
    skuSearchItems.value = Array.isArray(res) ? res : (res as { data: Item[] }).data
  } catch {
    skuSearchItems.value = []
  } finally {
    skuSearchLoading.value = false
  }
}

async function selectItem(item: Item) {
  skuSearchItemId.value  = item.id
  skuSearchLoading.value = true
  try {
    const res = await get<{ data: ItemSku[] } | ItemSku[]>(`/items/${item.id}/skus`)
    skuSearchSkus.value = Array.isArray(res) ? res : (res as { data: ItemSku[] }).data
  } catch {
    skuSearchSkus.value = []
  } finally {
    skuSearchLoading.value = false
  }
}

function selectSku(item: Item, sku: ItemSku) {
  const line = lines.value.find(l => l._key === targetLineKey.value)
  if (line) {
    line.sku_id    = sku.id
    line.sku_code  = sku.sku_code
    line.item_name = item.name
    fetchInventory(line)
  }
  skuDialogOpen.value = false
}

// ── 提交 ─────────────────────────────────────────────────────────────
async function handleSubmit() {
  errorMsg.value = ''

  if (!customerId.value)  { errorMsg.value = '請選擇客戶';           return }
  if (!warehouseId.value) { errorMsg.value = '請輸入倉庫 ID';        return }
  if (!orderDate.value)   { errorMsg.value = '請輸入訂單日期';        return }
  if (lines.value.length === 0)          { errorMsg.value = '請至少加入一項商品'; return }
  if (lines.value.some(l => !l.sku_id)) { errorMsg.value = '請為每行選擇 SKU';   return }
  if (lines.value.some(l => Number(l.ordered_qty) <= 0)) {
    errorMsg.value = '訂購數量必須大於 0'; return
  }

  const payload = {
    customer_id:         Number(customerId.value),
    warehouse_id:        Number(warehouseId.value),
    order_date:          orderDate.value,
    expected_ship_date:  expectedShipDate.value || null,
    tax_rate:            taxRate.value,
    discount_amount:     Number(discountAmount.value ?? 0),
    notes:               notes.value || null,
    lines: lines.value.map(l => ({
      sku_id:        l.sku_id,
      ordered_qty:   Number(l.ordered_qty),
      unit_price:    Number(l.unit_price),
      discount_rate: Number(l.discount_rate ?? 0),
      notes:         l.notes || null,
    })),
  }

  try {
    const created = await soStore.create(payload)
    router.push(`/sales/orders/${created.id}`)
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '建立失敗，請稍後再試'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/sales/orders" class="hover:text-foreground">銷售訂單</NuxtLink>
      <span>/</span>
      <span class="text-foreground">新增</span>
    </div>

    <h1 class="text-2xl font-semibold">新增銷售訂單</h1>

    <!-- 錯誤訊息 -->
    <p
      v-if="errorMsg"
      class="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive"
    >
      {{ errorMsg }}
    </p>

    <!-- 基本資訊 -->
    <div class="rounded-lg border p-6 space-y-4">
      <h2 class="font-medium">基本資訊</h2>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- 客戶 -->
        <div class="space-y-1">
          <label class="text-sm font-medium">客戶 <span class="text-destructive">*</span></label>
          <select
            v-model="customerId"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          >
            <option value="">請選擇客戶</option>
            <option v-for="c in customerStore.customers" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </div>
        <!-- 倉庫 ID -->
        <div class="space-y-1">
          <label class="text-sm font-medium">倉庫 ID <span class="text-destructive">*</span></label>
          <input
            v-model.number="warehouseId"
            type="number"
            min="1"
            placeholder="輸入倉庫 ID"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
        <!-- 訂單日期 -->
        <div class="space-y-1">
          <label class="text-sm font-medium">訂單日期 <span class="text-destructive">*</span></label>
          <input
            v-model="orderDate"
            type="date"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
        <!-- 預計出貨日 -->
        <div class="space-y-1">
          <label class="text-sm font-medium">預計出貨日</label>
          <input
            v-model="expectedShipDate"
            type="date"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
        <!-- 稅率 -->
        <div class="space-y-1">
          <label class="text-sm font-medium">稅率 (%)</label>
          <input
            v-model.number="taxRate"
            type="number"
            min="0"
            max="100"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
        <!-- 整筆折扣 -->
        <div class="space-y-1">
          <label class="text-sm font-medium">整筆折扣金額</label>
          <input
            v-model.number="discountAmount"
            type="number"
            min="0"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
        <!-- 備註 -->
        <div class="sm:col-span-2 space-y-1">
          <label class="text-sm font-medium">備註</label>
          <textarea
            v-model="notes"
            rows="2"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
          />
        </div>
      </div>
    </div>

    <!-- 明細行 -->
    <div class="rounded-lg border p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="font-medium">訂單明細</h2>
        <button
          type="button"
          class="rounded-md border border-input px-3 py-1.5 text-sm hover:bg-muted/50"
          @click="addEmptyLine"
        >
          + 新增行
        </button>
      </div>

      <div v-if="lines.length === 0" class="py-8 text-center text-sm text-muted-foreground">
        尚無明細，請點擊「新增行」加入商品
      </div>

      <div v-for="line in lines" :key="line._key" class="rounded-md border p-4 space-y-3">
        <!-- 第一列：SKU 選擇 + 刪除 -->
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="shrink-0 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm hover:bg-muted/50"
            @click="openSkuSearch(line._key)"
          >
            {{ line.sku_id ? `${line.sku_code}` : '選擇 SKU' }}
          </button>
          <span class="text-sm">{{ line.item_name }}</span>

          <!-- 可用庫存提示 -->
          <span
            v-if="line.sku_id"
            class="ml-auto text-xs"
            :class="line.availableQty === null ? 'text-muted-foreground' : line.availableQty <= 0 ? 'text-destructive font-medium' : 'text-green-600'"
          >
            <template v-if="line.availableQty === null">庫存查詢中…</template>
            <template v-else>可用庫存：{{ line.availableQty }}</template>
          </span>

          <button
            type="button"
            class="ml-auto shrink-0 text-sm text-destructive hover:underline"
            :class="line.sku_id ? '' : 'ml-auto'"
            @click="removeLine(line._key)"
          >
            刪除
          </button>
        </div>

        <!-- 第二列：數量 / 單價 / 折扣 / 備註 -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div class="space-y-1">
            <label class="text-xs text-muted-foreground">訂購數量</label>
            <input
              v-model.number="line.ordered_qty"
              type="number"
              min="1"
              class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs text-muted-foreground">單價</label>
            <input
              v-model.number="line.unit_price"
              type="number"
              min="0"
              step="0.01"
              class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs text-muted-foreground">折扣率 (%)</label>
            <input
              v-model.number="line.discount_rate"
              type="number"
              min="0"
              max="100"
              class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-xs text-muted-foreground">行小計</label>
            <div class="px-2 py-1.5 text-sm font-medium text-right">{{ fmt(lineTotal(line)) }}</div>
          </div>
        </div>
        <div class="space-y-1">
          <label class="text-xs text-muted-foreground">備註</label>
          <input
            v-model="line.notes"
            type="text"
            placeholder="選填"
            class="w-full rounded-md border border-input bg-transparent px-2 py-1.5 text-sm focus-visible:outline-none"
          />
        </div>
      </div>
    </div>

    <!-- 金額摘要 -->
    <div class="ml-auto w-full max-w-xs rounded-lg border p-4 space-y-2 text-sm">
      <div class="flex justify-between">
        <span class="text-muted-foreground">小計</span>
        <span>{{ fmt(subtotal) }}</span>
      </div>
      <div class="flex justify-between">
        <span class="text-muted-foreground">整筆折扣</span>
        <span class="text-destructive">-{{ fmt(discountAmount) }}</span>
      </div>
      <div class="flex justify-between">
        <span class="text-muted-foreground">稅額 ({{ taxRate }}%)</span>
        <span>{{ fmt(taxAmount) }}</span>
      </div>
      <div class="flex justify-between border-t pt-2 font-semibold">
        <span>總計</span>
        <span>{{ fmt(total) }}</span>
      </div>
    </div>

    <!-- 操作按鈕 -->
    <div class="flex justify-end gap-3">
      <NuxtLink
        to="/sales/orders"
        class="rounded-md border border-input px-4 py-2 text-sm hover:bg-muted/50"
      >
        取消
      </NuxtLink>
      <button
        type="button"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60"
        :disabled="soStore.saving"
        @click="handleSubmit"
      >
        {{ soStore.saving ? '儲存中...' : '建立銷售單' }}
      </button>
    </div>
  </div>

  <!-- SKU 搜尋 Dialog -->
  <Teleport to="body">
    <div
      v-if="skuDialogOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
      @click.self="skuDialogOpen = false"
    >
      <div class="w-full max-w-lg rounded-lg bg-background p-6 shadow-xl space-y-4">
        <h3 class="font-medium">搜尋商品 / SKU</h3>

        <!-- 搜尋框 -->
        <div class="flex gap-2">
          <input
            v-model="skuSearchKeyword"
            type="text"
            placeholder="輸入商品名稱或 SKU 代碼"
            class="flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            @keyup.enter="searchItems"
          />
          <button
            type="button"
            class="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground hover:bg-primary/90"
            @click="searchItems"
          >
            搜尋
          </button>
        </div>

        <!-- 搜尋中 -->
        <div v-if="skuSearchLoading" class="flex justify-center py-6">
          <div class="h-6 w-6 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>

        <!-- 商品列表 -->
        <div v-else-if="!skuSearchItemId" class="max-h-72 overflow-y-auto divide-y">
          <div
            v-for="item in skuSearchItems"
            :key="item.id"
            class="flex items-center justify-between px-2 py-2.5 hover:bg-muted/50 cursor-pointer"
            @click="selectItem(item)"
          >
            <span class="text-sm">{{ item.name }}</span>
            <span class="text-xs text-muted-foreground">→ 選擇 SKU</span>
          </div>
          <div v-if="skuSearchItems.length === 0" class="py-6 text-center text-sm text-muted-foreground">
            找不到符合的商品
          </div>
        </div>

        <!-- SKU 列表 -->
        <div v-else class="max-h-72 overflow-y-auto divide-y">
          <button
            type="button"
            class="w-full text-left px-2 py-1.5 text-xs text-muted-foreground hover:underline"
            @click="skuSearchItemId = null"
          >
            ← 返回商品列表
          </button>
          <div
            v-for="sku in skuSearchSkus"
            :key="sku.id"
            class="flex items-center justify-between px-2 py-2.5 hover:bg-muted/50 cursor-pointer"
            @click="selectSku(skuSearchItems.find(i => i.id === skuSearchItemId)!, sku)"
          >
            <span class="font-mono text-sm">{{ sku.sku_code }}</span>
            <span class="text-xs text-muted-foreground">{{ sku.barcode || '-' }}</span>
          </div>
          <div v-if="skuSearchSkus.length === 0" class="py-6 text-center text-sm text-muted-foreground">
            此商品無可用 SKU
          </div>
        </div>

        <div class="flex justify-end">
          <button
            type="button"
            class="text-sm text-muted-foreground hover:underline"
            @click="skuDialogOpen = false"
          >
            關閉
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
