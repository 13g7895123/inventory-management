<script setup lang="ts">
import type { ShipmentLineForm, SalesPaymentForm, SalesReturnLineForm } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const route        = useRoute()
const router       = useRouter()
const soStore      = useSalesOrderStore()
const shipStore    = useShipmentStore()
const paymentStore = useSalesPaymentStore()
const returnStore  = useSalesReturnStore()

const id = computed(() => Number(route.params.id))

onMounted(async () => {
  await soStore.fetchOne(id.value)
  if (soStore.current && !['draft', 'cancelled'].includes(soStore.current.status)) {
    await Promise.all([
      soStore.fetchShipments(id.value),
      paymentStore.fetchList(id.value),
      returnStore.fetchByOrder(id.value),
    ])
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

// ── 收款 Dialog ──────────────────────────────────────────────────────
const payDialogOpen  = ref(false)
const payAmount      = ref('')
const payDate        = ref(new Date().toISOString().substring(0, 10))
const payMethod      = ref<SalesPaymentForm['payment_method']>('bank_transfer')
const payRef         = ref('')
const payNotes       = ref('')
const payErrorMsg    = ref('')

const paymentMethodLabels: Record<string, string> = {
  bank_transfer: '銀行轉帳',
  cash:          '現金',
  check:         '支票',
  credit_card:   '信用卡',
  other:         '其他',
}

const paymentStatusLabels: Record<string, string> = {
  unpaid:  '未收款',
  partial: '部分收款',
  paid:    '已收款',
}
const paymentStatusClasses: Record<string, string> = {
  unpaid:  'bg-red-100 text-red-700',
  partial: 'bg-yellow-100 text-yellow-700',
  paid:    'bg-green-100 text-green-700',
}

const remainingAmount = computed(() => {
  if (!so.value) return 0
  return Number(so.value.total_amount) - Number(so.value.paid_amount ?? 0)
})

function openPayDialog() {
  payAmount.value   = ''
  payDate.value     = new Date().toISOString().substring(0, 10)
  payMethod.value   = 'bank_transfer'
  payRef.value      = ''
  payNotes.value    = ''
  payErrorMsg.value = ''
  payDialogOpen.value = true
}

async function handlePaySubmit() {
  payErrorMsg.value = ''
  const amount = Number(payAmount.value)
  if (!amount || amount <= 0) {
    payErrorMsg.value = '請輸入有效的收款金額'; return
  }
  if (!payDate.value) {
    payErrorMsg.value = '請選擇收款日期'; return
  }
  try {
    await paymentStore.create(id.value, {
      amount:         amount,
      payment_date:   payDate.value,
      payment_method: payMethod.value,
      reference_no:   payRef.value || undefined,
      notes:          payNotes.value || undefined,
    })
    payDialogOpen.value = false
    await soStore.fetchOne(id.value)
  } catch (e: unknown) {
    payErrorMsg.value = e instanceof Error ? e.message : '收款記錄建立失敗'
  }
}

// ── 退貨 Dialog ──────────────────────────────────────────────────────
interface ReturnLineRow extends SalesReturnLineForm {
  _key: number
  max_qty: number
}

const retDialogOpen  = ref(false)
const retWarehouseId = ref('')
const retReason      = ref('')
const retNotes       = ref('')
const retErrorMsg    = ref('')
const retLines       = ref<ReturnLineRow[]>([])

function openReturnDialog() {
  retWarehouseId.value = so.value?.warehouse_id?.toString() ?? ''
  retReason.value      = ''
  retNotes.value       = ''
  retErrorMsg.value    = ''
  retLines.value = lines.value
    .filter(l => Number(l.shipped_qty ?? 0) > 0)
    .map((l, i) => ({
      _key:                i,
      sales_order_line_id: l.id,
      sku_id:              l.sku_id,
      sku_code:            l.sku_code ?? '',
      item_name:           l.item_name ?? '',
      return_qty:          0,
      unit_price:          Number(l.unit_price),
      return_reason:       '',
      max_qty:             Number(l.shipped_qty ?? 0),
      shipped_qty:         Number(l.shipped_qty ?? 0),
    }))
  retDialogOpen.value = true
}

async function handleReturnSubmit() {
  retErrorMsg.value = ''
  const activeLines = retLines.value.filter(l => Number(l.return_qty) > 0)
  if (activeLines.length === 0) {
    retErrorMsg.value = '請輸入至少一行退貨數量'; return
  }
  if (!retWarehouseId.value) {
    retErrorMsg.value = '請選擇入庫倉庫'; return
  }
  for (const l of activeLines) {
    if (Number(l.return_qty) > l.max_qty) {
      retErrorMsg.value = `${l.item_name} 退貨數量不能超過已出貨數量 ${l.max_qty}`; return
    }
  }
  try {
    const created = await returnStore.create(id.value, {
      warehouse_id: Number(retWarehouseId.value),
      reason:       retReason.value || null,
      notes:        retNotes.value || null,
      lines: activeLines.map(l => ({
        sales_order_line_id: l.sales_order_line_id,
        sku_id:              l.sku_id,
        return_qty:          Number(l.return_qty),
        unit_price:          l.unit_price,
        return_reason:       l.return_reason || null,
      })),
    })
    retDialogOpen.value = false
    router.push(`/sales/returns/${created.id}`)
  } catch (e: unknown) {
    retErrorMsg.value = e instanceof Error ? e.message : '退貨單建立失敗'
  }
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
        <!-- 新增收款 -->
        <button
          v-if="!['draft','cancelled'].includes(so.status) && so.payment_status !== 'paid'"
          type="button"
          class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
          @click="openPayDialog"
        >
          新增收款
        </button>
        <!-- 建立退貨 -->
        <button
          v-if="['confirmed','partial','shipped'].includes(so.status)"
          type="button"
          class="rounded-md border border-orange-500 px-4 py-2 text-sm font-medium text-orange-600 hover:bg-orange-50"
          @click="openReturnDialog"
        >
          建立退貨
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
          <div class="flex justify-between">
            <dt class="text-muted-foreground">已收款</dt>
            <dd class="text-green-700">{{ formatMoney(so.paid_amount ?? 0) }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted-foreground">未收款</dt>
            <dd :class="remainingAmount > 0 ? 'text-destructive' : 'text-muted-foreground'">
              {{ formatMoney(remainingAmount) }}
            </dd>
          </div>
          <div class="flex justify-between items-center">
            <dt class="text-muted-foreground">收款狀態</dt>
            <dd>
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="paymentStatusClasses[so.payment_status ?? 'unpaid']"
              >
                {{ paymentStatusLabels[so.payment_status ?? 'unpaid'] }}
              </span>
            </dd>
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

    <!-- 收款記錄 -->
    <div v-if="!['draft','cancelled'].includes(so.status)" class="rounded-lg border overflow-hidden">
      <div class="px-5 py-3 bg-muted/50 border-b flex items-center justify-between">
        <h2 class="font-medium">收款記錄</h2>
        <button
          v-if="so.payment_status !== 'paid'"
          type="button"
          class="text-sm text-indigo-600 hover:underline"
          @click="openPayDialog"
        >
          + 新增收款
        </button>
      </div>
      <div v-if="paymentStore.loading" class="px-5 py-4 text-sm text-muted-foreground">
        載入中...
      </div>
      <div v-else-if="paymentStore.payments.length === 0" class="px-5 py-4 text-sm text-muted-foreground">
        尚無收款記錄
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-muted/30">
          <tr>
            <th class="px-4 py-3 text-left font-medium">收款日期</th>
            <th class="px-4 py-3 text-left font-medium">收款方式</th>
            <th class="px-4 py-3 text-right font-medium">金額</th>
            <th class="px-4 py-3 text-left font-medium">參考號碼</th>
            <th class="px-4 py-3 text-left font-medium">備註</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="p in paymentStore.payments" :key="p.id" class="hover:bg-muted/20">
            <td class="px-4 py-3">{{ formatDate(p.payment_date) }}</td>
            <td class="px-4 py-3">{{ paymentMethodLabels[p.payment_method] ?? p.payment_method }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ formatMoney(p.amount) }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ p.reference_no || '-' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ p.notes || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 退貨單列表 -->
    <div v-if="!['draft','cancelled'].includes(so.status)" class="rounded-lg border overflow-hidden">
      <div class="px-5 py-3 bg-muted/50 border-b flex items-center justify-between">
        <h2 class="font-medium">退貨單</h2>
        <button
          v-if="['confirmed','partial','shipped'].includes(so.status)"
          type="button"
          class="text-sm text-orange-600 hover:underline"
          @click="openReturnDialog"
        >
          + 建立退貨
        </button>
      </div>
      <div v-if="returnStore.loading" class="px-5 py-4 text-sm text-muted-foreground">
        載入中...
      </div>
      <div v-else-if="returnStore.returns.length === 0" class="px-5 py-4 text-sm text-muted-foreground">
        尚無退貨單
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-muted/30">
          <tr>
            <th class="px-4 py-3 text-left font-medium">退貨單號</th>
            <th class="px-4 py-3 text-center font-medium">狀態</th>
            <th class="px-4 py-3 text-right font-medium">退款金額</th>
            <th class="px-4 py-3 text-left font-medium">原因</th>
            <th class="px-4 py-3 text-center font-medium">建立日期</th>
            <th class="px-4 py-3 text-center font-medium">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="r in returnStore.returns" :key="r.id" class="hover:bg-muted/20">
            <td class="px-4 py-3 font-mono text-xs font-medium">{{ r.return_number }}</td>
            <td class="px-4 py-3 text-center">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="r.status === 'confirmed' ? 'bg-green-100 text-green-700' : r.status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700'"
              >
                {{ r.status === 'confirmed' ? '已確認' : r.status === 'cancelled' ? '已取消' : '草稿' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">{{ formatMoney(r.refund_amount) }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ r.reason || '-' }}</td>
            <td class="px-4 py-3 text-center text-muted-foreground">{{ formatDate(r.created_at) }}</td>
            <td class="px-4 py-3 text-center">
              <NuxtLink :to="`/sales/returns/${r.id}`" class="text-sm text-blue-600 hover:underline">
                查看
              </NuxtLink>
            </td>
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

  <!-- 收款 Dialog -->
  <Teleport to="body">
    <div
      v-if="payDialogOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="payDialogOpen = false"
    >
      <div class="w-full max-w-md rounded-lg bg-background p-6 shadow-xl space-y-4">
        <h3 class="text-lg font-semibold">新增收款記錄</h3>

        <p v-if="payErrorMsg" class="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive">
          {{ payErrorMsg }}
        </p>

        <div class="rounded-md bg-muted/40 px-3 py-2 text-sm">
          未收款金額：<span class="font-semibold text-destructive">{{ formatMoney(remainingAmount) }}</span>
        </div>

        <div class="space-y-4">
          <div class="space-y-1">
            <label class="text-sm font-medium">收款金額 <span class="text-destructive">*</span></label>
            <input
              v-model="payAmount"
              type="number"
              min="0.01"
              step="0.01"
              :max="remainingAmount"
              placeholder="0.00"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-sm font-medium">收款日期 <span class="text-destructive">*</span></label>
            <input
              v-model="payDate"
              type="date"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-sm font-medium">收款方式 <span class="text-destructive">*</span></label>
            <select
              v-model="payMethod"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            >
              <option v-for="(label, val) in paymentMethodLabels" :key="val" :value="val">{{ label }}</option>
            </select>
          </div>
          <div class="space-y-1">
            <label class="text-sm font-medium">參考號碼</label>
            <input
              v-model="payRef"
              type="text"
              placeholder="匯款帳號、支票號等"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-sm font-medium">備註</label>
            <input
              v-model="payNotes"
              type="text"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <button
            type="button"
            class="rounded-md border border-input px-4 py-2 text-sm hover:bg-muted/50"
            @click="payDialogOpen = false"
          >
            取消
          </button>
          <button
            type="button"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
            :disabled="paymentStore.saving"
            @click="handlePaySubmit"
          >
            {{ paymentStore.saving ? '儲存中...' : '確認收款' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- 退貨 Dialog -->
  <Teleport to="body">
    <div
      v-if="retDialogOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="retDialogOpen = false"
    >
      <div class="w-full max-w-2xl rounded-lg bg-background p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold">建立退貨單</h3>

        <p v-if="retErrorMsg" class="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive">
          {{ retErrorMsg }}
        </p>

        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2 space-y-1">
            <label class="text-sm font-medium">入庫倉庫 ID <span class="text-destructive">*</span></label>
            <input
              v-model="retWarehouseId"
              type="number"
              min="1"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-sm font-medium">退貨原因</label>
            <input
              v-model="retReason"
              type="text"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
          <div class="space-y-1">
            <label class="text-sm font-medium">備註</label>
            <input
              v-model="retNotes"
              type="text"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
            />
          </div>
        </div>

        <div class="rounded-md border overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-muted/50">
              <tr>
                <th class="px-3 py-2 text-left font-medium">商品</th>
                <th class="px-3 py-2 text-right font-medium">已出貨</th>
                <th class="px-3 py-2 text-right font-medium">退貨數量</th>
                <th class="px-3 py-2 text-left font-medium">退貨原因</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="rl in retLines" :key="rl._key">
                <td class="px-3 py-2">
                  <div class="font-mono text-xs text-muted-foreground">{{ rl.sku_code }}</div>
                  <div>{{ rl.item_name }}</div>
                </td>
                <td class="px-3 py-2 text-right">{{ rl.max_qty }}</td>
                <td class="px-3 py-2 text-right w-24">
                  <input
                    v-model.number="rl.return_qty"
                    type="number"
                    min="0"
                    :max="rl.max_qty"
                    class="w-full rounded border border-input bg-transparent px-2 py-1 text-sm text-right focus-visible:outline-none"
                  />
                </td>
                <td class="px-3 py-2 w-40">
                  <input
                    v-model="rl.return_reason"
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
            @click="retDialogOpen = false"
          >
            取消
          </button>
          <button
            type="button"
            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 disabled:opacity-60"
            :disabled="returnStore.saving"
            @click="handleReturnSubmit"
          >
            {{ returnStore.saving ? '建立中...' : '建立退貨單' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

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
