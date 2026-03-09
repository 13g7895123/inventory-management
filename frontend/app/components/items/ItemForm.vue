<script setup lang="ts">
// components/items/ItemForm.vue
// 新增/編輯商品的共用表單元件（含 SKU 表格）

import { useForm, useField, useFieldArray } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Item } from '~/app/types/api'

// ── Props / Emits ─────────────────────────────────────────────────────
const props = defineProps<{
  initial?: Partial<Item>
  saving?:  boolean
}>()

const emit = defineEmits<{
  (e: 'submit', payload: Record<string, unknown>): void
  (e: 'cancel'): void
}>()

const categoryStore = useCategoryStore()
const unitStore     = useUnitStore()

onMounted(async () => {
  await Promise.all([categoryStore.fetchAll(), unitStore.fetchAll()])
})

// ── Schema ────────────────────────────────────────────────────────────
const skuSchema = z.object({
  sku_code:      z.string().min(1, 'SKU 代碼不可空白').max(100),
  cost_price:    z.number().min(0),
  selling_price: z.number().min(0),
  attributes:    z.string().max(500), // JSON 字串，前端用文字輸入
})

const formSchema = toTypedSchema(
  z.object({
    code:           z.string().min(1, '請輸入料號').max(64),
    name:           z.string().min(1, '請輸入商品名稱').max(255),
    category_id:    z.number({ required_error: '請選擇分類' }).positive('請選擇分類'),
    unit_id:        z.number({ required_error: '請選擇單位' }).positive('請選擇單位'),
    description:    z.string().max(2000).optional(),
    tax_type:       z.enum(['taxable', 'zero', 'exempt']),
    reorder_point:  z.number().min(0),
    safety_stock:   z.number().min(0),
    lead_time_days: z.number().int().min(0),
    is_active:      z.boolean(),
    skus: z.array(skuSchema).min(1, '至少需要一個 SKU'),
  })
)

// ── 初始值 ────────────────────────────────────────────────────────────
const defaultSku = { sku_code: '', cost_price: 0, selling_price: 0, attributes: '' }

const initSkus = computed(() => {
  if (props.initial?.skus?.length) {
    return props.initial.skus.map((s) => ({
      sku_code:      s.sku_code,
      cost_price:    s.cost_price,
      selling_price: s.selling_price,
      attributes:    s.attributes ? JSON.stringify(s.attributes) : '',
    }))
  }
  return [{ ...defaultSku }]
})

const { handleSubmit, errors } = useForm({
  validationSchema: formSchema,
  initialValues: {
    code:           props.initial?.code           ?? '',
    name:           props.initial?.name           ?? '',
    category_id:    props.initial?.category_id    ?? 0,
    unit_id:        props.initial?.unit_id        ?? 0,
    description:    props.initial?.description    ?? '',
    tax_type:       props.initial?.tax_type        ?? 'taxable',
    reorder_point:  props.initial?.reorder_point  ?? 0,
    safety_stock:   props.initial?.safety_stock   ?? 0,
    lead_time_days: props.initial?.lead_time_days ?? 0,
    is_active:      props.initial?.is_active      ?? true,
    skus:           initSkus.value,
  },
})

const { value: code }           = useField<string>('code')
const { value: name }           = useField<string>('name')
const { value: category_id }    = useField<number>('category_id')
const { value: unit_id }        = useField<number>('unit_id')
const { value: description }    = useField<string>('description')
const { value: tax_type }       = useField<string>('tax_type')
const { value: reorder_point }  = useField<number>('reorder_point')
const { value: safety_stock }   = useField<number>('safety_stock')
const { value: lead_time_days } = useField<number>('lead_time_days')
const { value: is_active }      = useField<boolean>('is_active')

const { fields: skuFields, push: addSku, remove: removeSku } = useFieldArray('skus')

// ── 提交 ─────────────────────────────────────────────────────────────
const onSubmit = handleSubmit((values) => {
  const payload: Record<string, unknown> = {
    ...values,
    skus: values.skus.map((s) => ({
      sku_code:      s.sku_code,
      cost_price:    s.cost_price,
      selling_price: s.selling_price,
      attributes:    s.attributes ? (() => {
        try { return JSON.parse(s.attributes) }
        catch { return {} }
      })() : {},
    })),
  }
  emit('submit', payload)
})
</script>

<template>
  <form class="space-y-8" @submit.prevent="onSubmit">
    <!-- ── 基本資料 ──────────────────────────────────────────────── -->
    <section class="rounded-xl border bg-card p-6 space-y-4">
      <h2 class="text-base font-semibold">基本資料</h2>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <!-- 料號 -->
        <div class="space-y-1.5">
          <label class="text-sm font-medium">料號 <span class="text-destructive">*</span></label>
          <input
            v-model="code"
            type="text"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            :class="{ 'border-destructive': errors.code }"
            placeholder="例：ITEM-001"
          />
          <p v-if="errors.code" class="text-xs text-destructive">{{ errors.code }}</p>
        </div>

        <!-- 商品名稱 -->
        <div class="space-y-1.5">
          <label class="text-sm font-medium">商品名稱 <span class="text-destructive">*</span></label>
          <input
            v-model="name"
            type="text"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            :class="{ 'border-destructive': errors.name }"
            placeholder="請輸入商品名稱"
          />
          <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
        </div>

        <!-- 分類 -->
        <div class="space-y-1.5">
          <label class="text-sm font-medium">分類 <span class="text-destructive">*</span></label>
          <select
            v-model.number="category_id"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            :class="{ 'border-destructive': errors.category_id }"
          >
            <option :value="0" disabled>請選擇分類</option>
            <option
              v-for="cat in categoryStore.categories"
              :key="cat.id"
              :value="cat.id"
            >
              {{ cat.name }}
            </option>
          </select>
          <p v-if="errors.category_id" class="text-xs text-destructive">{{ errors.category_id }}</p>
        </div>

        <!-- 單位 -->
        <div class="space-y-1.5">
          <label class="text-sm font-medium">計量單位 <span class="text-destructive">*</span></label>
          <select
            v-model.number="unit_id"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            :class="{ 'border-destructive': errors.unit_id }"
          >
            <option :value="0" disabled>請選擇單位</option>
            <option
              v-for="unit in unitStore.activeUnits"
              :key="unit.id"
              :value="unit.id"
            >
              {{ unit.name }}（{{ unit.symbol }}）
            </option>
          </select>
          <p v-if="errors.unit_id" class="text-xs text-destructive">{{ errors.unit_id }}</p>
        </div>

        <!-- 稅別 -->
        <div class="space-y-1.5">
          <label class="text-sm font-medium">稅別</label>
          <select
            v-model="tax_type"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          >
            <option value="taxable">應稅</option>
            <option value="zero">零稅率</option>
            <option value="exempt">免稅</option>
          </select>
        </div>

        <!-- 狀態 -->
        <div class="flex items-center gap-2 pt-6">
          <input
            id="item-active"
            v-model="is_active"
            type="checkbox"
            class="h-4 w-4 rounded border-input"
          />
          <label for="item-active" class="text-sm font-medium">啟用此商品</label>
        </div>
      </div>

      <!-- 描述 -->
      <div class="space-y-1.5">
        <label class="text-sm font-medium">商品描述</label>
        <textarea
          v-model="description"
          rows="3"
          class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
          placeholder="選填"
        />
      </div>
    </section>

    <!-- ── 庫存設定 ──────────────────────────────────────────────── -->
    <section class="rounded-xl border bg-card p-6 space-y-4">
      <h2 class="text-base font-semibold">庫存設定</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="space-y-1.5">
          <label class="text-sm font-medium">再訂購點</label>
          <input
            v-model.number="reorder_point"
            type="number"
            min="0"
            step="0.01"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
        <div class="space-y-1.5">
          <label class="text-sm font-medium">安全庫存量</label>
          <input
            v-model.number="safety_stock"
            type="number"
            min="0"
            step="0.01"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
        <div class="space-y-1.5">
          <label class="text-sm font-medium">前置天數（天）</label>
          <input
            v-model.number="lead_time_days"
            type="number"
            min="0"
            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
          />
        </div>
      </div>
    </section>

    <!-- ── SKU 變體 ────────────────────────────────────────────────── -->
    <section class="rounded-xl border bg-card p-6 space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-base font-semibold">SKU 變體設定</h2>
          <p class="text-xs text-muted-foreground mt-0.5">
            每個 SKU 代表一個可獨立計價的品項（如：顏色 + 尺寸組合）
          </p>
        </div>
        <button
          type="button"
          class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted transition-colors"
          @click="addSku({ ...defaultSku })"
        >
          + 新增 SKU
        </button>
      </div>

      <p v-if="errors.skus" class="text-xs text-destructive">{{ errors.skus }}</p>

      <div class="space-y-3">
        <div
          v-for="(field, idx) in skuFields"
          :key="field.key"
          class="rounded-lg border p-4 space-y-3 bg-muted/10"
        >
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-muted-foreground">SKU {{ idx + 1 }}</span>
            <button
              v-if="skuFields.length > 1"
              type="button"
              class="text-xs text-destructive hover:underline"
              @click="removeSku(idx)"
            >
              移除
            </button>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <!-- SKU 代碼 -->
            <div class="space-y-1">
              <label class="text-xs font-medium">SKU 代碼 *</label>
              <input
                :name="`skus[${idx}].sku_code`"
                v-model="(field.value as Record<string,unknown>).sku_code"
                type="text"
                class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                placeholder="例：ITEM-001-RED-M"
              />
            </div>

            <!-- 成本價 -->
            <div class="space-y-1">
              <label class="text-xs font-medium">成本價</label>
              <input
                v-model.number="(field.value as Record<string,unknown>).cost_price"
                type="number"
                min="0"
                step="0.01"
                class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                placeholder="0.00"
              />
            </div>

            <!-- 售價 -->
            <div class="space-y-1">
              <label class="text-xs font-medium">售價</label>
              <input
                v-model.number="(field.value as Record<string,unknown>).selling_price"
                type="number"
                min="0"
                step="0.01"
                class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                placeholder="0.00"
              />
            </div>

            <!-- 屬性（JSON） -->
            <div class="space-y-1">
              <label class="text-xs font-medium">屬性（JSON）</label>
              <input
                v-model="(field.value as Record<string,unknown>).attributes"
                type="text"
                class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring font-mono"
                placeholder='{"color":"red","size":"M"}'
              />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ── 操作按鈕 ────────────────────────────────────────────────── -->
    <div class="flex justify-end gap-3">
      <button
        type="button"
        class="rounded-md border px-5 py-2 text-sm hover:bg-muted transition-colors"
        @click="emit('cancel')"
      >
        取消
      </button>
      <button
        type="submit"
        :disabled="props.saving"
        class="rounded-md bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
      >
        {{ props.saving ? '儲存中…' : '儲存商品' }}
      </button>
    </div>
  </form>
</template>
