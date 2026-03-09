<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route     = useRoute()
const shipStore = useShipmentStore()

const id = computed(() => Number(route.params.id))

onMounted(() => shipStore.fetchOne(id.value))

const ship  = computed(() => shipStore.current)
const lines = computed(() => shipStore.currentLines)

const statusLabels: Record<string, string> = {
  pending:   '待出貨',
  shipped:   '已出貨',
  cancelled: '已取消',
}
const statusClasses: Record<string, string> = {
  pending:   'bg-yellow-100 text-yellow-700',
  shipped:   'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
}

function formatDate(dt: string | null | undefined) {
  if (!dt) return '-'
  return dt.substring(0, 10)
}
</script>

<template>
  <div v-if="shipStore.loading" class="flex justify-center py-24">
    <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
  </div>

  <div v-else-if="!ship" class="py-16 text-center text-muted-foreground">
    找不到此出貨單
  </div>

  <div v-else class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink :to="`/sales/orders/${ship.sales_order_id}`" class="hover:text-foreground">
        銷售訂單 {{ ship.so_number ?? `#${ship.sales_order_id}` }}
      </NuxtLink>
      <span>/</span>
      <span class="text-foreground">{{ ship.shipment_number }}</span>
    </div>

    <!-- 標題列 -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-semibold font-mono">{{ ship.shipment_number }}</h1>
          <span
            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            :class="statusClasses[ship.status]"
          >
            {{ statusLabels[ship.status] ?? ship.status }}
          </span>
        </div>
        <p class="text-sm text-muted-foreground">
          客戶：{{ ship.customer_name ?? '-' }} ·
          出貨日期：{{ formatDate(ship.shipped_at) }}
        </p>
      </div>

      <div class="flex gap-2">
        <NuxtLink
          :to="`/sales/shipments/${id}/packing-slip`"
          class="rounded-md border border-input px-4 py-2 text-sm hover:bg-muted/50"
          target="_blank"
        >
          包裝單列印
        </NuxtLink>
      </div>
    </div>

    <!-- 出貨資訊卡 -->
    <div class="rounded-lg border p-5 space-y-3">
      <h2 class="font-medium text-sm text-muted-foreground uppercase tracking-wide">出貨資訊</h2>
      <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div class="space-y-0.5">
          <dt class="text-muted-foreground">物流商</dt>
          <dd class="font-medium">{{ ship.carrier || '-' }}</dd>
        </div>
        <div class="space-y-0.5">
          <dt class="text-muted-foreground">追蹤號碼</dt>
          <dd class="font-mono text-xs font-medium">{{ ship.tracking_number || '-' }}</dd>
        </div>
        <div class="space-y-0.5">
          <dt class="text-muted-foreground">倉庫 ID</dt>
          <dd class="font-medium">{{ ship.warehouse_id }}</dd>
        </div>
        <div class="space-y-0.5">
          <dt class="text-muted-foreground">建立人</dt>
          <dd class="font-medium">{{ ship.created_by }}</dd>
        </div>
        <div class="col-span-2 sm:col-span-4 space-y-0.5">
          <dt class="text-muted-foreground">備註</dt>
          <dd>{{ ship.notes || '-' }}</dd>
        </div>
      </dl>
    </div>

    <!-- 明細表 -->
    <div class="rounded-lg border overflow-hidden">
      <div class="px-5 py-3 bg-muted/50 border-b">
        <h2 class="font-medium">出貨明細</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-muted/30">
          <tr>
            <th class="px-4 py-3 text-left font-medium">SKU</th>
            <th class="px-4 py-3 text-left font-medium">商品名稱</th>
            <th class="px-4 py-3 text-right font-medium">出貨數量</th>
            <th class="px-4 py-3 text-left font-medium">批號</th>
            <th class="px-4 py-3 text-left font-medium">備註</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="line in lines" :key="line.id" class="hover:bg-muted/20">
            <td class="px-4 py-3 font-mono text-xs">{{ line.sku_code ?? '-' }}</td>
            <td class="px-4 py-3">{{ line.item_name ?? '-' }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ line.shipped_qty }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ line.batch_number || '-' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ line.notes || '-' }}</td>
          </tr>
          <tr v-if="lines.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">無明細資料</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
