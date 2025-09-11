<template>
  <SpeedDial
    :model="items"
    direction="left"
    type="linear"
    :showIcon="showIcon"
    hideIcon="pi pi-times"
    :buttonClass="buttonClass"
  />
</template>

<script setup>
import { computed } from 'vue'
import SpeedDial from 'primevue/speeddial'

const props = defineProps({ user: { type: Object, required: true } })
const emit = defineEmits(['change'])
const isDisabled = computed(() => !!props.user.disabled)
const showIcon = computed(() => (isDisabled.value ? 'pi pi-times' : 'pi pi-check'))
const buttonClass = 'p-button-rounded p-button-secondary'

const items = computed(() => ([
  { label: 'Aktivieren', icon: 'pi pi-check', class: 'p-button-rounded p-button-success',
    command: () => emit('change', { userId: props.user.id, disabled: false }) },
  { label: 'Sperren', icon: 'pi pi-times', class: 'p-button-rounded p-button-danger',
    command: () => emit('change', { userId: props.user.id, disabled: true }) },
]))
</script>
