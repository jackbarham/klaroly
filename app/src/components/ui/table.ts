// A table's rows and cells as class strings rather than as components, so that
// a caller writes ordinary table markup and every table in the app still looks
// the same. This is the same shape as src/components/form/field.ts, which does
// the job for form controls.
//
// The alternative was a scoped style block inside DataTable using :deep() to
// reach rows the parent owns. It is fewer characters at the call site and it
// is a trick a reader has to know, so it is not what this does.

export interface TableColumn {
  key: string
  label: string
  // The last column is usually a figure, and a figure reads right-aligned.
  align?: 'start' | 'end'
}

// The hover is the divider the row already has, recoloured. Nothing is added,
// nothing is filled and the row does not move: a second line above a grey one
// reads as three pixels of rule and pulls the eye out of the table.
export const tableRowClasses = 'border-b border-border transition-colors hover:border-accent'

// The first and last cells are flush with the edges of the table, so the right
// padding comes off the last one.
export const tableCellClasses = 'py-row pr-row align-middle last:pr-0'
