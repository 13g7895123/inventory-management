<script setup lang="ts">
// components/common/ImageUpload.vue
// 商品圖片上傳：支援點擊選取、拖曳上傳、預覽

const props = defineProps<{
  itemId:       number
  currentImage?: string
}>()

const emit = defineEmits<{
  (e: 'uploaded', url: string): void
}>()

const itemStore   = useItemStore()
const inputRef    = ref<HTMLInputElement | null>(null)
const isDragging  = ref(false)
const previewUrl  = ref<string | null>(props.currentImage ?? null)
const uploading   = ref(false)
const errorMsg    = ref('')

// 當 prop 更新時同步預覽圖
watch(() => props.currentImage, (v) => {
  if (v && !previewUrl.value) previewUrl.value = v
})

const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
const MAX_SIZE      = 5 * 1024 * 1024  // 5 MB

function validate(file: File): string | null {
  if (!ALLOWED_TYPES.includes(file.type)) {
    return '僅支援 JPEG、PNG、GIF、WebP 格式'
  }
  if (file.size > MAX_SIZE) {
    return '圖片大小不可超過 5 MB'
  }
  return null
}

async function processFile(file: File) {
  const err = validate(file)
  if (err) {
    errorMsg.value = err
    return
  }

  errorMsg.value = ''
  previewUrl.value = URL.createObjectURL(file)
  uploading.value  = true

  try {
    const url = await itemStore.uploadImage(props.itemId, file)
    emit('uploaded', url)
  } catch (e: unknown) {
    errorMsg.value  = e instanceof Error ? e.message : '上傳失敗'
    previewUrl.value = props.currentImage ?? null
  } finally {
    uploading.value = false
  }
}

function onFileChange(evt: Event) {
  const file = (evt.target as HTMLInputElement).files?.[0]
  if (file) processFile(file)
}

function onDrop(evt: DragEvent) {
  isDragging.value = false
  const file = evt.dataTransfer?.files?.[0]
  if (file) processFile(file)
}

function onDragOver(evt: DragEvent) {
  evt.preventDefault()
  isDragging.value = true
}

function onDragLeave() {
  isDragging.value = false
}

function clearImage() {
  previewUrl.value = null
  if (inputRef.value) inputRef.value.value = ''
}
</script>

<template>
  <div class="space-y-3">
    <!-- 上傳區域 -->
    <div
      :class="[
        'relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-6 transition-colors cursor-pointer',
        isDragging
          ? 'border-primary bg-primary/5'
          : 'border-input hover:border-primary/50 hover:bg-muted/20',
      ]"
      @click="inputRef?.click()"
      @dragover.prevent="onDragOver"
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
    >
      <!-- 目前圖片預覽 -->
      <div v-if="previewUrl" class="mb-3 relative">
        <img
          :src="previewUrl"
          class="h-32 w-32 rounded-lg object-cover border shadow-sm"
          alt="商品圖片預覽"
        />
        <button
          type="button"
          class="absolute -top-2 -right-2 h-5 w-5 rounded-full bg-destructive text-destructive-foreground text-xs flex items-center justify-center shadow hover:opacity-90"
          @click.stop="clearImage"
        >
          ×
        </button>
        <div
          v-if="uploading"
          class="absolute inset-0 rounded-lg bg-black/40 flex items-center justify-center"
        >
          <span class="text-white text-xs">上傳中…</span>
        </div>
      </div>

      <!-- 無圖片時的提示 -->
      <template v-else>
        <div class="mb-2 h-12 w-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground text-xl">
          📷
        </div>
        <p class="text-sm font-medium text-center">
          {{ isDragging ? '放開以上傳' : '拖曳圖片至此，或點擊選取' }}
        </p>
        <p class="text-xs text-muted-foreground mt-1">
          支援 JPG、PNG、GIF、WebP；最大 5 MB
        </p>
      </template>

      <!-- 隱藏 input -->
      <input
        ref="inputRef"
        type="file"
        accept="image/jpeg,image/png,image/gif,image/webp"
        class="sr-only"
        @change="onFileChange"
      />
    </div>

    <!-- 已有圖片時顯示重新上傳提示 -->
    <p v-if="previewUrl && !uploading" class="text-xs text-muted-foreground text-center">
      點擊圖片區域可更換圖片
    </p>

    <!-- 上傳進度 -->
    <div v-if="uploading" class="flex items-center gap-2">
      <div class="h-1.5 flex-1 rounded-full bg-muted overflow-hidden">
        <div class="h-full w-1/2 rounded-full bg-primary animate-pulse" />
      </div>
      <span class="text-xs text-muted-foreground">上傳中…</span>
    </div>

    <!-- 錯誤訊息 -->
    <p v-if="errorMsg" class="text-xs text-destructive">{{ errorMsg }}</p>
  </div>
</template>
