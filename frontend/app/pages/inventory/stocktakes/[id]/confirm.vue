<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route  = useRoute()
const router = useRouter()
const store  = useStocktakesStore()

const id = Number(route.params.id)

onMounted(() => store.fetchOne(id))

// 統計
const gainLines = computed(() => store.lines.filter(l => (l.difference_qty ?? 0) > 0))
const lossLines = computed(() => store.lines.filter(l => (l.difference_qty ?? 0) < 0))
const evenLines = computed(() => store.lines.filter(l => (l.difference_qty ?? 0) === 0))

const totalGain = computed(() => gainLines.value.reduce((s, l) => s + (l.difference_qty ?? 0), 0))
const totalLoss = computed(() => lossLines.value.reduce((s, l) => s + (l.difference_qty ?? 0), 0))

const confirming = ref(false)
const confirmError = ref<string | null>(null)

async function doConfirm() {
  if (!confirm('確定要確認盤點並寫入庫存調整？此操作不可復原。')) return
  confirming.value  = true
  confirmError.value = null
  try {
    await store.confirm(id)
    router.push('/inventory/stocktakes')
  } catch (e: unknown) {
    confirmError.value = e instanceof Error ? e.message : '確認失敗'
  } finally {
    confirming.value = false
  }
}

function diffClass(diff: number | null) {
  if (diff == null) return ''
  if (diff > 0)  return 'text-green-600 font-semibold'
  if (diff < 0)  return 'text-red-600 font-semibold'
  return 'text-muted-foreground'
}

function diffLabel(diff: number | null) {
  if (diff == null) return '—'
  if (diff > 0) return `+${diff}`
  return String(diff)
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <NuxtLink :to="`/inventory/stocktakes/${id}`" class="text-muted-foreground hover:text-foreground">←</NuxtLink>
        <div>
          <h1 class="text-2xl font-semibold">盤點確認</h1>
          <p v-if="store.current" class="mt-0.5 text-sm text-muted-foreground">
            {{ store.current.stocktake_number }} · {{ store.current.warehouse_name }}
          </p>
        </div>
      </div>
    </div>

    <!-- 載入中 -->
    <div v-if="store.loading" class="py-16 text-center text-muted-foreground">載入中…</div>

    <template v-else>
      <!-- 盈虧摘要 -->
      <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl border bg-card p-5">
          <p class="text-sm text-muted-foreground">盤盈品項</p>
          <p class="mt-1 text-2xl font-bold text-green-600">{{ gainLines.length }}</p>
          <p class="text-xs text-muted-foreground mt-0.5">共 +{{ totalGain }} 件</p>
        </div>
        <div class="rounded-xl border bg-card p-5">
          <p class="text-sm text-muted-foreground">盤虧品項</p>
          <p class="mt-1 text-2xl font-bold text-red-600">{{ lossLines.length }}</p>
          <p class="text-xs text-muted-foreground mt-0.5">共 {{ totalLoss }} 件</p>
        </div>
        <div class="rounded-xl border bg-card p-5">
          <p class="text-sm text-muted-foreground">帳實相符</p>
          <p class="mt-1 text-2xl font-bold">{{ evenLines.length }}</p>
          <p class="text-xs text-muted-foreground mt-0.5">品項</p>
        </div>
      </div>

      <!-- 錯誤訊息 -->
      <div
        v-if="store.error || confirmError"
        class="rounded-md border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive"
      >
        {{ store.error || confirmError }}
      </div>

      <!-- 狀態提示 -->
      <div
        v-if="store.current?.status !== 'in_progress'"
        class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700"
      >
        此盤點任務狀態為「{{ store.current?.status }}」，無法進行確認操作。
      </div>

      <!-- 明細表格 -->
      <div class="rounded-xl border bg-card overflow-hidden">
        <div class="px-4 py-3 border-b bg-muted/40 text-sm font-medium">盈虧明細</div>
        <table class="w-full text-sm">
          <thead class="border-b bg-muted/20">
            <tr>
              <th class="px-4 py-3 text-left font-medium text-muted-foreground">SKU 代碼</th>
              <th class="px-4 py-3 text-left font-medium text-muted-foreground">商品名稱</th>
              <th class="px-4 py-3 text-right font-medium text-muted-foreground">系統帳量</th>
              <th class="px-4 py-3 text-right font-medium text-muted-foreground">實盤量</th>
              <th class="px-4 py-3 text-right font-medium text-muted-foreground">差異</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="store.lines.length === 0">
              <td colspan="5" class="py-16 text-center text-muted-foreground">尚無明細</td>
            </tr>
            <tr
              v-for="line in store.lines"
              :key="line.id"
              :class="[
                'border-b last:border-0 transition-colors',
                (line.difference_qty ?? 0) > 0 ? 'bg-green-50/60' :
                (line.difference_qty ?? 0) < 0 ? 'bg-red-50/60' : '',
              ]"
            >
              <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ line.sku_code || line.sku_id }}</td>
              <td class="px-4 py-3">{{ line.item_name || '—' }}</td>
              <td class="px-4 py-3 text-right">{{ line.system_qty }}</td>
              <td class="px-4 py-3 text-right">{{ line.actual_qty ?? '—' }}</td>
              <td class="px-4 py-3 text-right" :class="diffClass(line.difference_qty)">
                {{ diffLabel(line.difference_qty) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 操作區 -->
      <div v-if="store.current?.status === 'in_progress'" class="flex gap-3 justify-end">
        <NuxtLink
          :to="`/inventory/stocktakes/${id}`"
          class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
        >
          返回修改
        </NuxtLink>
        <button
          :disabled="confirming"
          class="rounded-md bg-green-600 px-6 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors disabled:opacity-50"
          @click="doConfirm"
        >
          {{ confirming ? '確認中…' : '確認並寫入庫存' }}
        </button>
      </div>
    </template>
  </div>
</template>
