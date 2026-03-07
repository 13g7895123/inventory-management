// app/middleware/auth.ts
// 全域路由守衛：未登入則導向 /login

export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()

  if (to.path === '/login') {
    // 已登入時造訪登入頁 → 導向首頁
    if (authStore.isAuthenticated) {
      return navigateTo('/')
    }
    return
  }

  // 未登入時造訪其他頁面 → 導向登入
  if (! authStore.isAuthenticated) {
    return navigateTo('/login')
  }
})
