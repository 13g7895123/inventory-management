<script setup lang="ts">
definePageMeta({ layout: 'default' })

const config    = useRuntimeConfig()
const authStore = useAuthStore()
const router    = useRouter()

// ── 狀態 ─────────────────────────────────────────────────────────────
type Status = 'idle' | 'uploading' | 'success' | 'error'

const status        = ref<Status>('idle')
const selectedFile  = ref<File | null>(null)
const isDragging    = ref(false)
const progress      = ref(0)  // 0-100
const errorMsg      = ref('')
const inputRef      = ref<HTMLInputElement | null>(null)

interface ImportResult {
  imported: number
  failed:   number
  errors:   Array<{ row: number; message: string }>
}
const result = ref<ImportResult | null>(null)

// ── 檔案選取 ──────────────────────────────────────────────────────────
const ALLOWED_EXTS  = ['.csv', '.xlsx', '.xls']
const MAX_FILE_SIZE = 10 * 1024 * 1024  // 10 MB

function validateFile(file: File): string | null {
  const ext = '.' + file.name.split('.').pop()?.toLowerCase()
  if (!ALLOWED_EXTS.includes(ext)) {
    return `僅支援 ${ALLOWED_EXTS.join('、')} 格式`
  }
  if (file.size > MAX_FILE_SIZE) {
    return '檔案大小不可超過 10 MB'
  }
  return null
}

function pickFile(file: File) {
  const err = validateFile(file)
  if (err) {
    errorMsg.value = err
    return
  }
  errorMsg.value = ''
  selectedFile.value = file
  status.value       = 'idle'
  result.value       = null
}

function onFileChange(evt: Event) {
  const file = (evt.target as HTMLInputElement).files?.[0]
  if (file) pickFile(file)
}

function onDrop(evt: DragEvent) {
  isDragging.value = false
  const file = evt.dataTransfer?.files?.[0]
  if (file) pickFile(file)
}

function clearFile() {
  selectedFile.value = null
  result.value       = null
  status.value       = 'idle'
  errorMsg.value     = ''
  if (inputRef.value) inputRef.value.value = ''
}

// ── 上傳 ─────────────────────────────────────────────────────────────
async function doImport() {
  if (!selectedFile.value) return

  status.value   = 'uploading'
  progress.value = 0
  result.value   = null
  errorMsg.value = ''

  const form = new FormData()
  form.append('file', selectedFile.value)

  try {
    // 模擬進度（XHR 上傳進度事件）
    const progressInterval = setInterval(() => {
      if (progress.value < 80) progress.value += 10
    }, 200)

    const res = await $fetch<{
      success: boolean
      message: string
      data: ImportResult
    }>(`${config.public.apiBase}/items/import`, {
      method:  'POST',
      headers: { Authorization: `Bearer ${authStore.accessToken}` },
      body:    form,
    })

    clearInterval(progressInterval)
    progress.value = 100

    if (res.success && res.data) {
      result.value = res.data
      status.value = 'success'
    } else {
      throw new Error(res.message || '匯入失敗')
    }
  } catch (e: unknown) {
    status.value   = 'error'
    errorMsg.value = e instanceof Error ? e.message : '匯入失敗，請確認檔案格式是否正確'
  }
}

// ── 範本下載 ──────────────────────────────────────────────────────────
function downloadTemplate() {
  const headers = ['code', 'name', 'category_id', 'unit_id', 'description',
                   'tax_type', 'reorder_point', 'safety_stock', 'lead_time_days',
                   'sku_code', 'cost_price', 'selling_price', 'attributes']
  const example  = ['ITEM-001', '螺絲 M3×10', '1', '1', '標準螺絲',
                    'taxable', '100', '50', '7',
                    'ITEM-001-DEFAULT', '2.5', '5.0', '']

  const csv = [headers.join(','), example.join(',')].join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = '商品匯入範本.csv'
  a.click()
  URL.revokeObjectURL(url)
}

const fileSize = computed(() => {
  if (!selectedFile.value) return ''
  const size = selectedFile.value.size
  if (size < 1024) return `${size} B`
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
  return `${(size / 1024 / 1024).toFixed(1)} MB`
})
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/items" class="hover:text-foreground">商品管理</NuxtLink>
      <span>›</span>
      <span class="text-foreground">批次匯入</span>
    </div>

    <!-- 標題 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">批次匯入商品</h1>
        <p class="mt-1 text-sm text-muted-foreground">支援 CSV、Excel (.xlsx) 格式，單次最多 2,000 筆</p>
      </div>
      <button
        class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
        @click="downloadTemplate"
      >
        下載範本
      </button>
    </div>

    <!-- 欄位說明 -->
    <div class="rounded-xl border bg-muted/20 p-4">
      <p class="text-sm font-medium mb-2">欄位對應說明</p>
      <div class="grid grid-cols-2 gap-x-8 gap-y-1 text-xs text-muted-foreground">
        <div><code class="text-foreground">code</code> — 料號（必填，唯一）</div>
        <div><code class="text-foreground">name</code> — 商品名稱（必填）</div>
        <div><code class="text-foreground">category_id</code> — 分類 ID（必填）</div>
        <div><code class="text-foreground">unit_id</code> — 單位 ID（必填）</div>
        <div><code class="text-foreground">tax_type</code> — taxable/zero/exempt</div>
        <div><code class="text-foreground">reorder_point</code> — 再訂購點</div>
        <div><code class="text-foreground">safety_stock</code> — 安全庫存量</div>
        <div><code class="text-foreground">lead_time_days</code> — 前置天數</div>
        <div><code class="text-foreground">sku_code</code> — SKU 代碼</div>
        <div><code class="text-foreground">cost_price</code> — 成本價</div>
        <div><code class="text-foreground">selling_price</code> — 售價</div>
        <div><code class="text-foreground">attributes</code> — JSON 格式屬性</div>
      </div>
    </div>

    <!-- 拖曳上傳區 -->
    <div
      :class="[
        'flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-10 transition-colors cursor-pointer',
        isDragging
          ? 'border-primary bg-primary/5'
          : 'border-input hover:border-primary/50 hover:bg-muted/20',
        status === 'uploading' ? 'pointer-events-none opacity-60' : '',
      ]"
      @click="inputRef?.click()"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="onDrop"
    >
      <template v-if="!selectedFile">
        <div class="mb-3 text-3xl">📂</div>
        <p class="text-sm font-medium">{{ isDragging ? '放開以選取檔案' : '拖曳檔案至此，或點擊選取' }}</p>
        <p class="text-xs text-muted-foreground mt-1">CSV、XLSX、XLS；最大 10 MB</p>
      </template>

      <template v-else>
        <div class="mb-2 text-3xl">
          {{ selectedFile.name.endsWith('.csv') ? '📄' : '📊' }}
        </div>
        <p class="text-sm font-medium">{{ selectedFile.name }}</p>
        <p class="text-xs text-muted-foreground mt-0.5">{{ fileSize }}</p>
        <button
          type="button"
          class="mt-3 text-xs text-destructive hover:underline"
          @click.stop="clearFile"
        >
          移除
        </button>
      </template>

      <input
        ref="inputRef"
        type="file"
        accept=".csv,.xlsx,.xls"
        class="sr-only"
        @change="onFileChange"
      />
    </div>

    <!-- 進度條（上傳中） -->
    <Transition name="fade">
      <div v-if="status === 'uploading'" class="space-y-1.5">
        <div class="flex items-center justify-between text-xs">
          <span class="text-muted-foreground">匯入中，請稍候…</span>
          <span class="tabular-nums">{{ progress }}%</span>
        </div>
        <div class="h-2 rounded-full bg-muted overflow-hidden">
          <div
            class="h-full rounded-full bg-primary transition-all duration-300"
            :style="{ width: `${progress}%` }"
          />
        </div>
      </div>
    </Transition>

    <!-- 錯誤訊息 -->
    <Transition name="fade">
      <div
        v-if="status === 'error' || errorMsg"
        class="rounded-md border border-destructive bg-destructive/5 px-4 py-3 text-sm text-destructive"
      >
        {{ errorMsg }}
      </div>
    </Transition>

    <!-- 成功結果 -->
    <Transition name="fade">
      <div
        v-if="status === 'success' && result"
        class="rounded-xl border border-green-200 bg-green-50 p-5 space-y-3"
      >
        <div class="flex items-center gap-2 text-green-700 font-medium">
          <span>✓</span>
          <span>匯入完成</span>
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div class="rounded-lg bg-white border border-green-100 p-3 text-center">
            <p class="text-2xl font-bold text-green-700">{{ result.imported }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">成功匯入</p>
          </div>
          <div class="rounded-lg bg-white border border-red-100 p-3 text-center">
            <p class="text-2xl font-bold" :class="result.failed > 0 ? 'text-red-600' : 'text-gray-400'">
              {{ result.failed }}
            </p>
            <p class="text-xs text-muted-foreground mt-0.5">失敗筆數</p>
          </div>
        </div>

        <!-- 錯誤明細 -->
        <div v-if="result.errors.length > 0" class="space-y-2">
          <p class="text-sm font-medium text-red-700">錯誤明細：</p>
          <div class="max-h-48 overflow-y-auto rounded-lg border border-red-100 bg-white divide-y text-xs">
            <div
              v-for="err in result.errors"
              :key="err.row"
              class="px-3 py-2 flex items-start gap-2"
            >
              <span class="text-muted-foreground shrink-0">第 {{ err.row }} 列</span>
              <span class="text-red-700">{{ err.message }}</span>
            </div>
          </div>
        </div>

        <div class="flex gap-2 pt-1">
          <button
            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
            @click="router.push('/items')"
          >
            前往商品列表
          </button>
          <button
            class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
            @click="clearFile"
          >
            繼續匯入
          </button>
        </div>
      </div>
    </Transition>

    <!-- 操作按鈕 -->
    <div v-if="status !== 'success'" class="flex justify-end gap-3">
      <NuxtLink
        to="/items"
        class="rounded-md border px-5 py-2 text-sm hover:bg-muted transition-colors"
      >
        取消
      </NuxtLink>
      <button
        :disabled="!selectedFile || status === 'uploading'"
        class="rounded-md bg-primary px-5 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
        @click="doImport"
      >
        {{ status === 'uploading' ? '匯入中…' : '開始匯入' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from,
.fade-leave-to { opacity: 0; }
</style>
