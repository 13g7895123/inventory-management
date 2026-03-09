<script setup lang="ts">
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Customer, CustomerAddress } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const customerStore = useCustomerStore()

onMounted(() => customerStore.fetchList({ per_page: 50 }))

// ── 列表篩選 ──────────────────────────────────────────────────────────
const keywordFilter = ref('')
const page = ref(1)

function doFilter() {
  page.value = 1
  loadList()
}
function loadList() {
  const params: Record<string, unknown> = { page: page.value, per_page: 20 }
  if (keywordFilter.value) params.keyword = keywordFilter.value
  customerStore.fetchList(params)
}
function onPageChange(p: number) {
  page.value = p
  loadList()
}

// ── 客戶 Dialog ───────────────────────────────────────────────────────
const dialogOpen = ref(false)
const editingId  = ref<number | null>(null)
const errorMsg   = ref('')

const schema = toTypedSchema(
  z.object({
    code:          z.string().min(1, '請輸入代碼').max(32),
    name:          z.string().min(1, '請輸入客戶名稱').max(100),
    tax_id:        z.string().max(20).optional().or(z.literal('')),
    contact_name:  z.string().max(100).optional().or(z.literal('')),
    contact_phone: z.string().max(20).optional().or(z.literal('')),
    contact_email: z.string().email('Email 格式錯誤').optional().or(z.literal('')),
    credit_limit:  z.number().min(0, '不得小於 0'),
    payment_terms: z.string().max(100).optional().or(z.literal('')),
    is_active:     z.boolean(),
    notes:         z.string().max(1000).optional().or(z.literal('')),
  }),
)

const { handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: {
    code: '', name: '', tax_id: '', contact_name: '', contact_phone: '',
    contact_email: '', credit_limit: 0, payment_terms: '', is_active: true, notes: '',
  },
})

const { value: code }          = useField<string>('code')
const { value: name }          = useField<string>('name')
const { value: tax_id }        = useField<string>('tax_id')
const { value: contact_name }  = useField<string>('contact_name')
const { value: contact_phone } = useField<string>('contact_phone')
const { value: contact_email } = useField<string>('contact_email')
const { value: credit_limit }  = useField<number>('credit_limit')
const { value: payment_terms } = useField<string>('payment_terms')
const { value: is_active }     = useField<boolean>('is_active')
const { value: notes }         = useField<string>('notes')

function openCreate() {
  editingId.value = null
  resetForm()
  errorMsg.value = ''
  dialogOpen.value = true
}

function openEdit(c: Customer) {
  editingId.value = c.id
  setValues({
    code:          c.code,
    name:          c.name,
    tax_id:        c.tax_id ?? '',
    contact_name:  c.contact_name ?? '',
    contact_phone: c.contact_phone ?? '',
    contact_email: c.contact_email ?? '',
    credit_limit:  Number(c.credit_limit),
    payment_terms: c.payment_terms ?? '',
    is_active:     c.is_active,
    notes:         c.notes ?? '',
  })
  errorMsg.value = ''
  dialogOpen.value = true
}

const onSubmit = handleSubmit(async (values) => {
  errorMsg.value = ''
  try {
    if (editingId.value) {
      await customerStore.update(editingId.value, values)
    } else {
      await customerStore.create(values)
    }
    dialogOpen.value = false
    loadList()
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '儲存失敗'
  }
})

// ── 地址管理 Dialog ───────────────────────────────────────────────────
const addrDialogOpen  = ref(false)
const addrCustomerId  = ref<number | null>(null)
const addrCustomerName = ref('')
const addrLoading     = ref(false)
const addrList        = ref<CustomerAddress[]>([])
const addrForm        = reactive({
  label:         '',
  contact_name:  '',
  contact_phone: '',
  address_line1: '',
  address_line2: '',
  city:          '',
  postal_code:   '',
  country:       'TW',
  is_default:    false,
})
const addrErrorMsg = ref('')
const addrSaving   = ref(false)
const showAddrForm = ref(false)

async function openAddresses(c: Customer) {
  addrCustomerId.value   = c.id
  addrCustomerName.value = c.name
  addrLoading.value      = true
  addrDialogOpen.value   = true
  showAddrForm.value     = false
  addrErrorMsg.value     = ''
  try {
    await customerStore.fetchOne(c.id)
    addrList.value = customerStore.addresses
  } finally {
    addrLoading.value = false
  }
}

function openAddrForm() {
  Object.assign(addrForm, {
    label: '', contact_name: '', contact_phone: '',
    address_line1: '', address_line2: '', city: '',
    postal_code: '', country: 'TW', is_default: false,
  })
  addrErrorMsg.value = ''
  showAddrForm.value = true
}

async function saveAddress() {
  if (!addrCustomerId.value) return
  if (!addrForm.address_line1) { addrErrorMsg.value = '請填寫地址'; return }
  addrSaving.value = true
  addrErrorMsg.value = ''
  try {
    await customerStore.addAddress(addrCustomerId.value, { ...addrForm })
    addrList.value = customerStore.addresses
    showAddrForm.value = false
  } catch (e: unknown) {
    addrErrorMsg.value = e instanceof Error ? e.message : '新增地址失敗'
  } finally {
    addrSaving.value = false
  }
}

function fmt(val: number | string) {
  return Number(val).toLocaleString('zh-TW', { minimumFractionDigits: 0 })
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">客戶管理</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          共 {{ customerStore.pagination?.total ?? customerStore.customers.length }} 筆
        </p>
      </div>
      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="openCreate"
      >
        + 新增客戶
      </button>
    </div>

    <!-- 篩選列 -->
    <div class="flex gap-3">
      <input
        v-model="keywordFilter"
        type="text"
        placeholder="搜尋名稱..."
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm w-56 focus-visible:outline-none"
        @keyup.enter="doFilter"
      />
      <button
        class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
        @click="doFilter"
      >
        搜尋
      </button>
    </div>

    <!-- 載入中 -->
    <div v-if="customerStore.loading" class="flex justify-center py-16">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <!-- 表格 -->
    <div v-else class="rounded-lg border overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-muted/50">
          <tr>
            <th class="px-4 py-3 text-left font-medium">代碼</th>
            <th class="px-4 py-3 text-left font-medium">名稱</th>
            <th class="px-4 py-3 text-left font-medium">統一編號</th>
            <th class="px-4 py-3 text-left font-medium">聯絡人</th>
            <th class="px-4 py-3 text-right font-medium">信用額度</th>
            <th class="px-4 py-3 text-center font-medium">狀態</th>
            <th class="px-4 py-3 text-center font-medium">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr
            v-for="c in customerStore.customers"
            :key="c.id"
            class="hover:bg-muted/30 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs">{{ c.code }}</td>
            <td class="px-4 py-3 font-medium">{{ c.name }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ c.tax_id ?? '-' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ c.contact_name ?? '-' }}</td>
            <td class="px-4 py-3 text-right">{{ fmt(c.credit_limit) }}</td>
            <td class="px-4 py-3 text-center">
              <span
                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="c.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
              >
                {{ c.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center space-x-2">
              <button
                class="text-sm text-blue-600 hover:underline"
                @click="openEdit(c)"
              >
                編輯
              </button>
              <button
                class="text-sm text-gray-500 hover:underline"
                @click="openAddresses(c)"
              >
                地址
              </button>
            </td>
          </tr>
          <tr v-if="customerStore.customers.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-muted-foreground">
              尚無客戶資料
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div
      v-if="customerStore.pagination && customerStore.pagination.total_pages > 1"
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
        {{ page }} / {{ customerStore.pagination.total_pages }}
      </span>
      <button
        :disabled="page >= customerStore.pagination.total_pages"
        class="rounded border px-3 py-1.5 text-sm disabled:opacity-40"
        @click="onPageChange(page + 1)"
      >
        下一頁
      </button>
    </div>
  </div>

  <!-- ── 新增/編輯客戶 Dialog ── -->
  <Teleport to="body">
    <div
      v-if="dialogOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="dialogOpen = false"
    >
      <div class="w-full max-w-lg rounded-xl bg-background shadow-xl overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between border-b px-6 py-4">
          <h2 class="text-lg font-semibold">{{ editingId ? '編輯客戶' : '新增客戶' }}</h2>
          <button class="text-muted-foreground hover:text-foreground" @click="dialogOpen = false">✕</button>
        </div>

        <form class="space-y-4 px-6 py-5" @submit.prevent="onSubmit">
          <!-- 代碼 / 名稱 -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium">代碼 <span class="text-red-500">*</span></label>
              <input
                v-model="code"
                :disabled="!!editingId"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none disabled:opacity-50"
                placeholder="C001"
              />
              <p v-if="errors.code" class="mt-1 text-xs text-red-500">{{ errors.code }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">客戶名稱 <span class="text-red-500">*</span></label>
              <input
                v-model="name"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
                placeholder="公司名稱"
              />
              <p v-if="errors.name" class="mt-1 text-xs text-red-500">{{ errors.name }}</p>
            </div>
          </div>

          <!-- 統一編號 / 信用額度 -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium">統一編號</label>
              <input
                v-model="tax_id"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
                placeholder="12345678"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">信用額度</label>
              <input
                v-model.number="credit_limit"
                type="number"
                min="0"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
              />
              <p v-if="errors.credit_limit" class="mt-1 text-xs text-red-500">{{ errors.credit_limit }}</p>
            </div>
          </div>

          <!-- 聯絡人 / 電話 -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium">聯絡人</label>
              <input
                v-model="contact_name"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">聯絡電話</label>
              <input
                v-model="contact_phone"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
              />
            </div>
          </div>

          <!-- Email / 付款條件 -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium">Email</label>
              <input
                v-model="contact_email"
                type="email"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
              />
              <p v-if="errors.contact_email" class="mt-1 text-xs text-red-500">{{ errors.contact_email }}</p>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium">付款條件</label>
              <input
                v-model="payment_terms"
                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
                placeholder="月結 30 天"
              />
            </div>
          </div>

          <!-- 備註 -->
          <div>
            <label class="mb-1 block text-sm font-medium">備註</label>
            <textarea
              v-model="notes"
              rows="2"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none resize-none"
            />
          </div>

          <!-- 啟用 -->
          <div class="flex items-center gap-2">
            <input id="is-active" v-model="is_active" type="checkbox" class="h-4 w-4" />
            <label for="is-active" class="text-sm">啟用中</label>
          </div>

          <!-- 錯誤訊息 -->
          <p v-if="errorMsg" class="rounded bg-red-50 px-3 py-2 text-sm text-red-600">{{ errorMsg }}</p>

          <div class="flex justify-end gap-3 border-t pt-4">
            <button
              type="button"
              class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
              @click="dialogOpen = false"
            >
              取消
            </button>
            <button
              type="submit"
              :disabled="customerStore.saving"
              class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
            >
              {{ customerStore.saving ? '儲存中...' : '儲存' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </Teleport>

  <!-- ── 地址管理 Dialog ── -->
  <Teleport to="body">
    <div
      v-if="addrDialogOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      @click.self="addrDialogOpen = false"
    >
      <div class="w-full max-w-2xl rounded-xl bg-background shadow-xl overflow-y-auto max-h-[90vh]">
        <div class="flex items-center justify-between border-b px-6 py-4">
          <h2 class="text-lg font-semibold">{{ addrCustomerName }} — 收貨地址</h2>
          <button class="text-muted-foreground hover:text-foreground" @click="addrDialogOpen = false">✕</button>
        </div>

        <div class="px-6 py-5 space-y-4">
          <!-- 載入中 -->
          <div v-if="addrLoading" class="flex justify-center py-8">
            <div class="h-6 w-6 animate-spin rounded-full border-4 border-primary border-t-transparent" />
          </div>

          <template v-else>
            <!-- 地址列表 -->
            <div v-if="addrList.length > 0" class="space-y-2">
              <div
                v-for="addr in addrList"
                :key="addr.id"
                class="flex items-start justify-between rounded-lg border p-4"
              >
                <div class="space-y-0.5 text-sm">
                  <div class="flex items-center gap-2">
                    <span class="font-medium">{{ addr.label || '預設地址' }}</span>
                    <span
                      v-if="addr.is_default"
                      class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700"
                    >預設</span>
                  </div>
                  <p class="text-muted-foreground">
                    {{ addr.address_line1 }}
                    <span v-if="addr.address_line2">{{ addr.address_line2 }}</span>
                  </p>
                  <p v-if="addr.contact_name" class="text-muted-foreground">
                    {{ addr.contact_name }}
                    <span v-if="addr.contact_phone">｜{{ addr.contact_phone }}</span>
                  </p>
                </div>
              </div>
            </div>
            <p v-else class="text-center text-sm text-muted-foreground py-4">尚無收貨地址</p>

            <!-- 新增地址表單 -->
            <div v-if="showAddrForm" class="space-y-3 rounded-lg border p-4 bg-muted/20">
              <h3 class="text-sm font-medium">新增地址</h3>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="mb-1 block text-xs font-medium">標籤</label>
                  <input
                    v-model="addrForm.label"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm focus-visible:outline-none"
                    placeholder="公司 / 倉庫..."
                  />
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium">聯絡人</label>
                  <input
                    v-model="addrForm.contact_name"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm focus-visible:outline-none"
                  />
                </div>
              </div>

              <div>
                <label class="mb-1 block text-xs font-medium">地址 <span class="text-red-500">*</span></label>
                <input
                  v-model="addrForm.address_line1"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm focus-visible:outline-none"
                  placeholder="完整地址"
                />
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="mb-1 block text-xs font-medium">城市</label>
                  <input v-model="addrForm.city" class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm focus-visible:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium">郵遞區號</label>
                  <input v-model="addrForm.postal_code" class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm focus-visible:outline-none" />
                </div>
                <div>
                  <label class="mb-1 block text-xs font-medium">國家</label>
                  <input v-model="addrForm.country" class="w-full rounded-md border border-input bg-transparent px-3 py-1.5 text-sm focus-visible:outline-none" />
                </div>
              </div>

              <div class="flex items-center gap-2">
                <input id="addr-default" v-model="addrForm.is_default" type="checkbox" class="h-4 w-4" />
                <label for="addr-default" class="text-sm">設為預設地址</label>
              </div>

              <p v-if="addrErrorMsg" class="rounded bg-red-50 px-3 py-2 text-sm text-red-600">{{ addrErrorMsg }}</p>

              <div class="flex gap-2 justify-end">
                <button class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted" @click="showAddrForm = false">取消</button>
                <button
                  class="rounded-md bg-primary px-3 py-1.5 text-sm text-primary-foreground disabled:opacity-50"
                  :disabled="addrSaving"
                  @click="saveAddress"
                >
                  {{ addrSaving ? '儲存中...' : '儲存地址' }}
                </button>
              </div>
            </div>

            <div v-if="!showAddrForm" class="flex justify-end">
              <button
                class="rounded-md border px-4 py-2 text-sm hover:bg-muted"
                @click="openAddrForm"
              >
                + 新增地址
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>
