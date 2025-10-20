<template>
  <section class="glass card" :class="rootClass">
    <header v-if="hasHeader" class="card-header" :class="divider ? 'with-divider' : ''">
      <!-- slot header opcional; si no, usa title si existe -->
      <div>
        <slot name="header">
          <h3 v-if="title" class="card-title">{{ title }}</h3>
        </slot>
      </div>
      <div><slot name="actions" /></div>
    </header>

    <div class="card-content" :class="bodyClass">
      <slot />
    </div>
  </section>
</template>

<script setup>
// Keep comments in English
import { useSlots, computed } from 'vue'

const props = defineProps({
  title: { type: String, default: '' },
  rootClass: { type: [String, Object, Array], default: '' },
  bodyClass: { type: [String, Object, Array], default: '' },
  divider: { type: Boolean, default: true }
})

const slots = useSlots()
const hasHeader = computed(() => !!props.title || !!slots.header || !!slots.actions)
</script>

<style scoped>
/* No padding here. .card ya lo define en main.css */
.card-content { padding: 0; }

/* Solo agrega línea y espaciamiento si hay header */
.with-divider { border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 10px; }
</style>
