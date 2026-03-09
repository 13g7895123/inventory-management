<script setup lang="ts">
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import type { Unit } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const unitStore = useUnitStore()

// ── 載入 ─────────────────────────────────────────────────────────────
onMounted(() => unitStore.fetchAll())

// ── 對話框狀態 ────────────────────────────────────────────────────────
const dialogOpen    = ref(false)
const editingId     = ref<number | null>(null)
const deleteConfirm = ref<number | null>(null)
const errorMsg      = ref('')

// ── 表單 ─────────────────────────────────────────────────────────────
const schema = toTypedSchema(
  z.object({
    name:        z.string().min(1, '請輸入單位名稱').max(50),
    symbol:      z.string().min(1, '請輸入單位符號').max(20),
    description: z.string().max(255).optional(),
    is_active:   z.boolean(),
  })
)

const { handleSubmit, errors, resetForm, setValues } = useForm({
  validationSchema: schema,
  initialValues: { name: '', symbol: '', description: '', is_active: true },
})

const { value: name }        = useField<string>('name')
const { value: symbol }      = useField<string>('symbol')
const { value: description } = useField<string>('description')
const { value: is_active }   = useField<boolean>('is_active')

function openCreate() {
  editingId.value = null
  resetForm()
  errorMsg.value  = ''
  dialogOpen.value = true
}

function openEdit(unit: Unit) {
  editingId.value = unit.id
  setValues({
    name:        unit.name,
    symbol:      unit.symbol,
    description: unit.description ?? '',
    is_active:   unit.is_active,
  })
  errorMsg.value  = ''
  dialogOpen.value = true
}

const onSubmit = handleSubmit(async (values) => {
  errorMsg.value = ''
  try {
    if (editingId.value) {
      await unitStore.update(editingId.value, values)
    } else {
      await unitStore.create(values)
    }
    dialogOpen.value = false
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '儲存失敗'
  }
})

async function confirmDelete(id: number) {
  try {
    await unitStore.remove(id)
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
        <h1 class="text-2xl font-semibold">計量單位管理</h1>
        <p class="mt-1 text-sm text-muted-foreground">管理商品計量單位（個、箱、公斤…）</p>
      </div>
      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="openCreate"
      >
        + 新增單位
      </button>
    </div>

    <!-- 載入中 -->
    <div v-if="unitStore.loading" class="py-12 text-center text-muted-foreground">載入中…</div>

    <!-- 資料表格 -->
    <div v-else class="rounded-xl border bg-card overflow-hidden">
      <table class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">名稱</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">符號</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">描述</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">狀態</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="unitStore.units.length === 0">
            <td colspan="5" class="py-12 text-center text-muted-foreground">尚無計量單位</td>
          </tr>
          <tr
            v-for="unit in unitStore.units"
            :key="unit.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <td class="px-4 py-3 font-medium">{{ unit.name }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ unit.symbol }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ unit.description || '—' }}</td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                  unit.is_active
                    ? 'bg-green-100 text-green-700'
                    : 'bg-gray-100 text-gray-500',
                ]"
              >
                {{ unit.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-2">
                <button
                  class="text-sm text-blue-600 hover:underline"
                  @click="openEdit(unit)"
                >
                  編輯
                </button>
                <button
                  class="text-sm text-destructive hover:underline"
                  @click="deleteConfirm = unit.id"
                >
                  刪除
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 新增/編輯 對話框 -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="dialogOpen"
          class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
          @click.self="dialogOpen = false"
        >
          <div class="w-full max-w-md rounded-xl bg-card border p-6 shadow-xl">
            <h2 class="text-lg font-semibold mb-4">
              {{ editingId ? '編輯計量單位' : '新增計量單位' }}
            </h2>

            <form class="space-y-4" @submit.prevent="onSubmit">
              <!-- 名稱 -->
              <div class="space-y-1.5">
                <label class="text-sm font-medium">名稱 <span class="text-destructive">*</span></label>
                <input
                  v-model="name"
                  type="text"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                  :class="{ 'border-destructive': errors.name }"
                  placeholder="例：個、箱、公斤"
                />
                <p v-if="errors.name" class="text-xs text-destructive">{{ errors.name }}</p>
              </div>

              <!-- 符號 -->
              <div class="space-y-1.5">
                <label class="text-sm font-medium">符號 <span class="text-destructive">*</span></label>
                <input
                  v-model="symbol"
                  type="text"
                  class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                  :class="{ 'border-destructive': errors.symbol }"
                  placeholder="例：pcs、Box、kg"
                />
                <p v-if="errors.symbol" class="text-xs text-destructive">{{ errors.symbol }}</p>
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
                  id="unit-active"
                  v-model="is_active"
                  type="checkbox"
                  class="h-4 w-4 rounded border-input"
                />
                <label for="unit-active" class="text-sm">啟用此單位</label>
              </div>

              <!-- 錯誤訊息 -->
              <p v-if="errorMsg" class="text-sm text-destructive">{{ errorMsg }}</p>

              <!-- 按鈕 -->
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
                  :disabled="unitStore.saving"
                  class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 transition-colors"
                >
                  {{ unitStore.saving ? '儲存中…' : '儲存' }}
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
            <p class="text-sm text-muted-foreground mb-4">刪除後無法復原，確定要刪除此計量單位嗎？</p>
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
