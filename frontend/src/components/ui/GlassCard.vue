<template>
  <section class="glass card" :class="rootClass">
    <header v-if="title" class="card-header">
      <h3 class="card-title">{{ title }}</h3>
      <slot name="actions" />
    </header>
    <div class="card-content">
      <slot />
    </div>
  </section>
</template>

<script setup>
// Keep comments in English
const props = defineProps({
  title: { type: String, default: '' },
  rootClass: { type: [String, Object, Array], default: '' }
})
</script>

<style scoped>
.card { padding: 12px; }
.card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding-bottom: 8px; margin-bottom: 10px; border-bottom: 1px solid var(--border);
}
.card-title { margin: 0; font-size: 1rem; font-weight: 600; color: var(--text); }
</style>
