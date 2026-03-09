<script setup lang="ts">
import type { PurchaseOrderStatus } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const route    = useRoute()
const router   = useRouter()
const poStore  = usePurchaseOrderStore()
const id       = computed(() => Number(route.params.id))

onMounted(() => poStore.fetchOne(id.value))

// ── 狀態顯示 ─────────────────────────────────────────────────────────
const statusLabels: Record<string, string> = {
  draft:     '草稿',
  pending:   '待審核',
  approved:  '已核准',
  partial:   '部分到貨',
  received:  '全部到貨',
  cancelled: '已取消',
}

const statusClasses: Record<string, string> = {
  draft:     'bg-gray-100 text-gray-700',
  pending:   'bg-yellow-100 text-yellow-700',
  approved:  'bg-blue-100 text-blue-700',
  partial:   'bg-purple-100 text-purple-700',
  received:  'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
}

function formatDate(dt: string | null) {
  if (!dt) return '-'
  return dt.substring(0, 10)
}

function formatDateTime(dt: string | null) {
  if (!dt) return '-'
  return dt.substring(0, 16).replace('T', ' ')
}

function fmt(val: number | string | null) {
  if (val === null || val === undefined) return '-'
  return Number(val).toLocaleString('zh-TW', { minimumFractionDigits: 0 })
}

// ── 操作 ─────────────────────────────────────────────────────────────
const actionError = ref('')
const confirmCancel = ref(false)

async function doSubmit() {
  actionError.value = ''
  try {
    await poStore.submit(id.value)
  } catch (e: unknown) {
    actionError.value = e instanceof Error ? e.message : '提交失敗'
  }
}

async function doApprove() {
  actionError.value = ''
  try {
    await poStore.approve(id.value)
  } catch (e: unknown) {
    actionError.value = e instanceof Error ? e.message : '核准失敗'
  }
}

async function doCancel() {
  actionError.value = ''
  try {
    await poStore.cancel(id.value)
    confirmCancel.value = false
  } catch (e: unknown) {
    actionError.value = e instanceof Error ? e.message : '取消失敗'
  }
}

function openPdf() {
  const config  = useRuntimeConfig()
  const baseUrl = (config.public as Record<string, string>).apiBase ?? '/api/v1'
  const token   = localStorage.getItem('access_token') ?? ''
  // 以新分頁開啟 PDF（後端需驗證 Authorization）
  const url = `${baseUrl}/purchase-orders/${id.value}/pdf`
  const win = window.open('', '_blank')
  if (win) {
    fetch(url, { headers: { Authorization: `Bearer ${token}` } })
      .then(r => r.blob())
      .then(blob => {
        const objUrl = URL.createObjectURL(blob)
        win.location.href = objUrl
      })
      .catch(() => { win.close(); actionError.value = '無法下載 PDF' })
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/purchase/orders" class="hover:text-foreground">採購單</NuxtLink>
      <span>/</span>
      <span class="text-foreground">{{ poStore.current?.po_number ?? '...'}}</span>
    </div>

    <!-- 載入中 -->
    <div v-if="poStore.loading" class="flex justify-center py-24">
      <div class="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>

    <template v-else-if="poStore.current">
      <!-- 標題 + 狀態 + 操作 -->
      <div class="flex flex-wrap items-start gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-semibold">{{ poStore.current.po_number }}</h1>
            <span
              class="inline-flex items-center rounded-full px-2.5 py-0.5 text-sm font-medium"
              :class="statusClasses[poStore.current.status]"
            >
              {{ statusLabels[poStore.current.status] ?? poStore.current.status }}
            </span>
          </div>
        </div>

        <!-- 操作按鈕 -->
        <div class="flex flex-wrap gap-2">
          <!-- draft → 提交審核 / 取消 -->
          <template v-if="poStore.current.status === 'draft'">
            <button
              :disabled="poStore.submitting"
              class="rounded-md bg-yellow-500 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-600 disabled:opacity-60 transition-colors"
              @click="doSubmit"
            >
              {{ poStore.submitting ? '提交中…' : '提交審核' }}
            </button>
            <button
              class="rounded-md border border-destructive px-4 py-2 text-sm text-destructive hover:bg-destructive/5 transition-colors"
              @click="confirmCancel = true"
            >
              取消採購單
            </button>
          </template>

          <!-- pending → 核准 / 取消 -->
          <template v-else-if="poStore.current.status === 'pending'">
            <button
              :disabled="poStore.approving"
              class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60 transition-colors"
              @click="doApprove"
            >
              {{ poStore.approving ? '核准中…' : '核准' }}
            </button>
            <button
              class="rounded-md border border-destructive px-4 py-2 text-sm text-destructive hover:bg-destructive/5 transition-colors"
              @click="confirmCancel = true"
            >
              取消採購單
            </button>
          </template>

          <!-- approved / partial → 進貨驗收 + 列印PDF -->
          <template v-else-if="poStore.current.status === 'approved' || poStore.current.status === 'partial'">
            <NuxtLink
              :to="`/purchase/orders/${id}/receive`"
              class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
            >
              進貨驗收
            </NuxtLink>
            <button
              class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
              @click="openPdf"
            >
              列印 PDF
            </button>
          </template>

          <!-- received → 列印 PDF -->
          <template v-else-if="poStore.current.status === 'received'">
            <button
              class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
              @click="openPdf"
            >
              列印 PDF
            </button>
          </template>
        </div>
      </div>

      <!-- 動作錯誤 -->
      <p
        v-if="actionError"
        class="rounded border border-destructive bg-destructive/5 px-3 py-2 text-sm text-destructive"
      >
        {{ actionError }}
      </p>

      <!-- 取消確認列 -->
      <div
        v-if="confirmCancel"
        class="flex items-center gap-3 rounded-md border border-destructive/50 bg-destructive/5 px-4 py-3 text-sm"
      >
        <span>確定要取消此採購單？此操作無法復原。</span>
        <button
          :disabled="poStore.cancelling"
          class="rounded-md bg-destructive px-3 py-1.5 text-xs text-white hover:bg-destructive/90 disabled:opacity-60"
          @click="doCancel"
        >
          {{ poStore.cancelling ? '取消中…' : '確定取消' }}
        </button>
        <button
          class="text-xs text-muted-foreground hover:underline"
          @click="confirmCancel = false"
        >
          返回
        </button>
      </div>

      <!-- 採購單基本資訊 -->
      <div class="rounded-lg border p-6">
        <h2 class="mb-4 font-medium">採購資訊</h2>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
          <div>
            <dt class="text-muted-foreground">供應商</dt>
            <dd class="font-medium">{{ poStore.current.supplier_name ?? '-' }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">倉庫 ID</dt>
            <dd>{{ poStore.current.warehouse_id }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">預計到貨</dt>
            <dd>{{ formatDate(poStore.current.expected_date) }}</dd>
          </div>
          <div>
            <dt class="text-muted-foreground">建立日期</dt>
            <dd>{{ formatDate(poStore.current.created_at) }}</dd>
          </div>
          <div v-if="poStore.current.approved_at">
            <dt class="text-muted-foreground">核准時間</dt>
            <dd>{{ formatDateTime(poStore.current.approved_at) }}</dd>
          </div>
          <div v-if="poStore.current.notes" class="col-span-2 sm:col-span-3">
            <dt class="text-muted-foreground">備註</dt>
            <dd class="whitespace-pre-line">{{ poStore.current.notes }}</dd>
          </div>
        </dl>
      </div>

      <!-- 明細表 -->
      <div class="rounded-lg border overflow-hidden">
        <div class="flex items-center gap-2 px-6 py-4 border-b bg-muted/20">
          <h2 class="font-medium">訂購明細</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-muted/50">
            <tr>
              <th class="px-4 py-3 text-left font-medium">SKU</th>
              <th class="px-4 py-3 text-left font-medium">品名</th>
              <th class="px-4 py-3 text-right font-medium">訂購數</th>
              <th class="px-4 py-3 text-right font-medium">已驗收</th>
              <th class="px-4 py-3 text-right font-medium">單價</th>
              <th class="px-4 py-3 text-right font-medium">小計</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr
              v-for="line in poStore.current.lines ?? []"
              :key="line.id"
              class="hover:bg-muted/30 transition-colors"
            >
              <td class="px-4 py-3 font-mono text-xs">{{ line.sku_code ?? line.sku_id }}</td>
              <td class="px-4 py-3">{{ line.item_name ?? '-' }}</td>
              <td class="px-4 py-3 text-right">{{ line.ordered_qty }}</td>
              <td class="px-4 py-3 text-right">
                <span
                  :class="{
                    'text-green-600 font-medium': line.received_qty >= line.ordered_qty,
                    'text-yellow-600': line.received_qty > 0 && line.received_qty < line.ordered_qty,
                  }"
                >
                  {{ line.received_qty }}
                </span>
              </td>
              <td class="px-4 py-3 text-right">{{ fmt(line.unit_price) }}</td>
              <td class="px-4 py-3 text-right font-medium">{{ fmt(line.line_total) }}</td>
            </tr>
          </tbody>
        </table>

        <!-- 金額合計 -->
        <div class="flex justify-end px-6 py-4 border-t">
          <div class="w-56 space-y-1 text-sm">
            <div class="flex justify-between">
              <span class="text-muted-foreground">小計</span>
              <span>{{ fmt(poStore.current.subtotal) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-muted-foreground">稅額 ({{ poStore.current.tax_rate }}%)</span>
              <span>{{ fmt(poStore.current.tax_amount) }}</span>
            </div>
            <div class="flex justify-between font-semibold border-t pt-1 mt-1">
              <span>總計</span>
              <span>{{ fmt(poStore.current.total_amount) }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- 找不到 -->
    <div v-else class="text-center py-24 text-muted-foreground">
      找不到採購單
    </div>
  </div>
</template>
