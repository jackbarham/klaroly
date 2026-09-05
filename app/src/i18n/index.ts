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
  // A date the way this locale writes one. It is here rather than in a
  // formatting helper so that the format follows the locale, the same way
  // every string does, on the day there is a second one.
  datetimeFormats: {
    'en-GB': {
      date: { day: 'numeric', month: 'long', year: 'numeric' },
    },
  },
})

export default i18n
