<template>
  <div>
    <PageHeader
      :title="t('settings.travel')"
      :description="t('travel.description')"
      :back-to="{ name: 'settings' }"
    />

    <form
      class="space-y-12"
      novalidate
      @submit.prevent="save"
    >
      <FormSection
        :title="t('travel.base_section_title')"
        :description="t('travel.base_section_description')"
      >
        <FormField
          v-slot="field"
          :label="t('travel.postcode_label')"
          :hint="t('travel.postcode_hint')"
        >
          <TextInput
            v-bind="field"
            v-model="postcode"
            autocomplete="postal-code"
          />
        </FormField>
      </FormSection>

      <FormSection
        :title="t('travel.charges_section_title')"
        :description="t('travel.charges_section_description')"
      >
        <FormField
          v-slot="field"
          :label="t('travel.charge_label')"
          :hint="t('travel.charge_hint')"
        >
          <ToggleSwitch
            v-bind="field"
            v-model="chargeForTravel"
          />
        </FormField>

        <FormField
          v-slot="field"
          :label="t('travel.rule_label')"
          :hint="t('travel.rule_hint')"
        >
          <SelectInput
            v-bind="field"
            v-model="rule"
            :options="ruleOptions"
          />
        </FormField>

        <FormField
          v-slot="field"
          :label="t('travel.rate_label')"
          :hint="t('travel.rate_hint')"
        >
          <MoneyInput
            v-bind="field"
            v-model="ratePerMile"
            currency="£"
          />
        </FormField>

        <FormField
          v-slot="field"
          :label="t('travel.notes_label')"
          :hint="t('travel.notes_hint')"
        >
          <TextArea
            v-bind="field"
            v-model="notes"
          />
        </FormField>
      </FormSection>

      <FormActions @save="save" />
    </form>
  </div>
</template>

<script setup lang="ts">
// The one page that shows the form kit doing real work. Nothing here is
// saved and nothing is validated: the settings API arrives later, and this
// page exists so that the shape of a section is settled before ten of them
// are written.
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import FormActions from '@/components/form/FormActions.vue'
import FormField from '@/components/form/FormField.vue'
import FormSection from '@/components/form/FormSection.vue'
import MoneyInput from '@/components/form/MoneyInput.vue'
import SelectInput from '@/components/form/SelectInput.vue'
import TextArea from '@/components/form/TextArea.vue'
import TextInput from '@/components/form/TextInput.vue'
import ToggleSwitch from '@/components/form/ToggleSwitch.vue'
import PageHeader from '@/components/ui/PageHeader.vue'

const { t } = useI18n()

const postcode = ref('')
const chargeForTravel = ref(true)
const rule = ref('per_mile')
const ratePerMile = ref('')
const notes = ref('')

const ruleOptions = computed(() => [
  { value: 'included', label: t('travel.rule_option_included') },
  { value: 'per_mile', label: t('travel.rule_option_per_mile') },
  { value: 'after_free_miles', label: t('travel.rule_option_after_free_miles') },
])

function save(): void {
  // TODO: saving travel settings needs the settings API, which is a later
  // prompt. The button is here so the row can be seen in place.
}
</script>
