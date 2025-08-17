<!-- src/components/filters/ComponentFilter.vue -->
<script setup>
/* UI DE; lógica simple controlada por el padre. */
import { computed } from 'vue'
import RadioButton from 'primevue/radiobutton'
import Dropdown from 'primevue/dropdown'
import Listbox from 'primevue/listbox'
import Button from 'primevue/button'

const props = defineProps({
  mode: { type: String, required: true },                 // '' | 'client' | 'pc'
  primaryOptions: { type: Array, required: true },        // [{label,value}]
  primaryId: { type: [Number, String, null], required: true },
  secondaryOptions: { type: Array, required: true },      // [{label,value}]
  secondaryId: { type: [Number, String, null], required: true }
})
const emit = defineEmits(['update:mode','update:primary-id','update:secondary-id','next'])

const m = computed({
  get: () => props.mode,
  set: v => emit('update:mode', v)
})
const pid = computed({
  get: () => props.primaryId,
  set: v => emit('update:primary-id', v)
})
const sid = computed({
  get: () => props.secondaryId,
  set: v => emit('update:secondary-id', v)
})
</script>

<template>
  <div class="filter-wrap">
    <div class="mb-2 text-600 text-sm">Filtern nach</div>
    <div class="flex align-items-center gap-3 mb-3">
      <div class="flex align-items-center gap-2">
        <RadioButton inputId="rb-client" v-model="m" value="client" name="mode" />
        <label for="rb-client">Kunde</label>
      </div>
      <div class="flex align-items-center gap-2">
        <RadioButton inputId="rb-pc" v-model="m" value="pc" name="mode" />
        <label for="rb-pc">Profit Center</label>
      </div>
    </div>

    <div class="mb-3">
      <label class="block text-600 text-sm mb-1">Auswahl</label>
      <Dropdown class="w-full" v-model="pid" :options="primaryOptions" optionLabel="label" optionValue="value"
                placeholder="Wählen" :disabled="!m" />
    </div>

    <div class="mb-3">
      <label class="block text-600 text-sm mb-1">
        {{ m==='client' ? 'Profit Center des Kunden' : 'Kunden im Profit Center' }}
      </label>
      <Listbox class="w-full" v-model="sid" :options="secondaryOptions" optionLabel="label" optionValue="value"
               listStyle="max-height: 50vh" :disabled="!m || pid==null" />
    </div>

    <Button class="w-full" label="Weiter" icon="pi pi-arrow-right" @click="$emit('next')" :disabled="!sid" />
  </div>
</template>

<style scoped>
.filter-wrap { width: 100%; }
</style>