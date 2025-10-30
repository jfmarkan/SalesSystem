<template>
  <section
    class="glass card"
    :class="[{ 'is-fill': fill, 'no-body-pad': !bodyPadding }, rootClass]"
    :style="fill ? { height: '100%' } : null"
  >
    <header v-if="showHeader" class="card-header" :class="divider ? 'with-divider' : ''">
      <div class="title-wrap">
        <slot name="header">
          <h3 v-if="title" class="card-title">{{ title }}</h3>
          <p v-if="subtitle" class="card-subtitle">{{ subtitle }}</p>
        </slot>
      </div>
      <div class="actions"><slot name="actions" /></div>
    </header>

    <div class="card-content" :class="bodyClass">
      <slot />
    </div>
  </section>
</template>

<script setup>
import { useSlots, computed } from 'vue'

const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  divider: { type: Boolean, default: true },
  /** ocupa 100% del alto disponible del contenedor padre */
  fill: { type: Boolean, default: false },
  /** activa/desactiva padding del cuerpo */
  bodyPadding: { type: Boolean, default: true },
  rootClass: { type: [String, Object, Array], default: '' },
  bodyClass: { type: [String, Object, Array], default: '' }
})

const slots = useSlots()
/** Header SOLO si hay title o slots header/actions */
const showHeader = computed(() => !!props.title || !!slots.header || !!slots.actions)
</script>

<style scoped>
/* Estructura base */
.card { display:flex; flex-direction:column; min-height:0; border-radius: var(--app-radius); }
.card-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding: var(--card-pad-y,8px) var(--card-pad-x,10px); }
.card-title { margin:0; font-size:1rem; line-height:1.2; font-weight:700; color: var(--text); }
.card-subtitle { margin:.2rem 0 0; font-size:.85rem; color: var(--muted); }

/* Cuerpo flexible que rellena */
.card-content { flex:1; min-height:0; display:flex; flex-direction:column; padding: var(--card-pad,10px); }
.no-body-pad .card-content { padding: 0; }

/* Detalles */
.with-divider { border-bottom: 1px solid var(--border); }
.is-fill { height: 100%; }
</style>
