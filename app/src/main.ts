import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from '@/App.vue'
import i18n from '@/i18n'
import router from '@/router'
import '@/assets/app.css'

createApp(App)
  .use(createPinia())
  .use(router)
  .use(i18n)
  .mount('#app')

// The service worker exists only on the web target. Dynamic import inside
// the branch, so the mobile bundle never contains it (see vite.config.ts).
if (__WEB_TARGET__) {
  import('@/lib/updates').then(({ watchForUpdates }) => watchForUpdates())
}
