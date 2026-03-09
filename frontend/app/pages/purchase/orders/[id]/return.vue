<script setup lang="ts">
import type { PurchaseOrderLine } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const route   = useRoute()
const router  = useRouter()
const poStore = usePurchaseOrderStore()
const id      = computed(() => Number(route.params.id))

onMounted(async () => {
  await poStore.fetchOne(id.value)
  initRows()
})

// ── 退貨行 ────────────────────────────────────────────────────────────
interface ReturnRow {
  line_id:         number
  sku_id:          number
  sku_code:        string
  item_name:       string
  received_qty:    number
  return_qty:      number
  unit_cost:       number | ''
  return_reason:   string
  batch_number:    string
  notes:           string
  qtyError:        string
}

const rows          = ref<ReturnRow[]>([])
const globalReason  = ref('')
const globalNotes   = ref('')
const errorMsg      = ref('')

function initRows() {
  if (!poStore.current?.lines) return
  rows.value = poStore.current.lines
    .filter((l: PurchaseOrderLine) => l.received_qty > 0)
    .map((l: PurchaseOrderLine) => ({
      line_id:       l.id,
      sku_id:        l.sku_id,
      sku_code:      l.sku_code ?? String(l.sku_id),
      item_name:     l.item_name ?? '-',
      received_qty:  l.received_qty,
      return_qty:    0,
      unit_cost:     '',
      return_reason: '',
      batch_number:  '',
      notes:         '',
      qtyError:      '',
    }))
}

function validateQty(row: ReturnRow) {
  const qty = Number(row.return_qty)
  if (isNaN(qty) || qty < 0) {
    row.qtyError = '數量不得為負數'
  } else if (qty > row.received_qty) {
    row.qtyError = `不得超過已驗收數量 ${row.received_qty}`
  } else {
    row.qtyError = ''
  }
}

const hasError = computed(() => rows.value.some(r => r.qtyError))

const activeRows = computed(() =>
  rows.value.filter(r => Number(r.return_qty) > 0)
)

// ── 提交 ─────────────────────────────────────────────────────────────
async function doSubmit() {
  errorMsg.value = ''

  // 驗證所有行
  rows.value.forEach(validateQty)
  if (hasError.value) {
    errorMsg.value = '請修正數量錯誤'
    return
  }

  if (activeRows.value.length === 0) {
    errorMsg.value = '請至少輸入一項退貨數量'
    return
  }

  const lines = activeRows.value.map(r => ({
    purchase_order_line_id: r.line_id,
    sku_id:                 r.sku_id,
    return_qty:             Number(r.return_qty),
    unit_cost:              r.unit_cost !== '' ? Number(r.unit_cost) : undefined,
    return_reason:          r.return_reason || undefined,
    batch_number:           r.batch_number || undefined,
    notes:                  r.notes || undefined,
  }))

  try {
    await poStore.createReturn(id.value, {
      reason: globalReason.value || undefined,
      notes:  globalNotes.value  || undefined,
      lines,
    })
    router.push(`/purchase/orders/${id.value}`)
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '建立退貨單失敗'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/purchase/orders" class="hover:text-foreground">採購單</NuxtLink>
      <span>/</span>
      <NuxtLink :to="`/purchase/orders/${id}`" class="hover:text-foreground">
        {{ poStore.current?.po_number ?? '...' }}
      </NuxtLink>
      <span>/</span>
      <span class="text-foreground">申請退貨</span>
    </div>

    <!-- 載入中 -->
    <div v-if="poStore.loading" class="flex justify-center py-24">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <template v-else-if="poStore.current">
      <!-- 標題 -->
      <div>
        <h1 class="text-2xl font-semibold">採購退貨申請</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          採購單：{{ poStore.current.po_number }}　供應商：{{ poStore.current.supplier_name ?? '-' }}
        </p>
      </div>

      <!-- 退貨原因 -->
      <div class="rounded-lg border p-5 space-y-3">
        <h2 class="font-medium text-sm">退貨說明</h2>
        <div>
          <label class="text-sm font-medium">退貨原因</label>
          <input
            v-model="globalReason"
            type="text"
            class="mt-1 w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            placeholder="如：品質問題、數量錯誤…"
          />
        </div>
        <div>
          <label class="text-sm font-medium">備註</label>
          <textarea
            v-model="globalNotes"
            rows="2"
            class="mt-1 w-full rounded-md border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary resize-none"
          />
        </div>
      </div>

      <!-- 退貨明細 -->
      <div class="rounded-lg border overflow-hidden">
        <div class="px-6 py-4 border-b bg-muted/20">
          <h2 class="font-medium">退貨明細</h2>
          <p class="text-xs text-muted-foreground mt-0.5">僅顯示已驗收的商品，輸入退貨數量（0 表示不退）</p>
        </div>

        <div v-if="rows.length === 0" class="px-6 py-10 text-center text-sm text-muted-foreground">
          此採購單無已驗收的商品
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-muted/50">
              <tr>
                <th class="px-4 py-3 text-left font-medium">SKU</th>
                <th class="px-4 py-3 text-left font-medium">品名</th>
                <th class="px-4 py-3 text-right font-medium">已驗收</th>
                <th class="px-4 py-3 text-right font-medium w-28">退貨數量</th>
                <th class="px-4 py-3 text-right font-medium w-28">退貨單價</th>
                <th class="px-4 py-3 text-left font-medium">退貨原因</th>
                <th class="px-4 py-3 text-left font-medium w-28">批號</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr
                v-for="row in rows"
                :key="row.line_id"
                class="hover:bg-muted/30 transition-colors align-top"
              >
                <td class="px-4 py-3 font-mono text-xs">{{ row.sku_code }}</td>
                <td class="px-4 py-3">{{ row.item_name }}</td>
                <td class="px-4 py-3 text-right">{{ row.received_qty }}</td>
                <td class="px-4 py-3">
                  <input
                    v-model.number="row.return_qty"
                    type="number"
                    min="0"
                    :max="row.received_qty"
                    class="w-full rounded border px-2 py-1 text-right text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    :class="row.qtyError ? 'border-destructive' : ''"
                    @input="validateQty(row)"
                  />
                  <p v-if="row.qtyError" class="text-xs text-destructive mt-0.5">{{ row.qtyError }}</p>
                </td>
                <td class="px-4 py-3">
                  <input
                    v-model="row.unit_cost"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full rounded border px-2 py-1 text-right text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    placeholder="選填"
                  />
                </td>
                <td class="px-4 py-3">
                  <input
                    v-model="row.return_reason"
                    type="text"
                    class="w-full rounded border px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                    placeholder="選填"
                  />
                </td>
                <td class="px-4 py-3">
                  <input
                    v-model="row.batch_number"
                    type="text"
                    class="w-full rounded border px-2 py-1 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-primary"
                    placeholder="選填"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 錯誤訊息 -->
      <p
        v-if="errorMsg"
        class="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive"
      >
        {{ errorMsg }}
      </p>

      <!-- 操作按鈕 -->
      <div class="flex justify-end gap-3">
        <NuxtLink
          :to="`/purchase/orders/${id}`"
          class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
        >
          取消
        </NuxtLink>
        <button
          :disabled="poStore.returnSaving || rows.length === 0"
          class="rounded-md bg-orange-500 px-5 py-2 text-sm font-medium text-white hover:bg-orange-600 disabled:opacity-60 transition-colors"
          @click="doSubmit"
        >
          {{ poStore.returnSaving ? '建立中…' : '建立退貨單' }}
        </button>
      </div>
    </template>

    <div v-else class="text-center py-24 text-muted-foreground">
      找不到採購單
    </div>
  </div>
</template>
