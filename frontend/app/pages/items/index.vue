<script setup lang="ts">
import type { Item } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const itemStore     = useItemStore()
const categoryStore = useCategoryStore()
const router        = useRouter()

// ── 查詢參數 ──────────────────────────────────────────────────────────
const keyword    = ref('')
const categoryId = ref<string>('')
const isActive   = ref<string>('')
const page       = ref(1)
const perPage    = 20

// ── 載入 ─────────────────────────────────────────────────────────────
async function load() {
  const params: Record<string, unknown> = {
    page,
    per_page: perPage,
  }
  if (keyword.value)    params.keyword     = keyword.value
  if (categoryId.value) params.category_id = categoryId.value
  if (isActive.value !== '') params.is_active = isActive.value

  await itemStore.fetchList(params)
}

onMounted(async () => {
  await Promise.all([categoryStore.fetchAll(), load()])
})

// ── 搜尋／篩選 ─────────────────────────────────────────────────────────
function doSearch() {
  page.value = 1
  load()
}

function onPageChange(p: number) {
  page.value = p
  load()
}

// ── 停用/啟用 ─────────────────────────────────────────────────────────
const toggleLoading = ref<number | null>(null)

async function toggleActive(item: Item) {
  toggleLoading.value = item.id
  try {
    await itemStore.toggleActive(item.id, !item.is_active)
  } finally {
    toggleLoading.value = null
  }
}

// ── 刪除 ─────────────────────────────────────────────────────────────
const deleteConfirm = ref<number | null>(null)

async function confirmDelete(id: number) {
  try {
    await itemStore.remove(id)
  } catch (e: unknown) {
    alert(e instanceof Error ? e.message : '刪除失敗')
  } finally {
    deleteConfirm.value = null
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">商品管理</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          共 {{ itemStore.pagination?.total ?? 0 }} 筆商品
        </p>
      </div>
      <div class="flex gap-2">
        <NuxtLink
          to="/items/import"
          class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
        >
          批次匯入
        </NuxtLink>
        <NuxtLink
          to="/items/create"
          class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        >
          + 新增商品
        </NuxtLink>
      </div>
    </div>

    <!-- 篩選列 -->
    <div class="flex flex-wrap gap-3">
      <!-- 關鍵字搜尋 -->
      <div class="flex gap-1.5">
        <input
          v-model="keyword"
          type="text"
          placeholder="搜尋料號/名稱…"
          class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring w-52"
          @keyup.enter="doSearch"
        />
      </div>

      <!-- 分類篩選 -->
      <select
        v-model="categoryId"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @change="doSearch"
      >
        <option value="">全部分類</option>
        <option
          v-for="cat in categoryStore.categories"
          :key="cat.id"
          :value="String(cat.id)"
        >
          {{ cat.name }}
        </option>
      </select>

      <!-- 狀態篩選 -->
      <select
        v-model="isActive"
        class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @change="doSearch"
      >
        <option value="">全部狀態</option>
        <option value="1">啟用</option>
        <option value="0">停用</option>
      </select>

      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="doSearch"
      >
        搜尋
      </button>
    </div>

    <!-- 商品表格 -->
    <div class="rounded-xl border bg-card overflow-hidden">
      <!-- 載入中 -->
      <div v-if="itemStore.loading" class="py-16 text-center text-muted-foreground">
        載入中…
      </div>

      <table v-else class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">料號</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">商品名稱</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">分類</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">單位</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">安全庫存</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">狀態</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="itemStore.items.length === 0">
            <td colspan="7" class="py-16 text-center text-muted-foreground">
              查無符合條件的商品
            </td>
          </tr>
          <tr
            v-for="item in itemStore.items"
            :key="item.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ item.code }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <img
                  v-if="item.image_path"
                  :src="item.image_path"
                  class="h-8 w-8 rounded object-cover border"
                  alt=""
                />
                <div
                  v-else
                  class="h-8 w-8 rounded border bg-muted/30 flex items-center justify-center text-xs text-muted-foreground"
                >
                  無圖
                </div>
                <span class="font-medium">{{ item.name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-muted-foreground">{{ item.category_name || '—' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ item.unit_name || '—' }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ item.safety_stock }}</td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                  item.is_active
                    ? 'bg-green-100 text-green-700'
                    : 'bg-gray-100 text-gray-500',
                ]"
              >
                {{ item.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <NuxtLink
                  :to="`/items/${item.id}/edit`"
                  class="text-sm text-blue-600 hover:underline"
                >
                  編輯
                </NuxtLink>
                <NuxtLink
                  :to="`/items/${item.id}/skus`"
                  class="text-sm text-muted-foreground hover:underline"
                >
                  SKU
                </NuxtLink>
                <button
                  class="text-sm hover:underline"
                  :class="item.is_active ? 'text-amber-600' : 'text-green-600'"
                  :disabled="toggleLoading === item.id"
                  @click="toggleActive(item)"
                >
                  {{ item.is_active ? '停用' : '啟用' }}
                </button>
                <button
                  class="text-sm text-destructive hover:underline"
                  @click="deleteConfirm = item.id"
                >
                  刪除
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分頁 -->
    <div
      v-if="itemStore.pagination && itemStore.pagination.total_pages > 1"
      class="flex items-center justify-center gap-1"
    >
      <button
        v-for="p in itemStore.pagination.total_pages"
        :key="p"
        :class="[
          'h-8 w-8 rounded-md text-sm transition-colors',
          p === itemStore.pagination.current_page
            ? 'bg-primary text-primary-foreground'
            : 'hover:bg-muted',
        ]"
        @click="onPageChange(p)"
      >
        {{ p }}
      </button>
    </div>

    <!-- 刪除確認 -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="deleteConfirm !== null"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        >
          <div class="w-full max-w-sm rounded-xl bg-card border p-6 shadow-xl">
            <h3 class="text-lg font-semibold mb-2">確認刪除</h3>
            <p class="text-sm text-muted-foreground mb-4">刪除後無法復原，確定要刪除此商品嗎？</p>
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
