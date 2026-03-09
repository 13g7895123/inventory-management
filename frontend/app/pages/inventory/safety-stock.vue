<script setup lang="ts">
import type { Item } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const itemStore = useItemStore()

// 載入所有商品（per_page 大一點取回全部）
const page    = ref(1)
const perPage = 50
const keyword = ref('')

async function load() {
  const params: Record<string, unknown> = { page: page.value, per_page: perPage }
  if (keyword.value) params.keyword = keyword.value
  await itemStore.fetchList(params)
}

onMounted(load)

function doSearch() {
  page.value = 1
  load()
}

const totalPages = computed(() => Math.ceil((itemStore.pagination?.total ?? 0) / perPage))

// ── 行內編輯 ───────────────────────────────────────────────────────
// key = item.id, value = { safety_stock, reorder_point, lead_time_days }
const editMap = reactive<Record<number, { safety_stock: number; reorder_point: number; lead_time_days: number }>>({})

function startEdit(item: Item) {
  editMap[item.id] = {
    safety_stock:   item.safety_stock,
    reorder_point:  item.reorder_point,
    lead_time_days: item.lead_time_days,
  }
}

function cancelEdit(id: number) {
  delete editMap[id]
}

const saving = ref<number | null>(null)
const saveError = ref<Record<number, string>>({})

async function saveEdit(item: Item) {
  const patch = editMap[item.id]
  if (!patch) return
  saving.value = item.id
  delete saveError.value[item.id]
  try {
    await itemStore.update(item.id, {
      // 只帶必要欄位，後端使用 PUT 需補齊其他欄位
      name:           item.name,
      code:           item.code,
      category_id:    item.category_id,
      unit_id:        item.unit_id,
      tax_type:       item.tax_type,
      is_active:      item.is_active,
      description:    item.description,
      lead_time_days: patch.lead_time_days,
      safety_stock:   patch.safety_stock,
      reorder_point:  patch.reorder_point,
    })
    // 更新列表中的值
    const idx = itemStore.items.findIndex(i => i.id === item.id)
    if (idx !== -1) {
      itemStore.items[idx] = {
        ...itemStore.items[idx],
        ...patch,
      }
    }
    delete editMap[item.id]
  } catch (e: unknown) {
    saveError.value[item.id] = e instanceof Error ? e.message : '儲存失敗'
  } finally {
    saving.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">安全庫存 / 再訂購點設定</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          設定各商品的安全庫存量與再訂購點，系統將在庫存低於安全庫存時產生低庫存警示。
        </p>
      </div>
    </div>

    <!-- 搜尋列 -->
    <div class="flex gap-3">
      <input
        v-model="keyword"
        type="text"
        placeholder="搜尋商品名稱 / 料號…"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-64"
        @keyup.enter="doSearch"
      />
      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="doSearch"
      >
        搜尋
      </button>
    </div>

    <!-- 說明卡 -->
    <div class="grid grid-cols-3 gap-4 text-sm">
      <div class="rounded-xl border bg-card p-4 space-y-1">
        <p class="font-medium">安全庫存（Safety Stock）</p>
        <p class="text-muted-foreground">庫存低於此值時觸發低庫存警示</p>
      </div>
      <div class="rounded-xl border bg-card p-4 space-y-1">
        <p class="font-medium">再訂購點（Reorder Point）</p>
        <p class="text-muted-foreground">庫存降至此值應發起補貨採購</p>
      </div>
      <div class="rounded-xl border bg-card p-4 space-y-1">
        <p class="font-medium">前置時間（Lead Time）</p>
        <p class="text-muted-foreground">估計採購到到貨所需天數</p>
      </div>
    </div>

    <!-- 表格 -->
    <div class="rounded-xl border bg-card overflow-hidden">
      <div v-if="itemStore.loading" class="py-16 text-center text-muted-foreground">載入中…</div>

      <table v-else class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">料號</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">商品名稱</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">安全庫存</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">再訂購點</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">前置天數</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="itemStore.items.length === 0">
            <td colspan="6" class="py-16 text-center text-muted-foreground">查無商品</td>
          </tr>
          <tr
            v-for="item in itemStore.items"
            :key="item.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ item.code }}</td>
            <td class="px-4 py-3 font-medium">{{ item.name }}</td>

            <!-- 安全庫存 -->
            <td class="px-4 py-3 text-right">
              <input
                v-if="editMap[item.id]"
                v-model.number="editMap[item.id].safety_stock"
                type="number"
                min="0"
                class="w-24 rounded-md border border-input bg-transparent px-2 py-1 text-sm text-right focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              />
              <span v-else>{{ item.safety_stock }}</span>
            </td>

            <!-- 再訂購點 -->
            <td class="px-4 py-3 text-right">
              <input
                v-if="editMap[item.id]"
                v-model.number="editMap[item.id].reorder_point"
                type="number"
                min="0"
                class="w-24 rounded-md border border-input bg-transparent px-2 py-1 text-sm text-right focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              />
              <span v-else>{{ item.reorder_point }}</span>
            </td>

            <!-- 前置天數 -->
            <td class="px-4 py-3 text-right">
              <input
                v-if="editMap[item.id]"
                v-model.number="editMap[item.id].lead_time_days"
                type="number"
                min="0"
                class="w-20 rounded-md border border-input bg-transparent px-2 py-1 text-sm text-right focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
              />
              <span v-else>{{ item.lead_time_days }} 天</span>
            </td>

            <!-- 操作 -->
            <td class="px-4 py-3 text-right space-x-2">
              <template v-if="editMap[item.id]">
                <button
                  :disabled="saving === item.id"
                  class="text-sm text-primary hover:underline disabled:opacity-50"
                  @click="saveEdit(item)"
                >
                  {{ saving === item.id ? '儲存…' : '儲存' }}
                </button>
                <button
                  class="text-sm text-muted-foreground hover:underline"
                  @click="cancelEdit(item.id)"
                >
                  取消
                </button>
              </template>
              <button
                v-else
                class="text-sm text-primary hover:underline"
                @click="startEdit(item)"
              >
                編輯
              </button>
            </td>
          </tr>

          <!-- 行內錯誤訊息 -->
          <template v-for="item in itemStore.items" :key="`err-${item.id}`">
            <tr v-if="saveError[item.id]">
              <td colspan="6" class="px-4 py-1">
                <p class="text-xs text-destructive">{{ saveError[item.id] }}</p>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div v-if="totalPages > 1" class="flex items-center gap-2 justify-end">
      <button
        :disabled="page <= 1"
        class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted disabled:opacity-40 transition-colors"
        @click="page--; load()"
      >
        ‹ 上一頁
      </button>
      <span class="text-sm text-muted-foreground">{{ page }} / {{ totalPages }}</span>
      <button
        :disabled="page >= totalPages"
        class="rounded-md border px-3 py-1.5 text-sm hover:bg-muted disabled:opacity-40 transition-colors"
        @click="page++; load()"
      >
        下一頁 ›
      </button>
    </div>
  </div>
</template>
