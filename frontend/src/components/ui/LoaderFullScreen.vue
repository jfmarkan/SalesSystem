<!-- components/ui/LoaderFullscreen.vue -->
<template>
  <div class="page-loader">
    <div class="glass"></div>
    <div class="spinner-content">
      <ProgressSpinner style="width: 48px; height: 48px" strokeWidth="4" />
      <div class="loader-text">Lädt Daten…</div>
    </div>
  </div>
</template>

<script setup>
import ProgressSpinner from 'primevue/progressspinner'
</script>

<style scoped>
.page-loader {
  position: fixed;
  top: var(--navbar-h);     /* 👈 respeta el alto de la navbar */
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  display: grid;
  place-items: center;
  background: color-mix(in oklab, var(--bg) 40%, transparent);
  backdrop-filter: saturate(var(--glass-sat)) blur(6px);
}

.spinner-content {
  z-index: 1000;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
}

.loader-text {
  font-size: 0.9rem;
  color: var(--text);
  opacity: 0.85;
}

.page-loader .p-progress-spinner-circle {
  animation: spinnerColor 1.8s infinite linear;
}

@keyframes spinnerColor {
  0% { stroke: #0ea5e9; }
  33% { stroke: #10b981; }
  66% { stroke: #ef4444; }
  100% { stroke: #0ea5e9; }
}
</style>
