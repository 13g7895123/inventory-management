<script setup lang="ts">
// app/layouts/default.vue
// 後台主佈局：側邊欄 + 頂部導覽列 + 內容區

const authStore = useAuthStore()
const router    = useRouter()

interface NavItem {
  label: string
  icon:  string
  to:    string
  children?: NavItem[]
}

const navItems: NavItem[] = [
  { label: '儀表板',   icon: 'LayoutDashboard', to: '/' },
  { label: '商品管理', icon: 'Package',          to: '/items' },
  {
    label: '庫存管理',
    icon:  'Boxes',
    to:    '/inventory',
    children: [
      { label: '庫存查詢',   icon: 'Search',      to: '/inventory' },
      { label: '庫存異動日誌', icon: 'History',   to: '/inventory/transactions' },
      { label: '庫存調撥',   icon: 'ArrowLeftRight', to: '/inventory/transfers' },
      { label: '盤點管理',   icon: 'ClipboardList', to: '/inventory/stocktakes' },
    ],
  },
  {
    label: '採購管理',
    icon:  'ShoppingCart',
    to:    '/purchase',
    children: [
      { label: '採購單列表', icon: 'List',   to: '/purchase/orders' },
      { label: '新增採購單', icon: 'Plus',   to: '/purchase/orders/create' },
      { label: '廠商管理',   icon: 'Truck',  to: '/purchase/suppliers' },
    ],
  },
  {
    label: '銷售管理',
    icon:  'TrendingUp',
    to:    '/sales',
    children: [
      { label: '銷售單列表', icon: 'List',  to: '/sales/orders' },
      { label: '新增銷售單', icon: 'Plus',  to: '/sales/orders/create' },
      { label: '客戶管理',   icon: 'Users', to: '/sales/customers' },
    ],
  },
  { label: '倉庫管理', icon: 'Warehouse',  to: '/warehouses' },
  { label: '報表中心', icon: 'BarChart2',  to: '/reports' },
  {
    label: '基礎資料',
    icon:  'Settings',
    to:    '/master',
    children: [
      { label: '商品分類', icon: 'Tag',      to: '/master/categories' },
      { label: '計量單位', icon: 'Ruler',    to: '/master/units' },
    ],
  },
]

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<template>
  <div class="flex min-h-screen bg-background">
    <!-- 側邊欄 -->
    <aside class="w-64 border-r bg-card flex flex-col">
      <!-- Logo -->
      <div class="h-16 flex items-center px-6 border-b">
        <span class="text-lg font-bold">進銷存系統</span>
      </div>

      <!-- 導覽選單 -->
      <nav class="flex-1 overflow-y-auto py-4 px-2">
        <template v-for="item in navItems" :key="item.to">
          <NuxtLink
            v-if="! item.children"
            :to="item.to"
            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground transition-colors"
            active-class="bg-accent text-accent-foreground"
          >
            <span>{{ item.label }}</span>
          </NuxtLink>

          <!-- 有子選單的項目（可展開） -->
          <details v-else class="group">
            <summary class="flex cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm font-medium hover:bg-accent list-none">
              <span class="flex-1">{{ item.label }}</span>
              <span class="transition-transform group-open:rotate-90">›</span>
            </summary>
            <div class="ml-4 mt-1 space-y-1">
              <NuxtLink
                v-for="child in item.children"
                :key="child.to"
                :to="child.to"
                class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                active-class="text-foreground font-medium"
              >
                {{ child.label }}
              </NuxtLink>
            </div>
          </details>
        </template>
      </nav>

      <!-- 使用者資訊 -->
      <div class="border-t p-4">
        <div class="flex items-center gap-3">
          <div class="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-sm font-bold">
            {{ authStore.user?.name?.charAt(0) }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">{{ authStore.user?.name }}</p>
            <p class="text-xs text-muted-foreground truncate">{{ authStore.user?.role }}</p>
          </div>
          <button
            class="text-sm text-muted-foreground hover:text-foreground"
            @click="handleLogout"
          >
            登出
          </button>
        </div>
      </div>
    </aside>

    <!-- 主要內容區 -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- 頂部導覽列 -->
      <header class="h-16 border-b bg-card flex items-center px-6 gap-4">
        <div class="flex-1">
          <!-- 麵包屑（各頁面自行插入） -->
          <slot name="breadcrumb" />
        </div>
      </header>

      <!-- 頁面內容 -->
      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
