import { createI18n } from 'vue-i18n'
import enGB from '@/locales/en-GB.json'

// Every user-facing string is a key in src/locales/en-GB.json. Key names
// describe meaning (auth.sign_in_action), never the wording.
const i18n = createI18n({
  legacy: false,
  locale: 'en-GB',
  fallbackLocale: 'en-GB',
  messages: {
    'en-GB': enGB,
  },
})

export default i18n
