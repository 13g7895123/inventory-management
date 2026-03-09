<script setup lang="ts">
definePageMeta({ layout: 'default' })

const router  = useRouter()
const store   = useStocktakesStore()
const whStore = useWarehousesStore()

onMounted(() => whStore.fetchAll())

const warehouseId = ref<string | number>('')
const notes       = ref('')

const isValid = computed(() => !!warehouseId.value)

async function submit() {
  if (!isValid.value) return
  try {
    const created = await store.create({
      warehouse_id: Number(warehouseId.value),
      notes:        notes.value || undefined,
    })
    router.push('/inventory/stocktakes')
  } catch {
    // error held in store
  }
}
</script>

<template>
  <div class="space-y-6 max-w-lg">
    <!-- 標題列 -->
    <div class="flex items-center gap-3">
      <NuxtLink to="/inventory/stocktakes" class="text-muted-foreground hover:text-foreground">←</NuxtLink>
      <h1 class="text-2xl font-semibold">新增盤點任務</h1>
    </div>

    <!-- 錯誤 -->
    <div v-if="store.error" class="rounded-md border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
      {{ store.error }}
    </div>

    <!-- 表單 -->
    <div class="rounded-xl border bg-card p-6 space-y-4">
      <div>
        <label class="mb-1 block text-sm font-medium">盤點倉庫 <span class="text-destructive">*</span></label>
        <select
          v-model="warehouseId"
          class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        >
          <option value="">請選擇倉庫</option>
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
        <label class="mb-1 block text-sm font-medium">備註</label>
        <textarea
          v-model="notes"
          rows="3"
          placeholder="盤點說明（選填）"
          class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
        />
      </div>

      <div class="rounded-md bg-muted/40 px-4 py-3 text-sm text-muted-foreground">
        建立後需點擊「開始盤點」才會鎖定庫存快照並允許輸入實盤量。
      </div>
    </div>

    <!-- 提交 -->
    <div class="flex gap-3 justify-end">
      <NuxtLink
        to="/inventory/stocktakes"
        class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
      >
        取消
      </NuxtLink>
      <button
        :disabled="!isValid || store.saving"
        class="rounded-md bg-primary px-6 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50"
        @click="submit"
      >
        {{ store.saving ? '建立中…' : '建立盤點任務' }}
      </button>
    </div>
  </div>
</template>
