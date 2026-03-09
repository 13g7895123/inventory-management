<script setup lang="ts" generic="T extends Record<string, unknown>">
import {
  FlexRender,
  getCoreRowModel,
  getSortedRowModel,
  useVueTable,
  type ColumnDef,
  type SortingState,
} from '@tanstack/vue-table'
import type { Pagination } from '~/app/types/api'

// ── Props ────────────────────────────────────────────────────────────

const props = withDefaults(
  defineProps<{
    /** TanStack Column 定義 */
    columns: ColumnDef<T>[]
    /** 資料陣列 */
    data: T[]
    /** 分頁資訊（若不傳則不顯示分頁列） */
    pagination?: Pagination
    /** 載入中狀態 */
    loading?: boolean
    /** 是否顯示搜尋列 */
    searchable?: boolean
    /** 搜尋輸入提示文字 */
    searchPlaceholder?: string
  }>(),
  {
    loading:           false,
    searchable:        true,
    searchPlaceholder: '搜尋...',
  }
)

// ── Emits ────────────────────────────────────────────────────────────

const emit = defineEmits<{
  (e: 'page-change', page: number): void
  (e: 'search', query: string): void
}>()

// ── 排序狀態（Client-side sort） ──────────────────────────────────────

const sorting = ref<SortingState>([])

// ── 搜尋 ─────────────────────────────────────────────────────────────

const searchQuery = ref('')

function doSearch() {
  emit('search', searchQuery.value.trim())
}

// ── TanStack Table 實例 ───────────────────────────────────────────────

const table = useVueTable({
  get data()    { return props.data },
  get columns() { return props.columns },
  getCoreRowModel:    getCoreRowModel(),
  getSortedRowModel:  getSortedRowModel(),
  manualPagination:   true,
  state: {
    get sorting() { return sorting.value },
  },
  onSortingChange: (updater) => {
    sorting.value = typeof updater === 'function'
      ? updater(sorting.value)
      : updater
  },
})
</script>

<template>
  <div class="space-y-3">

    <!-- ── 搜尋列 ─────────────────────────────────────────────────── -->
    <div v-if="searchable" class="flex items-center gap-2">
      <input
        v-model="searchQuery"
        type="search"
        :placeholder="searchPlaceholder"
        class="flex h-8 w-64 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        @keyup.enter="doSearch"
      />
      <button
        class="inline-flex h-8 items-center justify-center rounded-md bg-primary px-3 text-xs font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="doSearch"
      >
        搜尋
      </button>
    </div>

    <!-- ── 表格 ───────────────────────────────────────────────────── -->
    <div class="rounded-md border overflow-x-auto">
      <table class="w-full caption-bottom text-sm">

        <!-- 表頭 -->
        <thead class="[&_tr]:border-b">
          <tr
            v-for="headerGroup in table.getHeaderGroups()"
            :key="headerGroup.id"
          >
            <th
              v-for="header in headerGroup.headers"
              :key="header.id"
              class="h-10 px-4 text-left align-middle font-medium text-muted-foreground whitespace-nowrap"
              :class="{ 'cursor-pointer select-none hover:text-foreground': header.column.getCanSort() }"
              @click="header.column.getToggleSortingHandler()?.($event)"
            >
              <div class="flex items-center gap-1">
                <FlexRender
                  v-if="!header.isPlaceholder"
                  :render="header.column.columnDef.header"
                  :props="header.getContext()"
                />
                <span v-if="header.column.getIsSorted() === 'asc'"  class="text-xs">↑</span>
                <span v-else-if="header.column.getIsSorted() === 'desc'" class="text-xs">↓</span>
                <span v-else-if="header.column.getCanSort()" class="text-xs opacity-30">⇅</span>
              </div>
            </th>
          </tr>
        </thead>

        <!-- 表身 -->
        <tbody class="[&_tr:last-child]:border-0">

          <!-- 載入中 -->
          <tr v-if="loading">
            <td
              :colspan="columns.length"
              class="h-24 text-center text-muted-foreground"
            >
              <span class="animate-pulse">載入中...</span>
            </td>
          </tr>

          <!-- 無資料 -->
          <tr v-else-if="table.getRowModel().rows.length === 0">
            <td
              :colspan="columns.length"
              class="h-24 text-center text-muted-foreground"
            >
              暫無資料
            </td>
          </tr>

          <!-- 資料列 -->
          <template v-else>
            <tr
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted"
            >
              <td
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                class="p-4 align-middle"
              >
                <FlexRender
                  :render="cell.column.columnDef.cell"
                  :props="cell.getContext()"
                />
              </td>
            </tr>
          </template>

        </tbody>
      </table>
    </div>

    <!-- ── 分頁列 ─────────────────────────────────────────────────── -->
    <div
      v-if="pagination"
      class="flex items-center justify-between"
    >
      <p class="text-xs text-muted-foreground">
        共 <span class="font-medium text-foreground">{{ pagination.total }}</span> 筆 ·
        第 {{ pagination.current_page }} / {{ pagination.total_pages }} 頁
      </p>

      <div class="flex items-center gap-1">
        <button
          class="inline-flex h-8 items-center justify-center rounded-md border px-3 text-xs transition-colors hover:bg-accent disabled:opacity-40 disabled:pointer-events-none"
          :disabled="pagination.current_page <= 1"
          @click="emit('page-change', pagination.current_page - 1)"
        >
          上一頁
        </button>
        <button
          class="inline-flex h-8 items-center justify-center rounded-md border px-3 text-xs transition-colors hover:bg-accent disabled:opacity-40 disabled:pointer-events-none"
          :disabled="pagination.current_page >= pagination.total_pages"
          @click="emit('page-change', pagination.current_page + 1)"
        >
          下一頁
        </button>
      </div>
    </div>

  </div>
</template>
