import { oneOf, permutationOf, readSettings as read, writeSettings as write, type Checks } from '@/lib/viewSettings'
import type { PeriodKey } from '@/types/home'

// The three things this device remembers about the home screen.
//
// The mechanism is src/lib/viewSettings.ts, shared with the contacts and
// enquiries menus. None of the values is: nothing there orders blocks or picks
// a money period, and nothing here sorts by staleness or hides an ending. That
// is the split those files were written to keep, and this is the third caller
// to keep it.

/**
 * The three blocks, in the order they are drawn.
 *
 * Next up leads by default on a tone judgement rather than a measurement: the
 * first thing an artist meets should be the next two weddings, not a list of
 * things they have not done. Both arrangements were measured and both put
 * something from every block above the fold once Attention is capped, so once
 * both work the pleasant one wins.
 */
export type BlockKey = 'next' | 'attention' | 'money'

/**
 * How many attention rows the phone previews before "See all".
 *
 * Four is the default because it leaves room for something else: with Next up
 * leading, three weddings plus the Attention heading, its first band and two
 * rows fit one 375px screen. 'all' turns the cap off.
 */
export type PreviewCount = 3 | 4 | 6 | 'all'

export interface HomeViewSettings {
  order: BlockKey[]
  previewCount: PreviewCount
  /**
   * Which period the money figures are for.
   *
   * **Stored here and deliberately not shown in Adjust.** It was in the sheet
   * for a round and came out: one value with two homes is two places to keep in
   * step, and a "default" that differs from the current view is an artist who
   * cannot work out why the block keeps changing back. A setting earns a second
   * home when it is hard to reach, and the block's own selector is on screen.
   */
  period: PeriodKey
}

const storageKey = 'klaroly.home.view'

export const blockKeys: BlockKey[] = ['next', 'attention', 'money']

export const previewCounts: PreviewCount[] = [3, 4, 6, 'all']

export const defaultSettings: HomeViewSettings = {
  order: ['next', 'attention', 'money'],
  previewCount: 4,
  period: 'this_month',
}

// One check per field, so an order written by an older build, or a period key
// that has since been renamed, cannot reach the render.
const checks: Checks<HomeViewSettings> = {
  order: permutationOf<BlockKey>(...blockKeys),
  previewCount: oneOf<PreviewCount>(...previewCounts),
  period: oneOf<PeriodKey>('this_month', 'three_months', 'twelve_months', 'business_year'),
}

export function readSettings(): HomeViewSettings {
  return read(storageKey, defaultSettings, checks)
}

export function writeSettings(settings: HomeViewSettings): void {
  write(storageKey, settings)
}

/**
 * The cap to apply to the attention list, or null for no cap.
 *
 * Null at 'all', and null is what the full list on /attention always passes.
 */
export function previewLimit(count: PreviewCount): number | null {
  return count === 'all' ? null : count
}
