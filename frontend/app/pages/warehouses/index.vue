<script setup lang="ts">
import type { Warehouse } from '~/app/types/api'

definePageMeta({ layout: 'default' })

const store = useWarehousesStore()

// ── 載入 ─────────────────────────────────────────────────────────────
onMounted(() => store.fetchList())

// ── 新增 / 編輯 Modal ─────────────────────────────────────────────────
const showModal = ref(false)
const editing   = ref<Warehouse | null>(null)

const form = reactive({
  name:      '',
  code:      '',
  location:  '',
  is_active: true,
  notes:     '',
})

function openCreate() {
  editing.value  = null
  form.name      = ''
  form.code      = ''
  form.location  = ''
  form.is_active = true
  form.notes     = ''
  showModal.value = true
}

function openEdit(w: Warehouse) {
  editing.value  = w
  form.name      = w.name
  form.code      = w.code
  form.location  = w.location ?? ''
  form.is_active = w.is_active
  form.notes     = w.notes ?? ''
  showModal.value = true
}

async function submit() {
  if (!form.name || !form.code) return
  try {
    if (editing.value) {
      await store.update(editing.value.id, { ...form })
    } else {
      await store.create({ ...form })
    }
    showModal.value = false
  } catch {
    // error held in store
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">倉庫管理</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          共 {{ store.warehouses.length }} 座倉庫
        </p>
      </div>
      <button
        class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        @click="openCreate"
      >
        + 新增倉庫
      </button>
    </div>

    <!-- 錯誤訊息 -->
    <div v-if="store.error" class="rounded-md border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive">
      {{ store.error }}
    </div>

    <!-- 表格 -->
    <div class="rounded-xl border bg-card overflow-hidden">
      <div v-if="store.loading" class="py-16 text-center text-muted-foreground">載入中…</div>

      <table v-else class="w-full text-sm">
        <thead class="border-b bg-muted/40">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">代碼</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">倉庫名稱</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">位置</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">備註</th>
            <th class="px-4 py-3 text-left font-medium text-muted-foreground">狀態</th>
            <th class="px-4 py-3 text-right font-medium text-muted-foreground">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="store.warehouses.length === 0">
            <td colspan="6" class="py-16 text-center text-muted-foreground">尚無倉庫資料</td>
          </tr>
          <tr
            v-for="w in store.warehouses"
            :key="w.id"
            class="border-b last:border-0 hover:bg-muted/20 transition-colors"
          >
            <td class="px-4 py-3 font-mono text-xs text-muted-foreground">{{ w.code }}</td>
            <td class="px-4 py-3 font-medium">{{ w.name }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ w.location || '—' }}</td>
            <td class="px-4 py-3 text-muted-foreground max-w-xs truncate">{{ w.notes || '—' }}</td>
            <td class="px-4 py-3">
              <span
                :class="[
                  'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                  w.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500',
                ]"
              >
                {{ w.is_active ? '啟用' : '停用' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                class="text-sm text-primary hover:underline"
                @click="openEdit(w)"
              >
                編輯
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="showModal = false"
    >
      <div class="w-full max-w-md rounded-xl border bg-card p-6 shadow-lg space-y-4">
        <h2 class="text-lg font-semibold">{{ editing ? '編輯倉庫' : '新增倉庫' }}</h2>

        <div class="space-y-3">
          <div>
            <label class="mb-1 block text-sm font-medium">倉庫代碼 <span class="text-destructive">*</span></label>
            <input
              v-model="form.code"
              type="text"
              placeholder="ex: WH-A"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium">倉庫名稱 <span class="text-destructive">*</span></label>
            <input
              v-model="form.name"
              type="text"
              placeholder="ex: 台北主倉"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium">位置</label>
            <input
              v-model="form.location"
              type="text"
              placeholder="ex: 台北市中正區"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium">備註</label>
            <textarea
              v-model="form.notes"
              rows="2"
              class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
            />
          </div>
          <div class="flex items-center gap-2">
            <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4" />
            <label for="is_active" class="text-sm">啟用此倉庫</label>
          </div>
        </div>

        <div v-if="store.error" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {{ store.error }}
        </div>

        <div class="flex gap-2 justify-end">
          <button
            class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
            @click="showModal = false"
          >
            取消
          </button>
          <button
            :disabled="store.saving || !form.name || !form.code"
            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-50"
            @click="submit"
          >
            {{ store.saving ? '儲存中…' : '儲存' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
