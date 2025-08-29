<template>
  <Breadcrumb :home="home" :model="items" class="small-crumbs w-full p-0">
    <template #item="{ item }">
      <a href="#" class="flex align-items-center gap-2 cursor-pointer" @click.prevent="onClick(item)">
        <span :class="item.icon"></span>
        <span class="crumb-label">{{ item.label }}</span>
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

function findPath(list, key, acc = []) {
  if (!Array.isArray(list)) return null
  for (const n of list) {
    const t = [...acc, n]
    if (n.key === key) return t
    const hit = findPath(n.children, key, t)
    if (hit) return hit
  }
  return null
}

function iconFor(node) {
  const t = node?.data?.type
  if (t === 'company') return 'pi pi-home'
  if (t === 'team')    return 'pi pi-sitemap'
  if (t === 'user')    return 'pi pi-user'
  if (t === 'pc')      return 'pi pi-database'
  if (t === 'client')  return 'pi pi-building'
  return 'pi pi-circle'
}

const path = computed(() => props.selectedKey ? (findPath(props.nodes, props.selectedKey) || []) : [])

const home = computed(() => {
  const root = path.value[0]
  return {
    icon: 'pi pi-home',
    label: root?.label || 'Company',
    key: root?.key || 'company_main',
    url: '#'
  }
})

const items = computed(() => {
  const p = path.value.slice(1)
  return p.map(n => ({
    icon: iconFor(n),
    label: n.label,
    key: n.key,
    url: '#'
  }))
})

function onClick(item) {
  emit('navigate', item.key || 'company_main')
}
</script>

<style scoped>
.small-crumbs :deep(.p-breadcrumb) {
  padding: 0;
  margin: 0%;
}
.crumb-label { color: var(--text-color); }
</style>