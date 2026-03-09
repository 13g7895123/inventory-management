<script setup lang="ts">
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'

definePageMeta({
  layout: 'auth',
  // 不套用全域 auth middleware（登入頁為公開頁）
  middleware: [],
})

const authStore = useAuthStore()
const router    = useRouter()
const route     = useRoute()

const loginSchema = toTypedSchema(
  z.object({
    username: z.string().min(3, '帳號至少 3 個字元'),
    password: z.string().min(1, '請輸入密碼'),
  })
)

const { handleSubmit, errors } = useForm({ validationSchema: loginSchema })
const { value: username } = useField<string>('username')
const { value: password } = useField<string>('password')

const errorMsg = ref('')

const onSubmit = handleSubmit(async (values) => {
  errorMsg.value = ''
  try {
    await authStore.login({ username: values.username, password: values.password })
    const redirect = route.query.redirect as string | undefined
    await router.push(redirect ?? '/dashboard')
  } catch (err: unknown) {
    errorMsg.value = err instanceof Error ? err.message : '帳號或密碼錯誤，請重試'
  }
})

// 已登入則直接跳轉
onMounted(() => {
  if (authStore.isAuthenticated) {
    router.replace('/dashboard')
  }
})

const currentYear = new Date().getFullYear()
</script>

<template>
  <div class="w-full max-w-sm space-y-6">
    <!-- 標題 -->
    <div class="text-center">
      <h1 class="text-2xl font-bold tracking-tight">進銷存管理系統</h1>
      <p class="mt-1.5 text-sm text-muted-foreground">請輸入帳號密碼登入系統</p>
    </div>

    <!-- 登入卡片 -->
    <div class="rounded-xl border bg-card p-6 shadow-sm">
      <form class="space-y-4" @submit="onSubmit">

        <!-- 帳號 -->
        <div class="space-y-1.5">
          <label class="text-sm font-medium leading-none" for="username">
            帳號
          </label>
          <input
            id="username"
            v-model="username"
            type="text"
            autocomplete="username"
            placeholder="請輸入帳號"
            :class="[
              'flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-sm transition-colors',
              'placeholder:text-muted-foreground',
              'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
              errors.username ? 'border-destructive' : 'border-input',
            ]"
          />
          <p v-if="errors.username" class="text-xs text-destructive">
            {{ errors.username }}
          </p>
        </div>

        <!-- 密碼 -->
        <div class="space-y-1.5">
          <label class="text-sm font-medium leading-none" for="password">
            密碼
          </label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            placeholder="請輸入密碼"
            :class="[
              'flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-sm transition-colors',
              'placeholder:text-muted-foreground',
              'focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring',
              errors.password ? 'border-destructive' : 'border-input',
            ]"
          />
          <p v-if="errors.password" class="text-xs text-destructive">
            {{ errors.password }}
          </p>
        </div>

        <!-- 錯誤訊息 -->
        <p v-if="errorMsg" class="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
          {{ errorMsg }}
        </p>

        <!-- 提交按鈕 -->
        <button
          type="submit"
          :disabled="authStore.loading"
          class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 transition-colors"
        >
          <span v-if="authStore.loading">登入中...</span>
          <span v-else>登入</span>
        </button>
      </form>
    </div>

    <p class="text-center text-xs text-muted-foreground">
      © {{ currentYear }} 進銷存管理系統
    </p>
  </div>
</template>
