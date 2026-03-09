<script setup lang="ts">
definePageMeta({ layout: false })

const route     = useRoute()
const shipStore = useShipmentStore()

const id = computed(() => Number(route.params.id))

const printTime = ref('')

onMounted(() => {
  shipStore.fetchOne(id.value)
  printTime.value = new Date().toLocaleString('zh-TW')
})

const ship  = computed(() => shipStore.current)
const lines = computed(() => shipStore.currentLines)

function formatDate(dt: string | null | undefined) {
  if (!dt) return '-'
  return dt.substring(0, 10)
}

function printPage() {
  window.print()
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 print:bg-white">
    <!-- 列印控制列（列印時隱藏） -->
    <div class="print:hidden flex items-center justify-between px-6 py-3 bg-white border-b shadow-sm">
      <NuxtLink
        v-if="ship"
        :to="`/sales/shipments/${id}`"
        class="text-sm text-muted-foreground hover:text-foreground"
      >
        ← 返回出貨單
      </NuxtLink>
      <span v-else class="text-sm text-muted-foreground">載入中...</span>
      <button
        type="button"
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
        @click="printPage"
      >
        🖨 列印
      </button>
    </div>

    <!-- 載入中 -->
    <div v-if="shipStore.loading" class="flex justify-center py-24 print:hidden">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <!-- 找不到 -->
    <div v-else-if="!ship" class="py-16 text-center text-muted-foreground print:hidden">
      找不到此出貨單
    </div>

    <!-- 包裝單主體 -->
    <div
      v-else
      class="max-w-3xl mx-auto my-8 bg-white shadow print:shadow-none print:my-0 print:max-w-full"
    >
      <div class="p-10 space-y-8">
        <!-- 頁首：公司資訊 + 標題 -->
        <div class="flex items-start justify-between border-b pb-6">
          <div>
            <h1 class="text-2xl font-bold">進銷存管理系統</h1>
            <p class="text-sm text-gray-500 mt-1">倉庫 ID：{{ ship.warehouse_id }}</p>
          </div>
          <div class="text-right">
            <h2 class="text-xl font-semibold text-gray-700">包 裝 單</h2>
            <p class="text-sm text-gray-500 mt-1 font-mono">{{ ship.shipment_number }}</p>
          </div>
        </div>

        <!-- 出貨資訊 -->
        <div class="grid grid-cols-2 gap-6 text-sm">
          <div class="space-y-2">
            <div class="font-semibold text-gray-700 border-b pb-1">收件資訊</div>
            <div>
              <span class="text-gray-500">客戶：</span>
              <span class="font-medium">{{ ship.customer_name ?? '-' }}</span>
            </div>
            <div>
              <span class="text-gray-500">物流商：</span>
              <span>{{ ship.carrier || '-' }}</span>
            </div>
            <div>
              <span class="text-gray-500">追蹤號碼：</span>
              <span class="font-mono text-xs">{{ ship.tracking_number || '-' }}</span>
            </div>
          </div>
          <div class="space-y-2">
            <div class="font-semibold text-gray-700 border-b pb-1">出貨資訊</div>
            <div>
              <span class="text-gray-500">銷售訂單：</span>
              <span class="font-mono text-xs">{{ ship.so_number ?? `SO-${ship.sales_order_id}` }}</span>
            </div>
            <div>
              <span class="text-gray-500">出貨日期：</span>
              <span>{{ formatDate(ship.shipped_at) }}</span>
            </div>
            <div>
              <span class="text-gray-500">狀態：</span>
              <span>{{ ship.status === 'shipped' ? '已出貨' : ship.status === 'pending' ? '待出貨' : '已取消' }}</span>
            </div>
          </div>
        </div>

        <!-- 商品明細表 -->
        <div>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="border-b-2 border-gray-800">
                <th class="py-2 text-left font-semibold">編號</th>
                <th class="py-2 text-left font-semibold">SKU</th>
                <th class="py-2 text-left font-semibold">商品名稱</th>
                <th class="py-2 text-right font-semibold">出貨數量</th>
                <th class="py-2 text-left font-semibold">批號</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(line, idx) in lines"
                :key="line.id"
                class="border-b border-gray-200"
              >
                <td class="py-2 text-gray-500">{{ idx + 1 }}</td>
                <td class="py-2 font-mono text-xs">{{ line.sku_code ?? '-' }}</td>
                <td class="py-2">{{ line.item_name ?? '-' }}</td>
                <td class="py-2 text-right font-semibold">{{ line.shipped_qty }}</td>
                <td class="py-2 font-mono text-xs">{{ line.batch_number || '-' }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-gray-800">
                <td colspan="3" class="py-2 font-semibold">合計件數</td>
                <td class="py-2 text-right font-bold">
                  {{ lines.reduce((s, l) => s + Number(l.shipped_qty), 0) }}
                </td>
                <td />
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- 備註 -->
        <div v-if="ship.notes" class="rounded border border-gray-200 p-3 text-sm">
          <span class="text-gray-500 font-medium">備註：</span>{{ ship.notes }}
        </div>

        <!-- 簽名欄 -->
        <div class="grid grid-cols-3 gap-4 pt-8 text-sm text-center">
          <div class="space-y-8">
            <div class="border-b border-gray-400" />
            <div class="text-gray-500">倉管人員</div>
          </div>
          <div class="space-y-8">
            <div class="border-b border-gray-400" />
            <div class="text-gray-500">出貨確認</div>
          </div>
          <div class="space-y-8">
            <div class="border-b border-gray-400" />
            <div class="text-gray-500">收件簽收</div>
          </div>
        </div>

        <!-- 頁尾 -->
        <div class="border-t pt-4 text-xs text-gray-400 text-center">
          列印時間：{{ printTime }}
        </div>
      </div>
    </div>
  </div>
</template>

<style>
@media print {
  @page {
    size: A4;
    margin: 1cm;
  }
}
</style>
