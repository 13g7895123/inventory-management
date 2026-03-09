<script setup lang="ts">
import type { ReceiveLineForm, PurchaseOrderLine } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const route   = useRoute()
const router  = useRouter()
const poStore = usePurchaseOrderStore()
const id      = computed(() => Number(route.params.id))

onMounted(async () => {
  await poStore.fetchOne(id.value)
  initLines()
})

// ── 驗收行初始化 ─────────────────────────────────────────────────────
interface ReceiveRow extends ReceiveLineForm {
  _key: number
  sku_code: string
  item_name: string
  ordered_qty: number
  already_received: number
  pending_qty: number
  qtyError: string
}

const rows    = ref<ReceiveRow[]>([])
const errorMsg = ref('')

function initLines() {
  if (!poStore.current?.lines) return
  rows.value = poStore.current.lines
    .filter(l => l.ordered_qty > l.received_qty)
    .map((l: PurchaseOrderLine, i: number) => ({
      _key:             i,
      line_id:          l.id,
      sku_id:           l.sku_id,
      sku_code:         l.sku_code ?? String(l.sku_id),
      item_name:        l.item_name ?? '-',
      ordered_qty:      l.ordered_qty,
      already_received: l.received_qty,
      pending_qty:      l.ordered_qty - l.received_qty,
      received_qty:     l.ordered_qty - l.received_qty,  // 預設全數驗收
      unit_cost:        undefined,
      batch_number:     '',
      expiry_date:      '',
      notes:            '',
      qtyError:         '',
    }))
}

// ── 數量驗證 ─────────────────────────────────────────────────────────
function validateQty(row: ReceiveRow) {
  const qty = Number(row.received_qty)
  if (isNaN(qty) || qty < 0) {
    row.qtyError = '數量不得為負數'
  } else if (qty > row.pending_qty) {
    row.qtyError = `不得超過待驗收數量 ${row.pending_qty}`
  } else {
    row.qtyError = ''
  }
}

// ── 提交 ─────────────────────────────────────────────────────────────
async function handleSubmit() {
  errorMsg.value = ''

  // 驗證每行
  rows.value.forEach(r => validateQty(r))
  if (rows.value.some(r => r.qtyError)) {
    errorMsg.value = '請修正數量錯誤後再提交'
    return
  }

  const toReceive = rows.value.filter(r => Number(r.received_qty) > 0)
  if (toReceive.length === 0) {
    errorMsg.value = '請至少輸入一筆驗收數量'
    return
  }

  const lines: ReceiveLineForm[] = toReceive.map(r => ({
    line_id:      r.line_id,
    received_qty: Number(r.received_qty),
    unit_cost:    r.unit_cost !== undefined && r.unit_cost !== null && String(r.unit_cost) !== '' ? Number(r.unit_cost) : undefined,
    batch_number: r.batch_number || undefined,
    expiry_date:  r.expiry_date  || undefined,
    notes:        r.notes        || undefined,
  }))

  try {
    await poStore.receive(id.value, lines)
    router.push(`/purchase/orders/${id.value}`)
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '驗收失敗，請稍後再試'
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
        {{ poStore.current?.po_number ?? id }}
      </NuxtLink>
      <span>/</span>
      <span class="text-foreground">進貨驗收</span>
    </div>

    <!-- 標題 -->
    <div>
      <h1 class="text-2xl font-semibold">進貨驗收</h1>
      <p class="mt-1 text-sm text-muted-foreground">
        採購單：{{ poStore.current?.po_number }}
        — 供應商：{{ poStore.current?.supplier_name ?? '-' }}
      </p>
    </div>

    <!-- 載入中 -->
    <div v-if="poStore.loading" class="flex justify-center py-24">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <template v-else>
      <!-- 錯誤訊息 -->
      <p
        v-if="errorMsg"
        class="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive"
      >
        {{ errorMsg }}
      </p>

      <!-- 無待驗收明細 -->
      <div v-if="rows.length === 0" class="rounded-lg border py-16 text-center text-muted-foreground">
        目前無待驗收的明細項目
      </div>

      <!-- 驗收表格 -->
      <div v-else class="rounded-lg border overflow-hidden">
        <div class="px-6 py-4 border-b bg-muted/20">
          <h2 class="font-medium">待驗收明細</h2>
          <p class="text-xs text-muted-foreground mt-0.5">批號與效期為選填，如有批次管理需求請填寫</p>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-muted/50">
              <tr>
                <th class="px-4 py-3 text-left font-medium">SKU</th>
                <th class="px-4 py-3 text-left font-medium">品名</th>
                <th class="px-4 py-3 text-right font-medium">訂購數</th>
                <th class="px-4 py-3 text-right font-medium">待驗收</th>
                <th class="px-4 py-3 text-right font-medium w-28">本次驗收 <span class="text-destructive">*</span></th>
                <th class="px-4 py-3 text-right font-medium w-28">成本單價</th>
                <th class="px-4 py-3 text-left font-medium w-32">批號</th>
                <th class="px-4 py-3 text-left font-medium w-36">效期</th>
                <th class="px-4 py-3 text-left font-medium">備註</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr
                v-for="row in rows"
                :key="row._key"
                class="hover:bg-muted/20 transition-colors"
              >
                <!-- SKU -->
                <td class="px-4 py-3 font-mono text-xs">{{ row.sku_code }}</td>
                <!-- 品名 -->
                <td class="px-4 py-3 max-w-xs truncate">{{ row.item_name }}</td>
                <!-- 訂購數 -->
                <td class="px-4 py-3 text-right text-muted-foreground">{{ row.ordered_qty }}</td>
                <!-- 待驗收 -->
                <td class="px-4 py-3 text-right font-medium text-orange-600">{{ row.pending_qty }}</td>
                <!-- 本次驗收數量 -->
                <td class="px-4 py-3">
                  <div class="space-y-0.5">
                    <input
                      v-model.number="row.received_qty"
                      type="number"
                      :min="0"
                      :max="row.pending_qty"
                      class="w-full rounded border border-input bg-transparent px-2 py-1 text-right text-sm focus-visible:outline-none"
                      :class="{ 'border-destructive': row.qtyError }"
                      @blur="validateQty(row)"
                    />
                    <p v-if="row.qtyError" class="text-xs text-destructive">{{ row.qtyError }}</p>
                  </div>
                </td>
                <!-- 成本單價 -->
                <td class="px-4 py-3">
                  <input
                    v-model.number="row.unit_cost"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="可選"
                    class="w-full rounded border border-input bg-transparent px-2 py-1 text-right text-sm focus-visible:outline-none"
                  />
                </td>
                <!-- 批號 -->
                <td class="px-4 py-3">
                  <input
                    v-model="row.batch_number"
                    type="text"
                    placeholder="如 LOT-2024-01"
                    class="w-full rounded border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none"
                  />
                </td>
                <!-- 效期 -->
                <td class="px-4 py-3">
                  <input
                    v-model="row.expiry_date"
                    type="date"
                    class="w-full rounded border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none"
                  />
                </td>
                <!-- 備註 -->
                <td class="px-4 py-3">
                  <input
                    v-model="row.notes"
                    type="text"
                    placeholder="備註"
                    class="w-full rounded border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 操作按鈕 -->
      <div class="flex justify-end gap-3">
        <NuxtLink
          :to="`/purchase/orders/${id}`"
          class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
        >
          取消
        </NuxtLink>
        <button
          v-if="rows.length > 0"
          type="button"
          :disabled="poStore.receiving"
          class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
          @click="handleSubmit"
        >
          {{ poStore.receiving ? '驗收中…' : '確認驗收' }}
        </button>
      </div>
    </template>
  </div>
</template>
