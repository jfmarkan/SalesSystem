<template>
  <ComponentTitle>{{ trailText }}</ComponentTitle>
</template>

<script setup>
import { computed } from 'vue'
import ComponentTitle from '@/components/titles/ComponentTitle.vue'

const props = defineProps({
  nodes: { type: Array,  required: true },    // árbol completo (primevue Tree value)
  selectedKey: { type: String, default: '' }  // key del nodo seleccionado
})

function findPath(list, key, acc = []) {
  if (!Array.isArray(list)) return null
  for (const n of list) {
    const trail = [...acc, n]
    if (n.key === key) return trail
    const childTrail = findPath(n.children, key, trail)
    if (childTrail) return childTrail
  }
  return null
}

const trail = computed(() => props.selectedKey ? (findPath(props.nodes, props.selectedKey) || []) : [])
const trailText = computed(() => trail.value.map(n => n.label).join(' › '))
</script>
