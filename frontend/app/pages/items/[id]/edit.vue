<script setup lang="ts">
definePageMeta({ layout: 'default' })

const route     = useRoute()
const router    = useRouter()
const itemStore = useItemStore()
const errorMsg  = ref('')

const itemId = computed(() => Number(route.params.id))

onMounted(async () => {
  await itemStore.fetchOne(itemId.value)
})

async function handleSubmit(payload: Record<string, unknown>) {
  errorMsg.value = ''
  try {
    await itemStore.update(itemId.value, payload)
    await router.push('/items')
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '更新商品失敗'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/items" class="hover:text-foreground">商品管理</NuxtLink>
      <span>›</span>
      <span class="text-foreground">{{ itemStore.current?.name ?? '編輯商品' }}</span>
    </div>

    <!-- 標題列 -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">
        {{ itemStore.current ? `編輯：${itemStore.current.name}` : '載入中…' }}
      </h1>
      <div class="flex gap-2">
        <NuxtLink
          :to="`/items/${itemId}/skus`"
          class="rounded-md border px-4 py-2 text-sm hover:bg-muted transition-colors"
        >
          管理 SKU
        </NuxtLink>
      </div>
    </div>

    <!-- 載入中 -->
    <div v-if="itemStore.loading && !itemStore.current" class="py-16 text-center text-muted-foreground">
      載入中…
    </div>

    <!-- 錯誤訊息 -->
    <p
      v-if="errorMsg"
      class="rounded-md border border-destructive bg-destructive/5 px-4 py-3 text-sm text-destructive"
    >
      {{ errorMsg }}
    </p>

    <!-- 圖片上傳區 -->
    <div v-if="itemStore.current" class="rounded-xl border bg-card p-6 space-y-3">
      <h2 class="text-base font-semibold">商品圖片</h2>
      <ImageUpload
        :item-id="itemId"
        :current-image="itemStore.current.image_path ?? undefined"
      />
    </div>

    <!-- 表單 -->
    <ItemForm
      v-if="itemStore.current"
      :initial="itemStore.current"
      :saving="itemStore.saving"
      @submit="handleSubmit"
      @cancel="router.push('/items')"
    />
  </div>
</template>
