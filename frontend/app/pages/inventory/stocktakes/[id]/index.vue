<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route  = useRoute()
const router = useRouter()
const store  = useStocktakesStore()

const id = Number(route.params.id)

onMounted(() => store.fetchOne(id))

// 儲存每行輸入中的實盤量（key = sku_id）
const inputMap = reactive<Record<number, string>>({})

watch(
  () => store.lines,
  (lines) => {
    for (const line of lines) {
      if (!(line.sku_id in inputMap)) {
        inputMap[line.sku_id] = line.actual_qty != null ? String(line.actual_qty) : ''
      }
    }
  },
  { immediate: true },
)

const saving = ref<number | null>(null)

async function saveCount(skuId: number) {
  const val = Number(inputMap[skuId])
  if (isNaN(val) || val < 0) return
  saving.value = skuId
  try {
    await store.updateCount(id, skuId, val)
  } finally {
    saving.value = null
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

const canConfirm = computed(() =>
  store.current?.status === 'in_progress' &&
  store.lines.every(l => l.actual_qty != null),
)
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <NuxtLink to="/inventory/stocktakes" class="text-muted-foreground hover:text-foreground">←</NuxtLink>
        <div>
          <h1 class="text-2xl font-semibold">盤點作業</h1>
          <p v-if="store.current" class="mt-0.5 text-sm text-muted-foreground">
            {{ store.current.stocktake_number }} · {{ store.current.warehouse_name }}
          </p>
        </div>
      </div>

      <NuxtLink
        v-if="canConfirm"
        :to="`/inventory/stocktakes/${id}/confirm`"
        class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors"
      >
        進入確認頁 →
      </NuxtLink>
    </div>

    <!-- 錯誤 -->
    <div v-if="store.error" class="rounded-md border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
      {{ store.error }}
    </div>

    <!-- 載入中 -->
    <div v-if="store.loading" class="py-16 text-center text-muted-foreground">載入中…</div>

    <!-- 提示 -->
    <div
      v-if="!store.loading && store.current?.status !== 'in_progress'"
      class="rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700"
    >
      此盤點任務狀態為「{{ store.current?.status }}」，僅盤點中的任務可輸入實盤量。
    </div>

    <!-- 明細表格 -->
    <div v-if="!store.loading" class="rounded-xl border bg-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">SKU 代碼</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">商品名稱</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">系統帳量</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">實盤量</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">差異</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.lines.length === 0">
            <td colspan="6" class="py-16 text-center text-muted-foreground">尚無盤點品項</td>
          </tr>
          <tr
            v-for="line in store.lines"
            :key="line.id"
            :class="[
              'border-b last:border-0 transition-colors',
              line.difference_qty != null && line.difference_qty !== 0 ? 'bg-red-50/60' : 'hover:bg-muted/20',
            ]"
          >
            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ line.sku_code || line.sku_id }}</td>
            <td class="px-4 py-3">{{ line.item_name || '—' }}</td>
            <td class="px-4 py-3 text-right">{{ line.system_qty }}</td>
            <td class="px-4 py-3 text-right">
              <input
                v-if="store.current?.status === 'in_progress'"
                v-model="inputMap[line.sku_id]"
                type="number"
                min="0"
                class="w-24 rounded-md border border-input bg-transparent px-2 py-1 text-sm text-right focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                @keyup.enter="saveCount(line.sku_id)"
              />
              <span v-else>{{ line.actual_qty ?? '—' }}</span>
            </td>
            <td class="px-4 py-3 text-right" :class="diffClass(line.difference_qty)">
              {{ diffLabel(line.difference_qty) }}
            </td>
            <td class="px-4 py-3 text-right">
              <button
                v-if="store.current?.status === 'in_progress'"
                :disabled="saving === line.sku_id"
                class="rounded-md border px-3 py-1 text-xs hover:bg-muted transition-colors disabled:opacity-50"
                @click="saveCount(line.sku_id)"
              >
                {{ saving === line.sku_id ? '儲存…' : '儲存' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
