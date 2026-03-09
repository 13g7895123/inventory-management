<script setup lang="ts">
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Category } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const categoryStore = useCategoryStore()

onMounted(() => categoryStore.fetchAll())

// ── Dialog ────────────────────────────────────────────────────────────
const dialogOpen    = ref(false)
const editingId     = ref<number | null>(null)
const deleteConfirm = ref<number | null>(null)
const errorMsg      = ref('')

// ── 表單 ─────────────────────────────────────────────────────────────
const schema = toTypedSchema(
  z.object({
    name:        z.string().min(1, '請輸入分類名稱').max(100),
    parent_id:   z.number().nullable().optional(),
    description: z.string().max(500).optional(),
    sort_order:  z.number().int().min(0),
    is_active:   z.boolean(),
  })
)

const { handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: { name: '', parent_id: null, description: '', sort_order: 0, is_active: true },
})

const { value: name }        = useField<string>('name')
const { value: parent_id }   = useField<number | null>('parent_id')
const { value: description } = useField<string>('description')
const { value: sort_order }  = useField<number>('sort_order')
const { value: is_active }   = useField<boolean>('is_active')

// 排除自身及其子孫，避免循環引用
const parentCandidates = computed(() => {
  if (!editingId.value) return categoryStore.categories
  const excluded = new Set<number>([editingId.value])

  // 收集所有後代
  const queue = [editingId.value]
  while (queue.length) {
    const id = queue.shift()!
    categoryStore.categories
      .filter((c) => c.parent_id === id)
      .forEach((c) => {
        excluded.add(c.id)
        queue.push(c.id)
      })
  }

  return categoryStore.categories.filter((c) => !excluded.has(c.id))
})

function openCreate() {
  editingId.value = null
  resetForm()
  errorMsg.value  = ''
  dialogOpen.value = true
}

function openEdit(cat: Category) {
  editingId.value = cat.id
  setValues({
    name:        cat.name,
    parent_id:   cat.parent_id,
    description: cat.description ?? '',
    sort_order:  cat.sort_order,
    is_active:   cat.is_active,
  })
  errorMsg.value  = ''
  dialogOpen.value = true
}

const onSubmit = handleSubmit(async (values) => {
  errorMsg.value = ''
  try {
    const payload = {
      ...values,
      parent_id: values.parent_id || null,
    }
    if (editingId.value) {
      await categoryStore.update(editingId.value, payload)
    } else {
      await categoryStore.create(payload)
    }
    dialogOpen.value = false
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '儲存失敗'
  }
})

async function confirmDelete(id: number) {
  try {
    await categoryStore.remove(id)
  } catch (e: unknown) {
    alert(e instanceof Error ? e.message : '刪除失敗（可能有商品使用此分類）')
  } finally {
    deleteConfirm.value = null
  }
}

// ── 樹狀渲染 ─────────────────────────────────────────────────────────
function indentStyle(cat: Category) {
  const depth = getDepth(cat)
  return { paddingLeft: `${depth * 20 + 12}px` }
}

function getDepth(cat: Category): number {
  if (!cat.parent_id) return 0
  const parent = categoryStore.categories.find((c) => c.id === cat.parent_id)
  return parent ? getDepth(parent) + 1 : 0
}

/** 將分類依樹狀順序排列（DFS pre-order）*/
const orderedCategories = computed(() => {
  const result: Category[] = []
  const visit = (parentId: number | null) => {
    categoryStore.categories
      .filter((c) => c.parent_id === parentId)
      .sort((a, b) => a.sort_order - b.sort_order || a.name.localeCompare(b.name))
      .forEach((c) => {
        result.push(c)
        visit(c.id)
      })
  }
  visit(null)
  return result
})
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">商品分類管理</h1>
        <p class="mt-1 text-sm text-muted-foreground">支援多層樹狀分類結構</p>
      </div>
      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="openCreate"
      >
        + 新增分類
      </button>
    </div>

    <!-- 載入中 -->
    <div v-if="categoryStore.loading" class="py-12 text-center text-muted-foreground">載入中…</div>

    <!-- 資料表格 -->
    <div v-else class="rounded-xl border bg-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">分類名稱</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">Slug</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">排序</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">狀態</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="orderedCategories.length === 0">
            <td colspan="5" class="py-12 text-center text-muted-foreground">尚無分類</td>
          </tr>
          <tr
            v-for="cat in orderedCategories"
            :key="cat.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <!-- 名稱縮排顯示層級 -->
            <td class="py-3 font-medium" :style="indentStyle(cat)">
              <span v-if="cat.parent_id" class="text-muted-foreground mr-1">└</span>
              {{ cat.name }}
            </td>
            <td class="px-4 py-3 text-muted-foreground font-mono text-xs">{{ cat.slug }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ cat.sort_order }}</td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                  cat.is_active
                    ? 'bg-green-100 text-green-700'
                    : 'bg-gray-100 text-gray-500',
                ]"
              >
                {{ cat.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button class="text-sm text-blue-600 hover:underline" @click="openEdit(cat)">
                  編輯
                </button>
                <button
                  class="text-sm text-destructive hover:underline"
                  @click="deleteConfirm = cat.id"
                >
                  刪除
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 新增/編輯 Dialog -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="dialogOpen"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
          @click.self="dialogOpen = false"
        >
          <div class="w-full max-w-md rounded-xl bg-card border p-6 shadow-xl">
            <h2 class="text-lg font-semibold mb-4">
              {{ editingId ? '編輯分類' : '新增分類' }}
            </h2>

            <form class="space-y-4" @submit.prevent="onSubmit">
              <!-- 名稱 -->
              <div class="space-y-1.5">
                <label class="text-sm font-medium">分類名稱 <span class="text-destructive">*</span></label>
                <input
                  v-model="name"
                  type="text"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                  :class="{ 'border-destructive': errors.name }"
                  placeholder="請輸入分類名稱"
                />
                <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
              </div>

              <!-- 上層分類 -->
              <div class="space-y-1.5">
                <label class="text-sm font-medium">上層分類</label>
                <select
                  v-model="parent_id"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                >
                  <option :value="null">— 根分類（無上層）</option>
                  <option
                    v-for="c in parentCandidates"
                    :key="c.id"
                    :value="c.id"
                  >
                    {{ c.name }}
                  </option>
                </select>
              </div>

              <!-- 排序 -->
              <div class="space-y-1.5">
                <label class="text-sm font-medium">排序值</label>
                <input
                  v-model.number="sort_order"
                  type="number"
                  min="0"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                  placeholder="0"
                />
              </div>

              <!-- 描述 -->
              <div class="space-y-1.5">
                <label class="text-sm font-medium">描述</label>
                <textarea
                  v-model="description"
                  rows="2"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
                  placeholder="選填"
                />
              </div>

              <!-- 狀態 -->
              <div class="flex items-center gap-2">
                <input
                  id="cat-active"
                  v-model="is_active"
                  type="checkbox"
                  class="h-4 w-4 rounded border-input"
                />
                <label for="cat-active" class="text-sm">啟用此分類</label>
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
                  :disabled="categoryStore.saving"
                  class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                >
                  {{ categoryStore.saving ? '儲存中…' : '儲存' }}
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
              刪除後無法復原。若此分類有子分類或商品，將無法刪除。
            </p>
            <div class="flex justify-end gap-2">
              <button
                class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
                @click="deleteConfirm = null"
              >
                取消
              </button>
              <button
                class="rounded-md bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground hover:opacity-90 transition-opacity"
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
