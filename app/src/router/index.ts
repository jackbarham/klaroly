import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import DashboardView from '@/views/DashboardView.vue'
import ForgotPasswordView from '@/views/ForgotPasswordView.vue'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import ResetPasswordView from '@/views/ResetPasswordView.vue'

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
    path: '/register',
    name: 'register',
    component: RegisterView,
    meta: { guestOnly: true },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: ForgotPasswordView,
    meta: { guestOnly: true },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: ResetPasswordView,
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

// The store finds out whether a session or token is still good exactly once,
// before the first navigation. Later navigations use what the store knows.
let bootstrapped: Promise<void> | null = null

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (bootstrapped === null) {
    bootstrapped = auth.bootstrap()
  }

  await bootstrapped

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

// Where to go after signing in or registering. The redirect query is
// followed only when it is a path inside this app: one leading slash and
// not two. A value such as //evil.example or https://evil.example would
// otherwise send the person, and any token in the URL, to another site.
export function destinationAfterSignIn(redirect: unknown): string {
  if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')) {
    return redirect
  }

  return '/'
}

export default router
