<script setup lang="ts">
import type { StockTransferLineForm } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const router  = useRouter()
const store   = useStockTransfersStore()
const whStore = useWarehousesStore()
const invStore = useInventoryStore()

onMounted(() => whStore.fetchAll())

const form = reactive({
  from_warehouse_id: '' as string | number,
  to_warehouse_id:   '' as string | number,
  reason:            '',
  notes:             '',
})

// ── 明細行 ─────────────────────────────────────────────────────────────
const lines = ref<StockTransferLineForm[]>([])

function addLine() {
  lines.value.push({ sku_id: 0, qty: 1, sku_code: '', item_name: '' })
}

function removeLine(idx: number) {
  lines.value.splice(idx, 1)
}

// SKU 搜尋（用 sku_code 欄位先帶入，實際 sku_id 需透過庫存查詢補足）
async function onSkuCodeChange(idx: number) {
  const code = lines.value[idx].sku_code?.trim()
  if (!code) return
  try {
    await invStore.fetchList({ sku_code: code, per_page: 1 })
    const inv = invStore.items[0]
    if (inv) {
      lines.value[idx].sku_id = inv.sku_id
      lines.value[idx].item_name = inv.item_name
    }
  } catch {
    // ignore
  }
}

const isValid = computed(() =>
  form.from_warehouse_id &&
  form.to_warehouse_id &&
  form.from_warehouse_id !== form.to_warehouse_id &&
  lines.value.length > 0 &&
  lines.value.every(l => l.sku_id > 0 && l.qty > 0),
)

async function submit() {
  if (!isValid.value) return
  try {
    const payload = {
      from_warehouse_id: Number(form.from_warehouse_id),
      to_warehouse_id:   Number(form.to_warehouse_id),
      reason:            form.reason || undefined,
      notes:             form.notes  || undefined,
      lines: lines.value.map(l => ({
        sku_id:       l.sku_id,
        qty:          l.qty,
        batch_number: l.batch_number || undefined,
        notes:        l.notes        || undefined,
      })),
    }
    const created = await store.create(payload)
    router.push('/inventory/transfers')
  } catch {
    // error held in store
  }
}
</script>

<template>
  <div class="space-y-6 max-w-3xl">
    <!-- 標題列 -->
    <div class="flex items-center gap-3">
      <NuxtLink to="/inventory/transfers" class="text-muted-foreground hover:text-foreground">←</NuxtLink>
      <h1 class="text-2xl font-semibold">新增調撥單</h1>
    </div>

    <!-- 錯誤 -->
    <div v-if="store.error" class="rounded-md border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
      {{ store.error }}
    </div>

    <!-- 基本資料 -->
    <div class="rounded-xl border bg-card p-6 space-y-4">
      <h2 class="font-semibold">調撥資訊</h2>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1 block text-sm font-medium">來源倉庫 <span class="text-destructive">*</span></label>
          <select
            v-model="form.from_warehouse_id"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          >
            <option value="">請選擇</option>
            <option
              v-for="w in whStore.warehouses.filter(w => w.is_active)"
              :key="w.id"
              :value="w.id"
            >
              {{ w.name }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium">目標倉庫 <span class="text-destructive">*</span></label>
          <select
            v-model="form.to_warehouse_id"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          >
            <option value="">請選擇</option>
            <option
              v-for="w in whStore.warehouses.filter(w => w.is_active && w.id !== Number(form.from_warehouse_id))"
              :key="w.id"
              :value="w.id"
            >
              {{ w.name }}
            </option>
          </select>
        </div>
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium">調撥原因</label>
        <input
          v-model="form.reason"
          type="text"
          class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium">備註</label>
        <textarea
          v-model="form.notes"
          rows="2"
          class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
        />
      </div>
    </div>

    <!-- 調撥明細 -->
    <div class="rounded-xl border bg-card p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="font-semibold">調撥品項</h2>
        <button
          class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted transition-colors"
          @click="addLine"
        >
          + 新增品項
        </button>
      </div>

      <div v-if="lines.length === 0" class="py-8 text-center text-sm text-muted-foreground">
        尚未新增品項，請點擊上方「新增品項」
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="(line, idx) in lines"
          :key="idx"
          class="grid grid-cols-[1fr_80px_120px_auto] gap-3 items-end"
        >
          <div>
            <label class="mb-1 block text-xs text-muted-foreground">SKU 代碼</label>
            <input
              v-model="line.sku_code"
              type="text"
              placeholder="輸入後按 Enter 查詢"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              @keyup.enter="onSkuCodeChange(idx)"
              @blur="onSkuCodeChange(idx)"
            />
            <p v-if="line.item_name" class="mt-0.5 text-xs text-muted-foreground">{{ line.item_name }}</p>
            <p v-else-if="line.sku_code && !line.sku_id" class="mt-0.5 text-xs text-destructive">未找到 SKU</p>
          </div>
          <div>
            <label class="mb-1 block text-xs text-muted-foreground">數量</label>
            <input
              v-model.number="line.qty"
              type="number"
              min="1"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs text-muted-foreground">批號（選填）</label>
            <input
              v-model="line.batch_number"
              type="text"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
          </div>
          <button
            class="rounded-md border px-2 py-2 text-sm text-destructive hover:bg-destructive/10 transition-colors"
            @click="removeLine(idx)"
          >
            ✕
          </button>
        </div>
      </div>
    </div>

    <!-- 提交 -->
    <div class="flex gap-3 justify-end">
      <NuxtLink
        to="/inventory/transfers"
        class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
      >
        取消
      </NuxtLink>
      <button
        :disabled="!isValid || store.saving"
        class="rounded-md bg-primary px-6 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50"
        @click="submit"
      >
        {{ store.saving ? '建立中…' : '建立調撥單' }}
      </button>
    </div>
  </div>
</template>
