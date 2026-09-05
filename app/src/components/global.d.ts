// The components kit.ts registers on the app, declared for vue-tsc so that a
// template can use them without an import and still have its props checked.
import type { kit } from '@/components/kit'

declare module 'vue' {
  export interface GlobalComponents {
    AppButton: typeof kit.AppButton
    Card: typeof kit.Card
    CheckboxInput: typeof kit.CheckboxInput
    DataTable: typeof kit.DataTable
    DateInput: typeof kit.DateInput
    EmptyState: typeof kit.EmptyState
    FormActions: typeof kit.FormActions
    FormError: typeof kit.FormError
    FormField: typeof kit.FormField
    FormSection: typeof kit.FormSection
    Icon: typeof kit.Icon
    IconButton: typeof kit.IconButton
    ListRow: typeof kit.ListRow
    MoneyInput: typeof kit.MoneyInput
    PageHeader: typeof kit.PageHeader
    RadioCard: typeof kit.RadioCard
    RadioGroup: typeof kit.RadioGroup
    SectionBand: typeof kit.SectionBand
    SelectInput: typeof kit.SelectInput
    Sheet: typeof kit.Sheet
    StatusPill: typeof kit.StatusPill
    TextArea: typeof kit.TextArea
    TextInput: typeof kit.TextInput
    ToggleSwitch: typeof kit.ToggleSwitch
  }
}

export {}
