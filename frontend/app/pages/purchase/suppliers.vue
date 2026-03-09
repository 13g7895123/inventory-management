<script setup lang="ts">
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Supplier } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const supplierStore = useSupplierStore()

onMounted(() => supplierStore.fetchList({ per_page: 50 }))

// ── Dialog ────────────────────────────────────────────────────────────
const dialogOpen = ref(false)
const editingId  = ref<number | null>(null)
const errorMsg   = ref('')

// ── 表單驗證 ─────────────────────────────────────────────────────────
const schema = toTypedSchema(
  z.object({
    code:           z.string().min(1, '請輸入代碼').max(32),
    name:           z.string().min(1, '請輸入供應商名稱').max(255),
    contact_name:   z.string().max(100).optional(),
    contact_phone:  z.string().max(20).optional(),
    contact_email:  z.string().email('格式錯誤').optional().or(z.literal('')),
    tax_id:         z.string().max(20).optional(),
    payment_terms:  z.string().max(100).optional(),
    lead_time_days: z.number().int().min(0).max(365),
    is_active:      z.boolean(),
    notes:          z.string().max(1000).optional(),
  })
)

const { handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: {
    code: '', name: '', contact_name: '', contact_phone: '',
    contact_email: '', tax_id: '', payment_terms: '',
    lead_time_days: 7, is_active: true, notes: '',
  },
})

const { value: code }           = useField<string>('code')
const { value: name }           = useField<string>('name')
const { value: contact_name }   = useField<string>('contact_name')
const { value: contact_phone }  = useField<string>('contact_phone')
const { value: contact_email }  = useField<string>('contact_email')
const { value: tax_id }         = useField<string>('tax_id')
const { value: payment_terms }  = useField<string>('payment_terms')
const { value: lead_time_days } = useField<number>('lead_time_days')
const { value: is_active }      = useField<boolean>('is_active')
const { value: notes }          = useField<string>('notes')

function openCreate() {
  editingId.value = null
  resetForm()
  errorMsg.value  = ''
  dialogOpen.value = true
}

function openEdit(s: Supplier) {
  editingId.value = s.id
  setValues({
    code:           s.code,
    name:           s.name,
    contact_name:   s.contact_name ?? '',
    contact_phone:  s.contact_phone ?? '',
    contact_email:  s.contact_email ?? '',
    tax_id:         s.tax_id ?? '',
    payment_terms:  s.payment_terms ?? '',
    lead_time_days: s.lead_time_days,
    is_active:      s.is_active,
    notes:          s.notes ?? '',
  })
  errorMsg.value  = ''
  dialogOpen.value = true
}

const onSubmit = handleSubmit(async (values) => {
  errorMsg.value = ''
  try {
    const payload = {
      ...values,
      contact_name:  values.contact_name  || null,
      contact_phone: values.contact_phone || null,
      contact_email: values.contact_email || null,
      tax_id:        values.tax_id        || null,
      payment_terms: values.payment_terms || null,
      notes:         values.notes         || null,
    }
    if (editingId.value) {
      await supplierStore.update(editingId.value, payload)
    } else {
      await supplierStore.create(payload)
    }
    dialogOpen.value = false
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '儲存供應商失敗'
  }
})

// ── 搜尋 ─────────────────────────────────────────────────────────────
const keyword = ref('')
const page    = ref(1)

function doSearch() {
  page.value = 1
  load()
}

function load() {
  const params: Record<string, unknown> = { page: page.value, per_page: 20 }
  if (keyword.value) params.keyword = keyword.value
  supplierStore.fetchList(params)
}

function onPageChange(p: number) {
  page.value = p
  load()
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">供應商管理</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          共 {{ supplierStore.pagination?.total ?? supplierStore.suppliers.length }} 筆
        </p>
      </div>
      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="openCreate"
      >
        + 新增供應商
      </button>
    </div>

    <!-- 搜尋列 -->
    <div class="flex gap-2">
      <input
        v-model="keyword"
        type="text"
        placeholder="搜尋名稱…"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm w-56 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @keyup.enter="doSearch"
      />
      <button
        class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
        @click="doSearch"
      >
        搜尋
      </button>
    </div>

    <!-- 載入中 -->
    <div v-if="supplierStore.loading" class="flex justify-center py-16">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <!-- 表格 -->
    <div v-else class="rounded-lg border overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-muted/50">
          <tr>
            <th class="px-4 py-3 text-left font-medium">代碼</th>
            <th class="px-4 py-3 text-left font-medium">名稱</th>
            <th class="px-4 py-3 text-left font-medium">聯絡人</th>
            <th class="px-4 py-3 text-left font-medium">電話</th>
            <th class="px-4 py-3 text-left font-medium">前置天數</th>
            <th class="px-4 py-3 text-center font-medium">狀態</th>
            <th class="px-4 py-3 text-center font-medium">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="s in supplierStore.suppliers"
            :key="s.id"
            class="hover:bg-muted/30 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs">{{ s.code }}</td>
            <td class="px-4 py-3 font-medium">{{ s.name }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ s.contact_name ?? '-' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ s.contact_phone ?? '-' }}</td>
            <td class="px-4 py-3 text-center">{{ s.lead_time_days }} 天</td>
            <td class="px-4 py-3 text-center">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="s.is_active
                  ? 'bg-green-100 text-green-700'
                  : 'bg-gray-100 text-gray-500'"
              >
                {{ s.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <button
                class="text-sm text-blue-600 hover:underline"
                @click="openEdit(s)"
              >
                編輯
              </button>
            </td>
          </tr>
          <tr v-if="!supplierStore.loading && supplierStore.suppliers.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
              尚無供應商資料
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div
      v-if="supplierStore.pagination && supplierStore.pagination.total_pages > 1"
      class="flex items-center justify-center gap-2"
    >
      <button
        :disabled="page <= 1"
        class="rounded border px-3 py-1.5 text-sm disabled:opacity-40"
        @click="onPageChange(page - 1)"
      >
        上一頁
      </button>
      <span class="text-sm text-muted-foreground">
        第 {{ page }} / {{ supplierStore.pagination.total_pages }} 頁
      </span>
      <button
        :disabled="page >= supplierStore.pagination.total_pages"
        class="rounded border px-3 py-1.5 text-sm disabled:opacity-40"
        @click="onPageChange(page + 1)"
      >
        下一頁
      </button>
    </div>

    <!-- 新增/編輯 Dialog -->
    <Teleport to="body">
      <div
        v-if="dialogOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="dialogOpen = false"
      >
        <div class="w-full max-w-lg rounded-xl bg-background shadow-xl p-6 space-y-4">
          <h2 class="text-lg font-semibold">
            {{ editingId ? '編輯供應商' : '新增供應商' }}
          </h2>

          <p
            v-if="errorMsg"
            class="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive"
          >
            {{ errorMsg }}
          </p>

          <form class="space-y-4" @submit.prevent="onSubmit">
            <!-- 基本資訊 -->
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="text-sm font-medium">代碼 <span class="text-destructive">*</span></label>
                <input
                  v-model="code"
                  type="text"
                  :disabled="!!editingId"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:opacity-60"
                />
                <p v-if="errors.code" class="text-xs text-destructive">{{ errors.code }}</p>
              </div>
              <div class="space-y-1">
                <label class="text-sm font-medium">名稱 <span class="text-destructive">*</span></label>
                <input
                  v-model="name"
                  type="text"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                />
                <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
              </div>
            </div>

            <!-- 聯絡資訊 -->
            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="text-sm font-medium">聯絡人</label>
                <input v-model="contact_name" type="text" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
              </div>
              <div class="space-y-1">
                <label class="text-sm font-medium">聯絡電話</label>
                <input v-model="contact_phone" type="text" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="text-sm font-medium">Email</label>
                <input v-model="contact_email" type="email" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
                <p v-if="errors.contact_email" class="text-xs text-destructive">{{ errors.contact_email }}</p>
              </div>
              <div class="space-y-1">
                <label class="text-sm font-medium">統一編號</label>
                <input v-model="tax_id" type="text" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="space-y-1">
                <label class="text-sm font-medium">付款條件</label>
                <input v-model="payment_terms" type="text" placeholder="如 Net30" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
              </div>
              <div class="space-y-1">
                <label class="text-sm font-medium">前置天數</label>
                <input v-model.number="lead_time_days" type="number" min="0" max="365" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
              </div>
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium">備註</label>
              <textarea v-model="notes" rows="2" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none" />
            </div>

            <!-- 狀態 -->
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="is_active" type="checkbox" class="h-4 w-4" />
              <span class="text-sm font-medium">啟用此供應商</span>
            </label>

            <!-- 操作按鈕 -->
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
                :disabled="supplierStore.saving"
                class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-60 transition-colors"
              >
                {{ supplierStore.saving ? '儲存中…' : '儲存' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
