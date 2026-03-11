<script setup lang="ts">
// app/layouts/default.vue
// 後台主佈局：側邊欄 + 頂部導覽列 + 內容區

import {
  LayoutDashboard, Package, Boxes, Search, History, ArrowLeftRight, ClipboardList,
  ShieldAlert, Fingerprint, ShoppingCart, List, Plus, Truck, TrendingUp, Users,
  Warehouse, BarChart2, Table, PieChart, RefreshCcw, Settings, Tag, Ruler,
  Bell, LogOut, ChevronRight
} from 'lucide-vue-next'

const authStore = useAuthStore()
const invStore  = useInventoryStore()
const router    = useRouter()

onMounted(() => invStore.fetchLowStock())

const lowStockCount = computed(() => invStore.lowStockItems.length)

const iconMap: Record<string, any> = {
  LayoutDashboard, Package, Boxes, Search, History, ArrowLeftRight, ClipboardList,
  ShieldAlert, Fingerprint, ShoppingCart, List, Plus, Truck, TrendingUp, Users,
  Warehouse, BarChart2, Table, PieChart, RefreshCcw, Settings, Tag, Ruler
}

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
      { label: '庫存查詢',    icon: 'Search',         to: '/inventory' },
      { label: '庫存異動日誌', icon: 'History',        to: '/inventory/transactions' },
      { label: '庫存調撥',    icon: 'ArrowLeftRight',  to: '/inventory/transfers' },
      { label: '盤點管理',    icon: 'ClipboardList',   to: '/inventory/stocktakes' },
      { label: '安全庫存設定', icon: 'ShieldAlert',    to: '/inventory/safety-stock' },
      { label: '批號/序號追蹤', icon: 'Fingerprint',   to: '/inventory/batch-serials' },
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
  {
    label: '報表中心',
    icon:  'BarChart2',
    to:    '/reports',
    children: [
      { label: '進銷存彙總', icon: 'Table',         to: '/reports/inventory-summary' },
      { label: '銷售業績',   icon: 'TrendingUp',    to: '/reports/sales' },
      { label: '毛利分析',   icon: 'PieChart',      to: '/reports/profit' },
      { label: '採購報表',   icon: 'ShoppingCart',  to: '/reports/purchase' },
      { label: '庫存週轉率', icon: 'RefreshCcw',    to: '/reports/turnover' },
    ],
  },
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
  <div class="flex min-h-screen bg-gray-50/40">
    <!-- 側邊欄 -->
    <aside class="fixed inset-y-0 left-0 z-20 w-64 border-r bg-white shadow-sm flex flex-col transition-all duration-300">
      <!-- Logo -->
      <div class="h-16 flex items-center px-6 border-b bg-white">
        <div class="flex items-center gap-2 text-primary">
          <Boxes class="h-6 w-6" />
          <span class="text-lg font-bold tracking-tight text-foreground">進銷存系統</span>
        </div>
      </div>

      <!-- 導覽選單 -->
      <nav class="flex-1 overflow-y-auto py-6 px-3 space-y-1">
        <template v-for="item in navItems" :key="item.to">
          <!-- 單一層級連結 -->
          <NuxtLink
            v-if="!item.children"
            :to="item.to"
            class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground transition-all hover:bg-primary/5 hover:text-primary"
            active-class="bg-primary/10 text-primary shadow-sm"
          >
            <component :is="iconMap[item.icon]" class="h-4 w-4 transition-transform group-hover:scale-110" />
            <span>{{ item.label }}</span>
          </NuxtLink>

          <!-- 有子選單的項目 -->
          <details v-else class="group/details open:bg-transparent">
            <summary class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground transition-all hover:bg-muted/50 list-none select-none">
              <component :is="iconMap[item.icon]" class="h-4 w-4" />
              <span class="flex-1">{{ item.label }}</span>
              
              <!-- 低庫存警示徽章 -->
              <span
                v-if="item.to === '/inventory' && lowStockCount > 0"
                class="inline-flex items-center justify-center min-w-[1.25rem] h-5 rounded-full bg-red-500 text-[10px] font-bold text-white px-1.5 shadow-sm animate-pulse"
              >
                {{ lowStockCount > 99 ? '99+' : lowStockCount }}
              </span>
              <ChevronRight class="h-4 w-4 transition-transform duration-200 group-open/details:rotate-90 text-muted-foreground/50" />
            </summary>
            
            <div class="ml-4 mt-1 border-l pl-2 space-y-1 my-1">
              <NuxtLink
                v-for="child in item.children"
                :key="child.to"
                :to="child.to"
                class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-primary/5 hover:text-primary"
                active-class="bg-primary/5 text-primary font-semibold"
              >
                <div class="w-1 h-1 rounded-full bg-current opacity-40"></div>
                {{ child.label }}
              </NuxtLink>
            </div>
          </details>
        </template>
      </nav>

      <!-- 使用者資訊 (底部) -->
      <div class="border-t p-4 bg-gray-50/50">
        <div class="flex items-center justify-between gap-2 p-2 rounded-lg border bg-white shadow-sm">
          <div class="flex items-center gap-3 min-w-0">
            <div class="h-9 w-9 rounded-full bg-gradient-to-br from-primary to-primary/80 text-primary-foreground flex items-center justify-center text-sm font-bold shadow-sm">
              {{ authStore.user?.name?.charAt(0) }}
            </div>
            <div class="flex-1 min-w-0 overflow-hidden">
              <p class="text-sm font-semibold truncate text-foreground">{{ authStore.user?.name }}</p>
              <p class="text-xs text-muted-foreground truncate">{{ authStore.user?.role }}</p>
            </div>
          </div>
          <button
            class="p-2 text-muted-foreground hover:text-red-600 hover:bg-red-50 rounded-full transition-colors flex-shrink-0"
            @click="handleLogout"
            title="登出"
          >
            <LogOut class="h-4 w-4" />
          </button>
        </div>
      </div>
    </aside>

    <!-- 主內容區 -->
    <div class="flex-1 ml-64 flex flex-col min-h-screen transition-all duration-300">
      <!-- 頂部 Header -->
      <header class="sticky top-0 z-10 w-full h-16 bg-white/80 backdrop-blur border-b flex items-center justify-between px-6 shadow-sm">
        <div class="flex items-center gap-4">
           <!-- Breadcrumb or Title placeholder -->
           <h2 class="text-lg font-medium text-foreground tracking-tight">管理中心</h2>
        </div>

        <div class="flex items-center gap-4">
           <!-- Notifications -->
           <button class="relative p-2 rounded-full text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
              <Bell class="h-5 w-5" />
              <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-red-500 border border-white"></span>
           </button>
        </div>
      </header>

      <!-- 頁面內容 -->
      <main class="flex-1 p-6 md:p-8 space-y-6 overflow-x-hidden">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
/* 隱藏原生 details 箭頭 */
details > summary {
  list-style: none;
}
details > summary::-webkit-details-marker {
  display: none;
}
</style>
