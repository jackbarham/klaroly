import type { App } from 'vue'
import CheckboxInput from '@/components/form/CheckboxInput.vue'
import DateInput from '@/components/form/DateInput.vue'
import FormActions from '@/components/form/FormActions.vue'
import FormError from '@/components/form/FormError.vue'
import FormField from '@/components/form/FormField.vue'
import FormSection from '@/components/form/FormSection.vue'
import MoneyInput from '@/components/form/MoneyInput.vue'
import RadioCard from '@/components/form/RadioCard.vue'
import RadioGroup from '@/components/form/RadioGroup.vue'
import SelectInput from '@/components/form/SelectInput.vue'
import TextArea from '@/components/form/TextArea.vue'
import TextInput from '@/components/form/TextInput.vue'
import ToggleSwitch from '@/components/form/ToggleSwitch.vue'
import AnchoredSheet from '@/components/ui/AnchoredSheet.vue'
import AppButton from '@/components/ui/AppButton.vue'
import Card from '@/components/ui/Card.vue'
import DataTable from '@/components/ui/DataTable.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import Icon from '@/components/ui/Icon.vue'
import IconButton from '@/components/ui/IconButton.vue'
import ListRow from '@/components/ui/ListRow.vue'
import Notice from '@/components/ui/Notice.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SectionBand from '@/components/ui/SectionBand.vue'
import Sheet from '@/components/ui/Sheet.vue'
import StatusPill from '@/components/ui/StatusPill.vue'

// The UI kit and the form kit, registered once on the app so that a screen
// writes <AppButton> or <FormField> without importing it. Every other
// component, the shell, the auth card, the banners, is imported where it is
// used, because each of those belongs to one place.
//
// global.d.ts beside this file tells vue-tsc what the names mean, so a wrong
// prop on a global component is still a type error. Add a component to both
// lists in the same change, and to the kitchen sink.
export const kit = {
  AnchoredSheet,
  AppButton,
  Card,
  CheckboxInput,
  DataTable,
  DateInput,
  EmptyState,
  FormActions,
  FormError,
  FormField,
  FormSection,
  Icon,
  IconButton,
  ListRow,
  MoneyInput,
  Notice,
  PageHeader,
  RadioCard,
  RadioGroup,
  SectionBand,
  SelectInput,
  Sheet,
  StatusPill,
  TextArea,
  TextInput,
  ToggleSwitch,
}

export function installKit(app: App): void {
  for (const [name, component] of Object.entries(kit)) {
    app.component(name, component)
  }
}
