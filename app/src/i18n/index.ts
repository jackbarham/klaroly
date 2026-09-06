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
  // Money, so that no screen ever builds an amount by putting a pound sign in
  // front of a number. The currency here is only the default: every amount in
  // the app sits beside its own ISO 4217 code and passes it, because an
  // account can be billing in euros.
  //
  // currency_whole is the same format with the pence dropped, and it is used
  // only where the amount is already a whole number of pounds: a pill reading
  // "Owes £450" rather than "Owes £450.00" at 12px. Anything with pence in it
  // takes the full format, because rounding somebody's balance to make a pill
  // tidier is lying about what they owe.
  numberFormats: {
    'en-GB': {
      currency: { style: 'currency', currency: 'GBP' },
      currency_whole: { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 },
    },
  },
})

export default i18n
