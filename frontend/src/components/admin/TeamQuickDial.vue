<template>
  <SpeedDial
    :model="dialItems"
    direction="left"
    type="linear"
    showIcon="pi pi-users"
    hideIcon="pi pi-times"
    buttonClass="p-button-rounded p-button-help"
  />
</template>

<script setup>
// Code & comments in English
import { computed } from 'vue'
import SpeedDial from 'primevue/speeddial'

const props = defineProps({
  user: { type: Object, required: true },   // expects { id, teamIds?: number[] }
  teams: { type: Array, required: true }    // [{ id, name }]
})
const emit = defineEmits(['changeTeams'])

// Abbreviation from team name (e.g., "Hochbau" -> "HB", "Alpha" -> "A")
function abbr(name='') {
  const parts = String(name).trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0,2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}

const current = computed(() => new Set(props.user.teamIds || []))

const dialItems = computed(() => (props.teams || []).map(t => {
  const member = current.value.has(t.id)
  return {
    // Tooltip like "Alpha (A)" or "HB"
    label: member ? `Entfernen: ${t.name} (${abbr(t.name)})` : `Hinzufügen: ${t.name} (${abbr(t.name)})`,
    // Use colored circle icon; keep simple for now
    icon: member ? 'pi pi-check-circle' : 'pi pi-circle',
    class: member ? 'p-button-success' : 'p-button-secondary',
    command: () => {
      const set = new Set(props.user.teamIds || [])
      if (member) set.delete(t.id); else set.add(t.id)
      emit('changeTeams', { userId: props.user.id, teamIds: Array.from(set) })
    }
  }
}))
</script>
