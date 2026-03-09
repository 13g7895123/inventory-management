<script setup lang="ts">
definePageMeta({ layout: 'default' })

const itemStore = useItemStore()
const router    = useRouter()
const errorMsg  = ref('')

async function handleSubmit(payload: Record<string, unknown>) {
  errorMsg.value = ''
  try {
    const item = await itemStore.create(payload)
    await router.push(`/items/${item.id}/edit`)
  } catch (e: unknown) {
    errorMsg.value = e instanceof Error ? e.message : '建立商品失敗'
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- 麵包屑 -->
    <div class="flex items-center gap-1 text-sm text-muted-foreground">
      <NuxtLink to="/items" class="hover:text-foreground">商品管理</NuxtLink>
      <span>›</span>
      <span class="text-foreground">新增商品</span>
    </div>

    <h1 class="text-2xl font-semibold">新增商品</h1>

    <p v-if="errorMsg" class="rounded-md border border-destructive bg-destructive/5 px-4 py-3 text-sm text-destructive">
      {{ errorMsg }}
    </p>

    <ItemForm
      :saving="itemStore.saving"
      @submit="handleSubmit"
      @cancel="router.push('/items')"
    />
  </div>
</template>
