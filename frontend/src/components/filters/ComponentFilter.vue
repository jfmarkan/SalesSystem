<script setup>
/* Reusable filters. Model + emits. UI: German. Comments: English. */
import RadioButton from 'primevue/radiobutton'
import Dropdown from 'primevue/dropdown'
import Listbox from 'primevue/listbox'
import Button from 'primevue/button'

const props = defineProps({
  mode: { type: String, required: true },              // 'kunde' | 'pc'
  primaryOptions: { type: Array, required: true },     // list for dropdown
  primaryId: { type: [Number, String, null], required: true },
  secondaryOptions: { type: Array, required: true },   // dependent list
  secondaryId: { type: [Number, String, null], required: true }
})
const emit = defineEmits(['update:mode','update:primary-id','update:secondary-id','next'])
</script>

<template>
  <div>
    <div class="mb-3 font-medium">Filtern nach</div>
    <div class="flex align-items-center mb-2">
      <RadioButton inputId="rb-kunde" name="mode" value="kunde" :modelValue="props.mode"
                   @update:modelValue="v=>emit('update:mode',v)" />
      <label for="rb-kunde" class="ml-2">Kunde</label>
    </div>
    <div class="flex align-items-center mb-3">
      <RadioButton inputId="rb-pc" name="mode" value="pc" :modelValue="props.mode"
                   @update:modelValue="v=>emit('update:mode',v)" />
      <label for="rb-pc" class="ml-2">Profit Center</label>
    </div>

    <div class="mb-3">
      <label class="block text-sm text-500 mb-1">Auswahl</label>
      <Dropdown class="w-full" :options="primaryOptions" optionLabel="label" optionValue="value"
                :modelValue="primaryId" @update:modelValue="v=>emit('update:primary-id',v)" placeholder="Wählen" />
    </div>

    <div class="mb-3">
      <label class="block text-sm text-500 mb-1">
        {{ props.mode==='kunde' ? 'Profit Center des Kunden' : 'Kunden im Profit Center' }}
      </label>
      <Listbox class="w-full" :options="secondaryOptions" optionLabel="label" optionValue="value"
               :modelValue="secondaryId" @update:modelValue="v=>emit('update:secondary-id',v)"
               listStyle="max-height: 50vh" />
    </div>

    <Button class="w-full" label="Weiter" icon="pi pi-arrow-right" @click="emit('next')" />
  </div>
</template>