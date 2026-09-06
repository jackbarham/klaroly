import { boolean, oneOf, readSettings as read, writeSettings as write, type Checks } from '@/lib/viewSettings'

// The four things the contacts view menu changes.
//
// The mechanism is src/lib/viewSettings.ts, which the enquiries menu uses too:
// the wrapped accessor, the field-by-field checking and the write that
// swallows its own failure are the same in both and would drift if they were
// written twice. What is here is this screen's own values, and none of them is
// shared: nothing in the enquiries menu sorts by recency or leads with a
// booking.
//
// This file keeps its own readSettings and writeSettings rather than making
// every caller pass the key and the checks, so the store reads exactly as it
// did and this remains the one place a contacts setting is described.

// How the list is ordered. Recency is the working order, with the next job at
// the top; A to Z is for finding somebody whose name you know.
export type SortMode = 'recent' | 'alpha'

// Which of the row's two lines is the strong one. In this business the
// memorable handle is often the job rather than the person, so the wedding can
// take the top line and the name can drop to the muted one. Nothing else about
// the row moves.
export type LeadWith = 'name' | 'booking'

export interface ViewSettings {
  sort: SortMode
  leadWith: LeadWith
  showInitials: boolean
  showAmounts: boolean
}

const storageKey = 'klaroly.contacts.view'

export const defaultSettings: ViewSettings = {
  sort: 'recent',
  leadWith: 'name',
  showInitials: true,
  showAmounts: true,
}

// One check per field, so a `sort` of "surname" written by an older build, or
// by something else on this origin, cannot reach the sort function.
const checks: Checks<ViewSettings> = {
  sort: oneOf('recent', 'alpha'),
  leadWith: oneOf('name', 'booking'),
  showInitials: boolean,
  showAmounts: boolean,
}

export function readSettings(): ViewSettings {
  return read(storageKey, defaultSettings, checks)
}

export function writeSettings(settings: ViewSettings): void {
  write(storageKey, settings)
}
