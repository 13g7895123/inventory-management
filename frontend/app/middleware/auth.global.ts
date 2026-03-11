// app/middleware/auth.global.ts
// 全域路由守衛（.global 後綴 = 每次路由切換自動執行）

export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()

  // 登入頁：已登入則導向後台首頁
  if (to.path === '/login') {
    if (authStore.isAuthenticated) {
      return navigateTo('/dashboard')
    }
    return
  }

  // 根路徑重導向（index.vue 自己處理，此處允許通過）
  if (to.path === '/') {
    return
  }

  // 其他頁面：未登入則導向登入頁，並記錄原目標路由
  if (! authStore.isAuthenticated) {
    return navigateTo({
      path:  '/login',
      query: { redirect: to.fullPath },
    })
  }
})
