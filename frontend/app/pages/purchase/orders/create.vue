<script setup lang="ts">
import type { PurchaseOrderLineForm, Item, ItemSku } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const router        = useRouter()
const poStore       = usePurchaseOrderStore()
const supplierStore = useSupplierStore()
const { get }       = useApi()

onMounted(() => supplierStore.fetchAll())

// ── 基本欄位 ─────────────────────────────────────────────────────────
const supplierId   = ref<number | ''>('')
const warehouseId  = ref<number | ''>('')
const expectedDate = ref('')
const taxRate      = ref(5)
const notes        = ref('')
const errorMsg     = ref('')

// ── 明細行 ───────────────────────────────────────────────────────────
interface LineRow extends PurchaseOrderLineForm {
  _key: number
}

const lines    = ref<LineRow[]>([])
let nextKey = 0

function addEmptyLine() {
  lines.value.push({
    _key: nextKey++,
    sku_id: 0,
    sku_code: '',
    item_name: '',
    ordered_qty: 1,
    unit_price: 0,
    notes: '',
  })
}

function removeLine(key: number) {
  lines.value = lines.value.filter(l => l._key !== key)
}

// ── 小計 / Tax / 總計 ────────────────────────────────────────────────
const subtotal = computed(() =>
  lines.value.reduce((s, l) => s + Number(l.ordered_qty) * Number(l.unit_price), 0)
)
const taxAmount = computed(() => Math.round(subtotal.value * taxRate.value) / 100)
const total     = computed(() => subtotal.value + taxAmount.value)

function lineTotal(l: LineRow) {
  return Number(l.ordered_qty) * Number(l.unit_price)
}

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
  skuSearchItemId.value = item.id
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
  }
  skuDialogOpen.value = false
}

// ── 提交 ─────────────────────────────────────────────────────────────
async function handleSubmit() {
  errorMsg.value = ''

  if (!supplierId.value) { errorMsg.value = '請選擇供應商'; return }
  if (!warehouseId.value) { errorMsg.value = '請輸入倉庫 ID'; return }
  if (lines.value.length === 0) { errorMsg.value = '請至少加入一項商品'; return }
  if (lines.value.some(l => !l.sku_id)) { errorMsg.value = '請為每筆明細選擇 SKU'; return }
  if (lines.value.some(l => Number(l.ordered_qty) <= 0)) { errorMsg.value = '訂購數量必須大於 0'; return }

  const payload = {
    supplier_id:   Number(supplierId.value),
    warehouse_id:  Number(warehouseId.value),
    expected_date: expectedDate.value || null,
    tax_rate:      taxRate.value,
    notes:         notes.value || null,
    lines: lines.value.map(l => ({
      sku_id:     l.sku_id,
      ordered_qty: Number(l.ordered_qty),
      unit_price:  Number(l.unit_price),
      notes:       l.notes || null,
    })),
  }

  try {
    const created = await poStore.create(payload)
    router.push(`/purchase/orders/${created.id}`)
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '建立失敗，請稍後再試'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/purchase/orders" class="hover:text-foreground">採購單</NuxtLink>
      <span>/</span>
      <span class="text-foreground">新增</span>
    </div>

    <!-- 標題 -->
    <h1 class="text-2xl font-semibold">新增採購單</h1>

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
        <!-- 供應商 -->
        <div class="space-y-1">
          <label class="text-sm font-medium">供應商 <span class="text-destructive">*</span></label>
          <select
            v-model="supplierId"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          >
            <option value="">請選擇供應商</option>
            <option v-for="s in supplierStore.activeSuppliers" :key="s.id" :value="s.id">
              {{ s.name }}
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
        <!-- 預計到貨日 -->
        <div class="space-y-1">
          <label class="text-sm font-medium">預計到貨日</label>
          <input
            v-model="expectedDate"
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
            step="0.1"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
        <!-- 備註 -->
        <div class="space-y-1 sm:col-span-2">
          <label class="text-sm font-medium">備註</label>
          <textarea
            v-model="notes"
            rows="2"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
          />
        </div>
      </div>
    </div>

    <!-- 訂購明細 -->
    <div class="rounded-lg border p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="font-medium">訂購明細</h2>
        <button
          type="button"
          class="rounded-md bg-secondary px-3 py-1.5 text-sm font-medium hover:bg-secondary/80 transition-colors"
          @click="addEmptyLine"
        >
          + 加入商品
        </button>
      </div>

      <div v-if="lines.length === 0" class="rounded border border-dashed py-8 text-center text-sm text-muted-foreground">
        尚未加入任何商品，點選「加入商品」開始
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-muted/50">
            <tr>
              <th class="px-3 py-2 text-left font-medium w-40">SKU</th>
              <th class="px-3 py-2 text-left font-medium">品名</th>
              <th class="px-3 py-2 text-right font-medium w-24">數量</th>
              <th class="px-3 py-2 text-right font-medium w-28">單價</th>
              <th class="px-3 py-2 text-right font-medium w-28">小計</th>
              <th class="px-3 py-2 text-center font-medium w-16">備註</th>
              <th class="px-3 py-2 w-10" />
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="line in lines" :key="line._key" class="hover:bg-muted/20">
              <!-- SKU -->
              <td class="px-3 py-2">
                <div class="flex items-center gap-1">
                  <span class="font-mono text-xs text-muted-foreground min-w-0 truncate">
                    {{ line.sku_code || '未選擇' }}
                  </span>
                  <button
                    type="button"
                    class="shrink-0 rounded px-1.5 py-0.5 text-xs border hover:bg-muted"
                    @click="openSkuSearch(line._key)"
                  >
                    選
                  </button>
                </div>
              </td>
              <!-- 品名 -->
              <td class="px-3 py-2 text-muted-foreground truncate max-w-xs">
                {{ line.item_name || '-' }}
              </td>
              <!-- 數量 -->
              <td class="px-3 py-2">
                <input
                  v-model.number="line.ordered_qty"
                  type="number"
                  min="1"
                  class="w-full rounded border border-input bg-transparent px-2 py-1 text-right text-sm focus-visible:outline-none"
                />
              </td>
              <!-- 單價 -->
              <td class="px-3 py-2">
                <input
                  v-model.number="line.unit_price"
                  type="number"
                  min="0"
                  step="0.01"
                  class="w-full rounded border border-input bg-transparent px-2 py-1 text-right text-sm focus-visible:outline-none"
                />
              </td>
              <!-- 小計 -->
              <td class="px-3 py-2 text-right font-medium">
                {{ fmt(lineTotal(line)) }}
              </td>
              <!-- 備註 -->
              <td class="px-3 py-2">
                <input
                  v-model="line.notes"
                  type="text"
                  placeholder="備註"
                  class="w-full rounded border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none"
                />
              </td>
              <!-- 刪除 -->
              <td class="px-3 py-2 text-center">
                <button
                  type="button"
                  class="text-destructive hover:opacity-70"
                  @click="removeLine(line._key)"
                >
                  ✕
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 合計 -->
      <div v-if="lines.length > 0" class="flex justify-end">
        <div class="w-56 space-y-1 text-sm">
          <div class="flex justify-between">
            <span class="text-muted-foreground">小計</span>
            <span>{{ fmt(subtotal) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-muted-foreground">稅額 ({{ taxRate }}%)</span>
            <span>{{ fmt(taxAmount) }}</span>
          </div>
          <div class="flex justify-between font-semibold border-t pt-1 mt-1">
            <span>總計</span>
            <span>{{ fmt(total) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 操作按鈕 -->
    <div class="flex justify-end gap-3">
      <NuxtLink
        to="/purchase/orders"
        class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
      >
        取消
      </NuxtLink>
      <button
        type="button"
        :disabled="poStore.saving"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
        @click="handleSubmit"
      >
        {{ poStore.saving ? '建立中…' : '建立採購單' }}
      </button>
    </div>
  </div>

  <!-- SKU 搜尋 Dialog -->
  <Teleport to="body">
    <div
      v-if="skuDialogOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="skuDialogOpen = false"
    >
      <div class="w-full max-w-lg rounded-xl bg-background shadow-xl p-6 space-y-4">
        <h2 class="text-lg font-semibold">選擇商品 SKU</h2>

        <!-- 搜尋 -->
        <div class="flex gap-2">
          <input
            v-model="skuSearchKeyword"
            type="text"
            placeholder="搜尋商品名稱或代碼…"
            class="flex-1 rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            @keyup.enter="searchItems"
          />
          <button
            class="rounded-md border px-3 py-2 text-sm hover:bg-muted transition-colors"
            @click="searchItems"
          >
            搜尋
          </button>
        </div>

        <!-- Loading -->
        <div v-if="skuSearchLoading" class="flex justify-center py-4">
          <div class="h-6 w-6 animate-spin rounded-full border-4 border-primary border-t-transparent" />
        </div>

        <!-- 商品列表 -->
        <div v-else-if="skuSearchItems.length && !skuSearchItemId" class="max-h-64 overflow-y-auto space-y-1">
          <button
            v-for="item in skuSearchItems"
            :key="item.id"
            class="w-full text-left rounded-md px-3 py-2 text-sm hover:bg-muted transition-colors"
            @click="selectItem(item)"
          >
            <span class="font-medium">{{ item.name }}</span>
            <span class="ml-2 text-xs text-muted-foreground">{{ item.category_name ?? '' }}</span>
          </button>
        </div>

        <!-- SKU 列表 -->
        <div v-else-if="skuSearchItemId" class="max-h-64 overflow-y-auto space-y-1">
          <button
            class="mb-2 text-sm text-blue-600 hover:underline"
            @click="skuSearchItemId = null"
          >
            ← 返回商品列表
          </button>
          <div v-if="skuSearchSkus.length === 0" class="text-sm text-muted-foreground py-4 text-center">
            此商品無 SKU
          </div>
          <button
            v-for="sku in skuSearchSkus"
            :key="sku.id"
            class="w-full text-left rounded-md px-3 py-2 text-sm hover:bg-muted transition-colors"
            @click="selectSku(skuSearchItems.find(i => i.id === skuSearchItemId)!, sku)"
          >
            <span class="font-mono text-xs font-semibold">{{ sku.sku_code }}</span>
            <span v-if="sku.attributes" class="ml-2 text-xs text-muted-foreground">{{ JSON.stringify(sku.attributes) }}</span>
          </button>
        </div>

        <div
          v-else-if="!skuSearchLoading && skuSearchItems.length === 0 && skuSearchKeyword"
          class="text-sm text-muted-foreground text-center py-4"
        >
          查無結果
        </div>

        <div class="flex justify-end">
          <button
            class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
            @click="skuDialogOpen = false"
          >
            關閉
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
