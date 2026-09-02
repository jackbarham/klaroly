import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import DashboardView from '@/views/DashboardView.vue'
import LoginView from '@/views/LoginView.vue'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
  }
}

// Every route is listed here, by hand. There is no file-based routing.
const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: LoginView,
    meta: { guestOnly: true },
  },
  {
    path: '/',
    name: 'dashboard',
    component: DashboardView,
    meta: { requiresAuth: true },
  },
]

// Billing exists only on the web target. __WEB_TARGET__ is a compile-time
// constant (see vite.config.ts), so on the mobile build this block is dead
// code and the billing chunk is never emitted. The import must stay dynamic
// and inside this branch for that to hold; a static import at the top of the
// file would keep the code in the mobile binary.
if (__WEB_TARGET__) {
  routes.push({
    path: '/billing',
    name: 'billing',
    component: () => import('@/views/BillingView.vue'),
    meta: { requiresAuth: true },
  })
}

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
