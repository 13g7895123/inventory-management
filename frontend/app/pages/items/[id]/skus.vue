<script setup lang="ts">
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { ItemSku } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const route     = useRoute()
const router    = useRouter()
const itemStore = useItemStore()

const itemId = computed(() => Number(route.params.id))

onMounted(async () => {
  await Promise.all([
    itemStore.fetchOne(itemId.value),
    itemStore.fetchSkus(itemId.value),
  ])
})

// ── Dialog ────────────────────────────────────────────────────────────
const dialogOpen    = ref(false)
const editingSkuId  = ref<number | null>(null)
const deleteConfirm = ref<number | null>(null)
const errorMsg      = ref('')

// ── 表單 ─────────────────────────────────────────────────────────────
const schema = toTypedSchema(
  z.object({
    sku_code:      z.string().min(1, 'SKU 代碼不可空白').max(100),
    cost_price:    z.number().min(0, '請輸入有效金額'),
    selling_price: z.number().min(0, '請輸入有效金額'),
    attributes:    z.string().max(500),
    is_active:     z.boolean(),
  })
)

const { handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: { sku_code: '', cost_price: 0, selling_price: 0, attributes: '', is_active: true },
})

const { value: sku_code }      = useField<string>('sku_code')
const { value: cost_price }    = useField<number>('cost_price')
const { value: selling_price } = useField<number>('selling_price')
const { value: attributes }    = useField<string>('attributes')
const { value: is_active }     = useField<boolean>('is_active')

function openCreate() {
  editingSkuId.value = null
  resetForm()
  // 建議 SKU 代碼前綴
  sku_code.value = `${itemStore.current?.code ?? ''}-`
  errorMsg.value  = ''
  dialogOpen.value = true
}

function openEdit(sku: ItemSku) {
  editingSkuId.value = sku.id
  setValues({
    sku_code:      sku.sku_code,
    cost_price:    sku.cost_price,
    selling_price: sku.selling_price,
    attributes:    sku.attributes ? JSON.stringify(sku.attributes) : '',
    is_active:     sku.is_active,
  })
  errorMsg.value  = ''
  dialogOpen.value = true
}

const onSubmit = handleSubmit(async (values) => {
  errorMsg.value = ''
  try {
    const payload = {
      ...values,
      attributes: values.attributes ? (() => {
        try { return JSON.parse(values.attributes) } catch { return {} }
      })() : {},
    }

    if (editingSkuId.value) {
      await itemStore.updateSku(editingSkuId.value, payload)
    } else {
      await itemStore.createSku(itemId.value, payload)
    }
    dialogOpen.value = false
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '儲存失敗'
  }
})

async function confirmDelete(id: number) {
  try {
    await itemStore.removeSku(id)
  } catch (e: unknown) {
    alert(e instanceof Error ? e.message : '刪除失敗')
  } finally {
    deleteConfirm.value = null
  }
}

function formatAttributes(attrs: Record<string, string>): string {
  if (!attrs || !Object.keys(attrs).length) return '—'
  return Object.entries(attrs).map(([k, v]) => `${k}: ${v}`).join('，')
}
</script>

<template>
  <div class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/items" class="hover:text-foreground">商品管理</NuxtLink>
      <span>›</span>
      <NuxtLink :to="`/items/${itemId}/edit`" class="hover:text-foreground">
        {{ itemStore.current?.name ?? '商品' }}
      </NuxtLink>
      <span>›</span>
      <span class="text-foreground">SKU 管理</span>
    </div>

    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">SKU 管理</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          商品：{{ itemStore.current?.name ?? '—' }}（{{ itemStore.current?.code }}）
        </p>
      </div>
      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="openCreate"
      >
        + 新增 SKU
      </button>
    </div>

    <!-- 載入中 -->
    <div v-if="itemStore.loading" class="py-12 text-center text-muted-foreground">載入中…</div>

    <!-- SKU 表格 -->
    <div v-else class="rounded-xl border bg-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">SKU 代碼</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">屬性</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">成本價</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">售價</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">狀態</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="itemStore.skus.length === 0">
            <td colspan="6" class="py-12 text-center text-muted-foreground">尚無 SKU</td>
          </tr>
          <tr
            v-for="sku in itemStore.skus"
            :key="sku.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs">{{ sku.sku_code }}</td>
            <td class="px-4 py-3 text-muted-foreground text-xs">
              {{ formatAttributes(sku.attributes) }}
            </td>
            <td class="px-4 py-3 text-right tabular-nums">
              {{ sku.cost_price.toLocaleString('zh-TW', { minimumFractionDigits: 2 }) }}
            </td>
            <td class="px-4 py-3 text-right tabular-nums">
              {{ sku.selling_price.toLocaleString('zh-TW', { minimumFractionDigits: 2 }) }}
            </td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                  sku.is_active
                    ? 'bg-green-100 text-green-700'
                    : 'bg-gray-100 text-gray-500',
                ]"
              >
                {{ sku.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button class="text-sm text-blue-600 hover:underline" @click="openEdit(sku)">
                  編輯
                </button>
                <button
                  class="text-sm text-destructive hover:underline"
                  @click="deleteConfirm = sku.id"
                >
                  刪除
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 返回按鈕 -->
    <div>
      <button
        class="text-sm text-muted-foreground hover:text-foreground"
        @click="router.push(`/items/${itemId}/edit`)"
      >
        ← 返回商品編輯
      </button>
    </div>

    <!-- SKU Dialog -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="dialogOpen"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
          @click.self="dialogOpen = false"
        >
          <div class="w-full max-w-lg rounded-xl bg-card border p-6 shadow-xl">
            <h2 class="text-lg font-semibold mb-4">
              {{ editingSkuId ? '編輯 SKU' : '新增 SKU' }}
            </h2>

            <form class="space-y-4" @submit.prevent="onSubmit">
              <!-- SKU 代碼 -->
              <div class="space-y-1.5">
                <label class="text-sm font-medium">SKU 代碼 <span class="text-destructive">*</span></label>
                <input
                  v-model="sku_code"
                  type="text"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-mono"
                  :class="{ 'border-destructive': errors.sku_code }"
                  placeholder="例：ITEM-001-RED-M"
                />
                <p v-if="errors.sku_code" class="text-xs text-destructive">{{ errors.sku_code }}</p>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <!-- 成本價 -->
                <div class="space-y-1.5">
                  <label class="text-sm font-medium">成本價</label>
                  <input
                    v-model.number="cost_price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    :class="{ 'border-destructive': errors.cost_price }"
                    placeholder="0.00"
                  />
                </div>

                <!-- 售價 -->
                <div class="space-y-1.5">
                  <label class="text-sm font-medium">售價</label>
                  <input
                    v-model.number="selling_price"
                    type="number"
                    min="0"
                    step="0.01"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    :class="{ 'border-destructive': errors.selling_price }"
                    placeholder="0.00"
                  />
                </div>
              </div>

              <!-- 屬性 JSON -->
              <div class="space-y-1.5">
                <label class="text-sm font-medium">屬性（JSON 格式）</label>
                <input
                  v-model="attributes"
                  type="text"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-mono"
                  placeholder='{"color":"red","size":"M"}'
                />
                <p class="text-xs text-muted-foreground">例：{"顏色":"紅色","尺寸":"M"}</p>
              </div>

              <!-- 狀態 -->
              <div class="flex items-center gap-2">
                <input
                  id="sku-active"
                  v-model="is_active"
                  type="checkbox"
                  class="h-4 w-4 rounded border-input"
                />
                <label for="sku-active" class="text-sm">啟用此 SKU</label>
              </div>

              <p v-if="errorMsg" class="text-sm text-destructive">{{ errorMsg }}</p>

              <div class="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
                  @click="dialogOpen = false"
                >
                  取消
                </button>
                <button
                  type="submit"
                  class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                >
                  儲存
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- 刪除確認 -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="deleteConfirm !== null"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        >
          <div class="w-full max-w-sm rounded-xl bg-card border p-6 shadow-xl">
            <h3 class="text-lg font-semibold mb-2">確認刪除</h3>
            <p class="text-sm text-muted-foreground mb-4">
              刪除後無法復原。若此 SKU 有相關庫存紀錄，將無法刪除。
            </p>
            <div class="flex justify-end gap-2">
              <button
                class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
                @click="deleteConfirm = null"
              >
                取消
              </button>
              <button
                class="rounded-md bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground hover:opacity-90"
                @click="confirmDelete(deleteConfirm!)"
              >
                確認刪除
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
