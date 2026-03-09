<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route       = useRoute()
const returnStore = useSalesReturnStore()

const id = computed(() => Number(route.params.id))

onMounted(() => returnStore.fetchOne(id.value))

const ret   = computed(() => returnStore.current)
const lines = computed(() => returnStore.currentLines)

const statusLabels: Record<string, string> = {
  draft:     '草稿',
  confirmed: '已確認',
  cancelled: '已取消',
}
const statusClasses: Record<string, string> = {
  draft:     'bg-gray-100 text-gray-700',
  confirmed: 'bg-green-100 text-green-700',
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

async function handleConfirm() {
  if (!confirm('確定要確認此退貨單嗎？確認後將回補庫存。')) return
  await returnStore.confirm(id.value)
}

async function handleCancel() {
  if (!confirm('確定要取消此退貨單嗎？')) return
  await returnStore.cancel(id.value)
}
</script>

<template>
  <div v-if="returnStore.loading" class="flex justify-center py-24">
    <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
  </div>

  <div v-else-if="!ret" class="py-16 text-center text-muted-foreground">
    找不到此退貨單
  </div>

  <div v-else class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink :to="`/sales/orders/${ret.sales_order_id}`" class="hover:text-foreground">
        銷售訂單 {{ ret.so_number ?? `#${ret.sales_order_id}` }}
      </NuxtLink>
      <span>/</span>
      <span class="text-foreground">{{ ret.return_number }}</span>
    </div>

    <!-- 標題列 -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-semibold font-mono">{{ ret.return_number }}</h1>
          <span
            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="statusClasses[ret.status]"
          >
            {{ statusLabels[ret.status] ?? ret.status }}
          </span>
        </div>
        <p class="text-sm text-muted-foreground">
          客戶：{{ ret.customer_name ?? `#${ret.sales_order_id}` }} ·
          建立日期：{{ formatDate(ret.created_at) }}
        </p>
      </div>

      <div class="flex gap-2">
        <button
          v-if="ret.status === 'draft'"
          type="button"
          class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-60"
          :disabled="returnStore.confirming"
          @click="handleConfirm"
        >
          {{ returnStore.confirming ? '確認中...' : '確認退貨' }}
        </button>
        <button
          v-if="ret.status === 'draft'"
          type="button"
          class="rounded-md border border-destructive px-4 py-2 text-sm font-medium text-destructive hover:bg-destructive/5 disabled:opacity-60"
          :disabled="returnStore.cancelling"
          @click="handleCancel"
        >
          {{ returnStore.cancelling ? '取消中...' : '取消退貨單' }}
        </button>
      </div>
    </div>

    <!-- 退貨資訊卡 -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="rounded-lg border p-5 space-y-3">
        <h2 class="font-medium text-sm text-muted-foreground uppercase tracking-wide">退貨資訊</h2>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between">
            <dt class="text-muted-foreground">倉庫 ID</dt>
            <dd>{{ ret.warehouse_id }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted-foreground">退貨原因</dt>
            <dd>{{ ret.reason || '-' }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-muted-foreground">備註</dt>
            <dd>{{ ret.notes || '-' }}</dd>
          </div>
          <div v-if="ret.confirmed_at" class="flex justify-between">
            <dt class="text-muted-foreground">確認時間</dt>
            <dd>{{ formatDate(ret.confirmed_at) }}</dd>
          </div>
        </dl>
      </div>
      <div class="rounded-lg border p-5 space-y-3">
        <h2 class="font-medium text-sm text-muted-foreground uppercase tracking-wide">退款資訊</h2>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between border-t pt-2 font-semibold">
            <dt>退款金額</dt>
            <dd>{{ formatMoney(ret.refund_amount) }}</dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- 退貨明細 -->
    <div class="rounded-lg border overflow-hidden">
      <div class="px-5 py-3 bg-muted/50 border-b">
        <h2 class="font-medium">退貨明細</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-muted/30">
          <tr>
            <th class="px-4 py-3 text-left font-medium">SKU</th>
            <th class="px-4 py-3 text-left font-medium">商品名稱</th>
            <th class="px-4 py-3 text-right font-medium">退貨數量</th>
            <th class="px-4 py-3 text-right font-medium">單價</th>
            <th class="px-4 py-3 text-right font-medium">行合計</th>
            <th class="px-4 py-3 text-left font-medium">退貨原因</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="line in lines" :key="line.id" class="hover:bg-muted/20">
            <td class="px-4 py-3 font-mono text-xs">{{ line.sku_code ?? '-' }}</td>
            <td class="px-4 py-3">{{ line.item_name ?? '-' }}</td>
            <td class="px-4 py-3 text-right">{{ line.return_qty }}</td>
            <td class="px-4 py-3 text-right">{{ formatMoney(line.unit_price) }}</td>
            <td class="px-4 py-3 text-right font-medium">
              {{ formatMoney(Number(line.return_qty) * Number(line.unit_price)) }}
            </td>
            <td class="px-4 py-3 text-muted-foreground">{{ line.return_reason || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
