<script setup lang="ts">
import type { ShipmentLineForm } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const route        = useRoute()
const router       = useRouter()
const soStore      = useSalesOrderStore()
const shipStore    = useShipmentStore()

const id = computed(() => Number(route.params.id))

onMounted(async () => {
  await soStore.fetchOne(id.value)
  if (soStore.current && !['draft', 'cancelled'].includes(soStore.current.status)) {
    soStore.fetchShipments(id.value)
  }
})

const so    = computed(() => soStore.current)
const lines = computed(() => soStore.currentLines)

// ── 狀態顯示 ─────────────────────────────────────────────────────────
const statusLabels: Record<string, string> = {
  draft:     '草稿',
  confirmed: '已確認',
  partial:   '部分出貨',
  shipped:   '已出貨',
  cancelled: '已取消',
}
const statusClasses: Record<string, string> = {
  draft:     'bg-gray-100 text-gray-700',
  confirmed: 'bg-blue-100 text-blue-700',
  partial:   'bg-purple-100 text-purple-700',
  shipped:   'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
}

function formatDate(dt: string | null | undefined) {
  if (!dt) return '-'
  return dt.substring(0, 10)
}

function formatMoney(val: number | string | null | undefined) {
  if (val === null || val === undefined) return '-'
  return Number(val).toLocaleString('zh-TW', { minimumFractionDigits: 0 })
}

// ── 確認訂單 ─────────────────────────────────────────────────────────
async function handleConfirm() {
  if (!confirm('確定要確認此訂單嗎？確認後無法回到草稿。')) return
  await soStore.confirm(id.value)
}

// ── 取消訂單 ─────────────────────────────────────────────────────────
async function handleCancel() {
  if (!confirm('確定要取消此訂單嗎？此操作無法復原。')) return
  await soStore.cancel(id.value)
}

// ── 出貨 Dialog ───────────────────────────────────────────────────────
interface ShipLineRow extends ShipmentLineForm {
  _key: number
}

const shipDialogOpen    = ref(false)
const shipCarrier       = ref('')
const shipTrackingNum   = ref('')
const shipNotes         = ref('')
const shipErrorMsg      = ref('')
const shipLines         = ref<ShipLineRow[]>([])

function openShipDialog() {
  shipCarrier.value      = ''
  shipTrackingNum.value  = ''
  shipNotes.value        = ''
  shipErrorMsg.value     = ''
  shipLines.value = lines.value
    .filter(l => {
      const pending = Number(l.ordered_qty) - Number(l.shipped_qty ?? 0)
      return pending > 0
    })
    .map((l, i) => ({
      _key:            i,
      sales_order_line_id: l.id,
      sku_id:          l.sku_id,
      sku_code:        l.sku_code ?? '',
      item_name:       l.item_name ?? '',
      ordered_qty:     Number(l.ordered_qty),
      shipped_qty_so_far: Number(l.shipped_qty ?? 0),
      pending_qty:     Number(l.ordered_qty) - Number(l.shipped_qty ?? 0),
      shipped_qty:     Number(l.ordered_qty) - Number(l.shipped_qty ?? 0),
      batch_number:    '',
      notes:           '',
    }))
  shipDialogOpen.value = true
}

async function handleShipSubmit() {
  shipErrorMsg.value = ''
  const activeLines = shipLines.value.filter(l => Number(l.shipped_qty) > 0)
  if (activeLines.length === 0) {
    shipErrorMsg.value = '請輸入至少一行出貨數量'; return
  }
  for (const l of activeLines) {
    if (Number(l.shipped_qty) > l.pending_qty!) {
      shipErrorMsg.value = `${l.item_name} 出貨數量不能超過待出貨數量 ${l.pending_qty}`; return
    }
  }

  try {
    const created = await shipStore.create(id.value, {
      carrier:         shipCarrier.value || null,
      tracking_number: shipTrackingNum.value || null,
      notes:           shipNotes.value || null,
      lines: activeLines.map(l => ({
        sales_order_line_id: l.sales_order_line_id,
        sku_id:              l.sku_id,
        shipped_qty:         Number(l.shipped_qty),
        batch_number:        l.batch_number || null,
        notes:               l.notes || null,
      })),
    })
    shipDialogOpen.value = false
    await soStore.fetchOne(id.value)
    await soStore.fetchShipments(id.value)
    router.push(`/sales/shipments/${created.id}`)
  } catch (e: unknown) {
    shipErrorMsg.value = e instanceof Error ? e.message : '出貨建立失敗'
  }
}
</script>

<template>
  <div v-if="soStore.loading" class="flex justify-center py-24">
    <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
  </div>

  <div v-else-if="!so" class="py-16 text-center text-muted-foreground">
    找不到此銷售訂單
  </div>

  <div v-else class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/sales/orders" class="hover:text-foreground">銷售訂單</NuxtLink>
      <span>/</span>
      <span class="text-foreground">{{ so.so_number }}</span>
    </div>

    <!-- 標題列 -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-semibold font-mono">{{ so.so_number }}</h1>
          <span
            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="statusClasses[so.status]"
          >
            {{ statusLabels[so.status] ?? so.status }}
          </span>
        </div>
        <p class="text-sm text-muted-foreground">
          客戶：{{ so.customer_name ?? `#${so.customer_id}` }} ·
          訂單日期：{{ formatDate(so.order_date) }}
        </p>
      </div>

      <!-- 操作按鈕 -->
      <div class="flex flex-wrap gap-2">
        <!-- 確認訂單 -->
        <button
          v-if="so.status === 'draft'"
          type="button"
          class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
          :disabled="soStore.confirming"
          @click="handleConfirm"
        >
          {{ soStore.confirming ? '確認中...' : '確認訂單' }}
        </button>
        <!-- 建立出貨 -->
        <button
          v-if="['confirmed','partial'].includes(so.status)"
          type="button"
          class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
          @click="openShipDialog"
        >
          建立出貨
        </button>
        <!-- 取消訂單 -->
        <button
          v-if="['draft','confirmed'].includes(so.status)"
          type="button"
          class="rounded-md border border-destructive px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/5 disabled:opacity-60"
          :disabled="soStore.cancelling"
          @click="handleCancel"
        >
          {{ soStore.cancelling ? '取消中...' : '取消訂單' }}
        </button>
        <!-- 發票 PDF -->
        <a
          :href="`/api/v1/sales-orders/${so.id}/pdf`"
          class="rounded-md border border-input px-4 py-2 text-sm hover:bg-muted/50"
          target="_blank"
          rel="noopener noreferrer"
        >
          發票 PDF
        </a>
      </div>
    </div>

    <!-- 訂單資訊卡 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="rounded-lg border p-5 space-y-3">
        <h2 class="font-medium text-sm text-muted-foreground uppercase tracking-wide">訂單資訊</h2>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between">
            <dt class="text-muted-foreground">倉庫 ID</dt>
            <dd>{{ so.warehouse_id }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted-foreground">預計出貨日</dt>
            <dd>{{ formatDate(so.expected_ship_date) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted-foreground">稅率</dt>
            <dd>{{ so.tax_rate }}%</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted-foreground">備註</dt>
            <dd class="max-w-xs text-right">{{ so.notes || '-' }}</dd>
          </div>
        </dl>
      </div>
      <div class="rounded-lg border p-5 space-y-3">
        <h2 class="font-medium text-sm text-muted-foreground uppercase tracking-wide">金額摘要</h2>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between">
            <dt class="text-muted-foreground">小計</dt>
            <dd>{{ formatMoney(so.subtotal) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted-foreground">折扣</dt>
            <dd class="text-destructive">-{{ formatMoney(so.discount_amount) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted-foreground">稅額</dt>
            <dd>{{ formatMoney(so.tax_amount) }}</dd>
          </div>
          <div class="flex justify-between border-t pt-2 font-semibold">
            <dt>總計</dt>
            <dd>{{ formatMoney(so.total_amount) }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- 明細表 -->
    <div class="rounded-lg border overflow-hidden">
      <div class="px-5 py-3 bg-muted/50 border-b">
        <h2 class="font-medium">訂單明細</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-muted/30">
          <tr>
            <th class="px-4 py-3 text-left font-medium">SKU</th>
            <th class="px-4 py-3 text-left font-medium">商品名稱</th>
            <th class="px-4 py-3 text-right font-medium">訂購數量</th>
            <th class="px-4 py-3 text-right font-medium">已出貨</th>
            <th class="px-4 py-3 text-right font-medium">單價</th>
            <th class="px-4 py-3 text-right font-medium">折扣率</th>
            <th class="px-4 py-3 text-right font-medium">行合計</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="line in lines" :key="line.id" class="hover:bg-muted/20">
            <td class="px-4 py-3 font-mono text-xs">{{ line.sku_code ?? '-' }}</td>
            <td class="px-4 py-3">{{ line.item_name ?? '-' }}</td>
            <td class="px-4 py-3 text-right">{{ line.ordered_qty }}</td>
            <td class="px-4 py-3 text-right">{{ line.shipped_qty ?? 0 }}</td>
            <td class="px-4 py-3 text-right">{{ formatMoney(line.unit_price) }}</td>
            <td class="px-4 py-3 text-right">{{ line.discount_rate ?? 0 }}%</td>
            <td class="px-4 py-3 text-right font-medium">{{ formatMoney(line.line_total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 出貨單列表 -->
    <div v-if="soStore.shipments.length > 0" class="rounded-lg border overflow-hidden">
      <div class="px-5 py-3 bg-muted/50 border-b">
        <h2 class="font-medium">相關出貨單</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-muted/30">
          <tr>
            <th class="px-4 py-3 text-left font-medium">出貨單號</th>
            <th class="px-4 py-3 text-center font-medium">狀態</th>
            <th class="px-4 py-3 text-left font-medium">物流商</th>
            <th class="px-4 py-3 text-left font-medium">追蹤號</th>
            <th class="px-4 py-3 text-center font-medium">出貨時間</th>
            <th class="px-4 py-3 text-center font-medium">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="s in soStore.shipments" :key="s.id" class="hover:bg-muted/20">
            <td class="px-4 py-3 font-mono text-xs font-medium">{{ s.shipment_number }}</td>
            <td class="px-4 py-3 text-center">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="s.status === 'shipped' ? 'bg-green-100 text-green-700' : s.status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'"
              >
                {{ s.status === 'shipped' ? '已出貨' : s.status === 'cancelled' ? '已取消' : '待出貨' }}
              </span>
            </td>
            <td class="px-4 py-3">{{ s.carrier || '-' }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ s.tracking_number || '-' }}</td>
            <td class="px-4 py-3 text-center text-muted-foreground">{{ formatDate(s.shipped_at) }}</td>
            <td class="px-4 py-3 text-center">
              <NuxtLink :to="`/sales/shipments/${s.id}`" class="text-sm text-blue-600 hover:underline">
                查看
              </NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 出貨 Dialog -->
  <Teleport to="body">
    <div
      v-if="shipDialogOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="shipDialogOpen = false"
    >
      <div class="w-full max-w-2xl rounded-lg bg-background p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold">建立出貨</h3>

        <!-- 錯誤 -->
        <p v-if="shipErrorMsg" class="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive">
          {{ shipErrorMsg }}
        </p>

        <!-- 物流資訊 -->
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-1">
            <label class="text-sm font-medium">物流商</label>
            <input
              v-model="shipCarrier"
              type="text"
              placeholder="例如：黑貓宅急便"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-sm font-medium">追蹤號碼</label>
            <input
              v-model="shipTrackingNum"
              type="text"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="col-span-2 space-y-1">
            <label class="text-sm font-medium">備註</label>
            <input
              v-model="shipNotes"
              type="text"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
        </div>

        <!-- 出貨明細 -->
        <div class="rounded-md border overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-muted/50">
              <tr>
                <th class="px-3 py-2 text-left font-medium">商品</th>
                <th class="px-3 py-2 text-right font-medium">待出貨</th>
                <th class="px-3 py-2 text-right font-medium">本次出貨</th>
                <th class="px-3 py-2 text-left font-medium">批號</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="sl in shipLines" :key="sl._key">
                <td class="px-3 py-2">
                  <div class="font-mono text-xs text-muted-foreground">{{ sl.sku_code }}</div>
                  <div>{{ sl.item_name }}</div>
                </td>
                <td class="px-3 py-2 text-right">{{ sl.pending_qty }}</td>
                <td class="px-3 py-2 text-right w-24">
                  <input
                    v-model.number="sl.shipped_qty"
                    type="number"
                    min="0"
                    :max="sl.pending_qty"
                    class="w-full rounded border border-input bg-transparent px-2 py-1 text-sm text-right focus-visible:outline-none"
                  />
                </td>
                <td class="px-3 py-2 w-32">
                  <input
                    v-model="sl.batch_number"
                    type="text"
                    placeholder="選填"
                    class="w-full rounded border border-input bg-transparent px-2 py-1 text-sm focus-visible:outline-none"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-input px-4 py-2 text-sm hover:bg-muted/50"
            @click="shipDialogOpen = false"
          >
            取消
          </button>
          <button
            type="button"
            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60"
            :disabled="shipStore.saving"
            @click="handleShipSubmit"
          >
            {{ shipStore.saving ? '建立中...' : '確認出貨' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
