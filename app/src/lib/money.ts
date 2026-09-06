// Formatting an amount, which four screens now do and which was written out
// four times.
//
// Money is a minor-unit integer beside its currency everywhere it travels
// (decision 77) and becomes a string only at the edge, which is here. The
// pence are dropped when there are none, because "£680" is what an artist
// writes on an invoice and "£680.00" is what a spreadsheet does.
//
// It takes vue-i18n's `n` rather than importing the composer, so a plain
// function can be tested without mounting anything and the number formats stay
// in src/i18n where the rest of them are.

export type FormatNumber = (value: number, options: { key: string, currency: string }) => string

export function formatMoney(n: FormatNumber, minor: number, currency: string): string {
  return n(minor / 100, {
    key: minor % 100 === 0 ? 'currency_whole' : 'currency',
    currency,
  })
}
