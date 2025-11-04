<template>
  <Breadcrumb :model="breadcrumbItems" class="small-crumbs w-full p-0">
    <template #item="{ item }">
      <a href="#" class="flex align-items-center gap-2 cursor-pointer" @click.prevent="onClick(item)">
        <i :class="item.icon" class="crumb-icon" />
        <span v-if="item.isLast" class="crumb-label">
          {{ item.label }}
        </span>
      </a>
    </template>
    <template #separator> / </template>
  </Breadcrumb>
</template>

<script setup>
import { computed } from 'vue'
import Breadcrumb from 'primevue/breadcrumb'

const props = defineProps({
  nodes: { type: Array, required: true },
  selectedKey: { type: String, default: '' }
})
const emit = defineEmits(['navigate'])

// 🔁 Encuentra el camino desde raíz hasta nodo seleccionado
function findPath(list, key, acc = []) {
  for (const node of list) {
    const path = [...acc, node]
    if (node.key === key) return path
    if (node.children) {
      const result = findPath(node.children, key, path)
      if (result) return result
    }
  }
  return null
}

// Iconos por tipo
function iconFor(node) {
  const t = node?.data?.type
  if (t === 'company') return 'pi pi-home'
  if (t === 'team')    return 'pi pi-sitemap'
  if (t === 'user')    return 'pi pi-user'
  if (t === 'pc')      return 'pi pi-database'
  if (t === 'client')  return 'pi pi-building'
  return 'pi pi-circle'
}

// Breadcrumb listo para el template
const breadcrumbItems = computed(() => {
  const path = findPath(props.nodes, props.selectedKey) || []

  return path.map((n, idx, arr) => ({
    icon: iconFor(n),
    label: n.label,
    key: n.key,
    isLast: idx === arr.length - 1,
    command: () => emit('navigate', n.key)
  }))
})

function onClick(item) {
  item.command?.()
}
</script>

<style scoped>
.small-crumbs :deep(.p-breadcrumb) {
  padding: 0;
  margin: 0;
}

.crumb-icon {
  font-size: 1rem;
  color: var(--text-color-secondary);
}

.crumb-label {
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--text-color);
  margin-left: 0.25rem; 
}
</style>