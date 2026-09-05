import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from '@/App.vue'
import { installKit } from '@/components/kit'
import i18n from '@/i18n'
import router from '@/router'
import '@/assets/app.css'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(i18n)
installKit(app)
app.mount('#app')

// The service worker exists only on the web target. Dynamic import inside
// the branch, so the mobile bundle never contains it (see vite.config.ts).
if (__WEB_TARGET__) {
  import('@/lib/updates').then(({ watchForUpdates }) => watchForUpdates())
}
