import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import i18n from '@/i18n'
import { accountGroups, settingsGroups } from '@/lib/navigation'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/components/layout/AppLayout.vue'
import ForgotPasswordView from '@/views/ForgotPasswordView.vue'
import LoginView from '@/views/LoginView.vue'
import RegisterView from '@/views/RegisterView.vue'
import ResetPasswordView from '@/views/ResetPasswordView.vue'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    // The locale key for this page's name. It is the document title and, on
    // a page that has not been built yet, the heading as well.
    titleKey?: string
    // The name of the route a phone's back link goes to. A wide screen has
    // the sidebar and the settings column instead, so it shows no back link.
    backTo?: string
  }
}

// Every route is listed here, by hand. There is no file-based routing.
//
// Everything behind the sign-in sits under one layout route, so the shell is
// mounted once and a navigation swaps only the page inside it. Every one of
// those views is loaded on demand: the app starts with the shell and the
// page that was asked for, not with all twenty.
export const routes: RouteRecordRaw[] = [
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
    component: AppLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
        meta: { titleKey: 'home.title' },
      },
      // The attention list in full. A route rather than a flag on Home
      // (decision 2026-09-06.1942), because a notification can link straight to
      // it, the back gesture works, and a flag cannot be linked to. It has no
      // tab of its own and marks Home, which is the line in navigation.ts's
      // sectionKey map.
      {
        path: 'attention',
        name: 'attention',
        component: () => import('@/views/AttentionView.vue'),
        meta: { titleKey: 'home.attention.title', backTo: 'home' },
      },
      {
        path: 'bookings',
        name: 'bookings',
        component: () => import('@/views/bookings/BookingsView.vue'),
        meta: { titleKey: 'bookings.title' },
      },
      {
        path: 'bookings/:id',
        name: 'booking',
        component: () => import('@/views/PlaceholderView.vue'),
        meta: { titleKey: 'bookings.detail_title', backTo: 'bookings' },
      },
      // Enquiries is the same shape as Contacts: a list beside one record,
      // both under one parent so the list's scroll position and the filter box
      // survive a row being tapped. Both route names already existed, so
      // navigation.ts and routeNames.test.ts are unaffected.
      {
        path: 'enquiries',
        component: () => import('@/views/enquiries/EnquiriesView.vue'),
        children: [
          {
            path: '',
            name: 'enquiries',
            component: () => import('@/views/enquiries/EnquiriesIndexView.vue'),
            meta: { titleKey: 'enquiries.title' },
          },
          {
            path: ':id',
            name: 'enquiry',
            component: () => import('@/views/enquiries/EnquiryDetailView.vue'),
            meta: { titleKey: 'enquiries.detail_title', backTo: 'enquiries' },
          },
        ],
      },
      // Contacts is a list beside a detail, and both are one mount: the
      // parent holds the filter, the list and the two columns, and the child
      // is whatever the detail column is showing. A parent with children
      // rather than two sibling records is what keeps the list's scroll
      // position and the filter box intact when a row is tapped, because only
      // the child is swapped. Both route names are the ones that already
      // existed, so navigation.ts and routeNames.test.ts are unaffected.
      {
        path: 'contacts',
        component: () => import('@/views/contacts/ContactsView.vue'),
        children: [
          {
            path: '',
            name: 'contacts',
            component: () => import('@/views/contacts/ContactsIndexView.vue'),
            // No back link: Contacts is a tab of its own now, so on a phone
            // there is nothing above it to go back to.
            meta: { titleKey: 'contacts.title' },
          },
          {
            path: ':id',
            name: 'contact',
            component: () => import('@/views/contacts/ContactDetailView.vue'),
            meta: { titleKey: 'contacts.detail_title', backTo: 'contacts' },
          },
        ],
      },
      {
        path: 'more',
        name: 'more',
        component: () => import('@/views/MoreView.vue'),
        meta: { titleKey: 'more.title' },
      },
      {
        path: 'account',
        component: () => import('@/views/SectionLayout.vue'),
        props: { groups: accountGroups, indexRouteName: 'account', labelKey: 'account.title' },
        children: [
          {
            path: '',
            name: 'account',
            component: () => import('@/views/account/AccountView.vue'),
            meta: { titleKey: 'account.title' },
          },
          {
            path: 'details',
            name: 'account-details',
            component: () => import('@/views/account/AccountDetailsView.vue'),
            meta: { titleKey: 'account.details', backTo: 'account' },
          },
          {
            path: 'password',
            name: 'account-password',
            component: () => import('@/views/account/AccountPasswordView.vue'),
            meta: { titleKey: 'account.password', backTo: 'account' },
          },
          {
            path: 'devices',
            name: 'account-devices',
            component: () => import('@/views/account/AccountDevicesView.vue'),
            meta: { titleKey: 'account.devices', backTo: 'account' },
          },
          {
            path: 'email',
            name: 'account-email',
            component: () => import('@/views/account/AccountEmailView.vue'),
            meta: { titleKey: 'account.email', backTo: 'account' },
          },
        ],
      },
      {
        path: 'help',
        name: 'help',
        component: () => import('@/views/PlaceholderView.vue'),
        meta: { titleKey: 'help.title', backTo: 'more' },
      },
      {
        path: 'settings',
        component: () => import('@/views/SectionLayout.vue'),
        props: { groups: settingsGroups, indexRouteName: 'settings', labelKey: 'settings.title' },
        children: [
          {
            path: '',
            name: 'settings',
            component: () => import('@/views/settings/SettingsView.vue'),
            meta: { titleKey: 'settings.title' },
          },
          {
            path: 'features',
            name: 'settings-features',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.features', backTo: 'settings' },
          },
          {
            path: 'rate-card',
            name: 'settings-rate-card',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.rate_card', backTo: 'settings' },
          },
          {
            path: 'travel',
            name: 'settings-travel',
            component: () => import('@/views/settings/TravelSettingsView.vue'),
            meta: { titleKey: 'settings.travel', backTo: 'settings' },
          },
          {
            path: 'payments',
            name: 'settings-payments',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.payments', backTo: 'settings' },
          },
          {
            path: 'templates',
            name: 'settings-templates',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.templates', backTo: 'settings' },
          },
          {
            path: 'automation',
            name: 'settings-automation',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.automation', backTo: 'settings' },
          },
          {
            path: 'agreement',
            name: 'settings-agreement',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.agreement', backTo: 'settings' },
          },
          {
            path: 'intake',
            name: 'settings-intake',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.intake', backTo: 'settings' },
          },
          {
            path: 'working',
            name: 'settings-working',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.working', backTo: 'settings' },
          },
          {
            path: 'business-year',
            name: 'settings-business-year',
            component: () => import('@/views/PlaceholderView.vue'),
            meta: { titleKey: 'settings.business_year', backTo: 'settings' },
          },
        ],
      },
    ],
  },
]

// Billing exists only on the web target. __WEB_TARGET__ is a compile-time
// constant (see vite.config.ts), so on the mobile build this block is dead
// code and the billing chunk is never emitted. The import must stay dynamic
// and inside this branch for that to hold; a static import at the top of the
// file would keep the code in the mobile binary.
if (__WEB_TARGET__) {
  const layout = routes[routes.length - 1]

  layout.children?.push({
    path: 'billing',
    name: 'billing',
    component: () => import('@/views/BillingView.vue'),
    meta: { titleKey: 'billing.title' },
  })
}

// The kitchen sink is a development page: the whole UI kit and form kit on
// one route, so that a change to a token can be judged in one place. It sits
// inside the layout, so every component on it is shown in the container it
// really lives in, and the navigation config lists it under the same flag, so
// in development it is a link in the sidebar rather than an address to type.
//
// import.meta.env.DEV is a compile-time constant, so this branch is dead code
// in a build and neither the page nor the link is shipped.
if (import.meta.env.DEV) {
  const layout = routes[routes.length - 1]

  layout.children?.push({
    path: 'kitchen-sink',
    name: 'kitchen-sink',
    component: () => import('@/views/kitchen-sink/KitchenSinkView.vue'),
    meta: { titleKey: 'nav.kitchen_sink', backTo: 'more' },
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
    return { name: 'home' }
  }

  return true
})

// The document title says which page this is, for a browser tab, a bookmark
// and a screen reader announcing a navigation. It comes from the route's
// locale key, so there is no title written out anywhere but the locale file.
router.afterEach((to) => {
  const name = i18n.global.t('app.name')

  document.title = to.meta.titleKey ? `${i18n.global.t(to.meta.titleKey)} - ${name}` : name
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
