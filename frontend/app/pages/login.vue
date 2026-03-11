<script setup lang="ts">
import { useForm, useField } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'
import { z } from 'zod'
import { Boxes, User, Lock, Loader2, AlertCircle } from 'lucide-vue-next'

definePageMeta({
  layout: 'auth',
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

const { handleSubmit, errors, isSubmitting } = useForm({ validationSchema: loginSchema })
const { value: username } = useField<string>('username')
const { value: password } = useField<string>('password')

const errorMsg = ref('')

const onSubmit = handleSubmit(async (values) => {
  errorMsg.value = ''
  try {
    await authStore.login({ username: values.username, password: values.password })
    const redirect = route.query.redirect as string | undefined
    
    // Redirect handling
    if (redirect) {
      await router.push(redirect)
    } else {
      await router.replace('/dashboard')
    }
  } catch (err: unknown) {
    errorMsg.value = err instanceof Error ? err.message : '帳號或密碼錯誤，請重試'
  }
})

onMounted(() => {
  if (authStore.isAuthenticated) {
    router.replace('/dashboard')
  }
})

const currentYear = new Date().getFullYear()
</script>

<template>
  <div class="space-y-6">
    <!-- Header / Branding -->
    <div class="text-center space-y-2">
      <div class="inline-flex items-center justify-center p-3 rounded-2xl bg-primary/10 text-primary mb-2 shadow-sm">
        <Boxes class="w-8 h-8" stroke-width="2.5" />
      </div>
      <h1 class="text-2xl font-bold tracking-tight text-foreground">進銷存管理系統</h1>
      <p class="text-sm text-muted-foreground max-w-xs mx-auto">
        請登入您的帳戶以存取管理介面
      </p>
    </div>

    <!-- Login Card -->
    <div class="rounded-xl border bg-card/80 backdrop-blur-sm shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden">
      <div class="p-6 md:p-8 space-y-6">
        
        <!-- Error Alert -->
        <div v-if="errorMsg" class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive flex items-start gap-2 animate-in fade-in slide-in-from-top-2">
          <AlertCircle class="w-4 h-4 mt-0.5 shrink-0" />
          <span>{{ errorMsg }}</span>
        </div>

        <form class="space-y-4" @submit="onSubmit">
          <!-- Username Field -->
          <div class="space-y-2">
            <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70" for="username">
              帳號
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted-foreground">
                <User class="h-4 w-4" />
              </div>
              <input
                id="username"
                v-model="username"
                type="text"
                autocomplete="username"
                placeholder="請輸入帳號"
                :disabled="isSubmitting"
                :class="[
                  'flex h-10 w-full rounded-md border bg-background pl-9 px-3 py-2 text-sm ring-offset-background transition-all',
                  'placeholder:text-muted-foreground',
                  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                  errors.username 
                    ? 'border-destructive focus-visible:ring-destructive' 
                    : 'border-input hover:border-primary/50',
                  isSubmitting && 'opacity-50 cursor-not-allowed'
                ]"
              />
            </div>
            <p v-if="errors.username" class="text-xs font-medium text-destructive mt-1 animate-in slide-in-from-left-1">
              {{ errors.username }}
            </p>
          </div>

          <!-- Password Field -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70" for="password">
                密碼
              </label>
              <a href="#" class="text-xs font-medium text-primary hover:underline hover:text-primary/80" tabindex="-1">
                忘記密碼？
              </a>
            </div>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-muted-foreground">
                <Lock class="h-4 w-4" />
              </div>
              <input
                id="password"
                v-model="password"
                type="password"
                autocomplete="current-password"
                placeholder="請輸入密碼"
                :disabled="isSubmitting"
                :class="[
                  'flex h-10 w-full rounded-md border bg-background pl-9 px-3 py-2 text-sm ring-offset-background transition-all',
                  'placeholder:text-muted-foreground',
                  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                  errors.password 
                    ? 'border-destructive focus-visible:ring-destructive' 
                    : 'border-input hover:border-primary/50',
                  isSubmitting && 'opacity-50 cursor-not-allowed'
                ]"
              />
            </div>
            <p v-if="errors.password" class="text-xs font-medium text-destructive mt-1 animate-in slide-in-from-left-1">
              {{ errors.password }}
            </p>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isSubmitting"
            class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2 w-full mt-2 shadow-sm hover:shadow group"
          >
            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
            <span v-else>登入系統</span>
          </button>
        </form>
      </div>
      
      <!-- Footer Area inside card -->
      <div class="bg-muted/30 p-4 border-t text-center">
         <p class="text-xs text-muted-foreground">
           還沒有帳號？ <a href="#" class="font-medium text-primary hover:underline">聯繫管理員</a>
         </p>
      </div>
    </div>

    <!-- Copyright Footer -->
    <div class="text-center text-xs text-muted-foreground py-2">
      Inventory Management System &copy; {{ currentYear }}
    </div>
  </div>
</template>
