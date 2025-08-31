<script setup>
const props = defineProps({
  title: { type: String, default: '' }
})
</script>

<template>
  <div class="glass-card h-full">
    <header v-if="title" class="card-header">
      <h3 class="title">{{ title }}</h3>
      <div class="header-extra">
        <slot name="header-extra" />
      </div>
    </header>
    <div class="card-body">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.glass-card{
  display:flex;
  flex-direction:column;
  height:100%;
  gap:0;
  padding:0;
  border-radius:12px;
  border:1px solid rgba(0,0,0,.06);
  box-shadow:0 10px 30px rgba(2,6,23,.06), 0 2px 6px rgba(2,6,23,.04);

  /* Light por defecto */
  background: rgba(255,255,255,0.4);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  color:#0f172a;
}

@media (prefers-color-scheme: dark) {
  .glass-card{
    background: rgba(0,0,0,0.4);
    color:#f8fafc;
    border-color: rgba(255,255,255,.12);
    box-shadow:0 10px 30px rgba(0,0,0,.35), 0 2px 6px rgba(0,0,0,.2);
  }
}
:global(.dark) .glass-card{
  background: rgba(0,0,0,0.4);
  color:#f8fafc;
  border-color: rgba(255,255,255,.12);
  box-shadow:0 10px 30px rgba(0,0,0,.35), 0 2px 6px rgba(0,0,0,.2);
}

/* Header .5rem 1rem */
.card-header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:8px 16px;
  border-bottom:1px solid rgba(2,6,23,.10);
}
@media (prefers-color-scheme: dark) { .card-header{ border-bottom-color: rgba(255,255,255,.16); } }
:global(.dark) .card-header{ border-bottom-color: rgba(255,255,255,.16); }

.title{
  margin:0;
  font-size:.95rem;
  line-height:1.2;
  font-weight:600;
}
.header-extra{ display:flex; align-items:center; gap:6px; }

/* Body mitad del header: .25rem .5rem */
.card-body{
  flex:1 1 auto;
  min-height:0;
  overflow:auto;
  padding:4px 8px;
}

/* Toggle unidad */
:deep(.unit-toggle){
  margin:0;
  background: rgba(255,255,255,.35);
  border:1px solid rgba(0,0,0,.08);
  border-radius:8px;
  padding:2px;
  display:flex; gap:6px;
}
@media (prefers-color-scheme: dark) {
  :deep(.unit-toggle){ background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.14); }
}
:global(.dark) :deep(.unit-toggle){ background: rgba(255,255,255,.08); border-color: rgba(255,255,255,.14); }
:deep(.u-btn){ border:0; background:transparent; padding:.25rem .5rem; font-size:.8rem; cursor:pointer; border-radius:6px; }
:deep(.u-btn.active){ background: rgba(31,86,115,.9); color:#fff; font-weight:700; }
</style>
