<template>
  <div class="mini-card">
    <div class="mc-head">
      <div class="title">Profit-Center
            <span class="muted">({{ total }})</span>
        </div>
      <div class="actions">
        
        <Button label="Neu" icon="pi pi-plus" size="small" @click="$emit('create')" />
      </div>
    </div>

    <div class="mc-search">
      <span class="pi pi-search"></span>
      <input class="input" v-model="q" placeholder="Suchen…" />
    </div>

    <div class="mc-list">
      <div v-for="p in filtered" :key="p._code" class="row">
        <div class="left">
          <div class="name">{{ p._name }}</div>
        </div>
        <div class="middle">
          <span class="badge">
            <i class="pi pi-users"></i>
            <span>{{ counts[p._code] ?? 0 }}</span>
          </span>
        </div>
        <div class="right">
          <Button
            icon="pi pi-eye"
            text
            rounded
            @click="$emit('view', p)"
            v-tooltip.top="'Ansehen'"
          />
          <Button
            icon="pi pi-pencil"
            text
            rounded
            @click="$emit('edit', p)"
            v-tooltip.top="'Bearbeiten'"
          />
        </div>
      </div>
    </div>

    <div v-if="filtered.length === 0" class="empty muted">Keine Treffer</div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Button from 'primevue/button'

const props = defineProps({
  items: { type: Array, default: () => [] },
  total: { type: Number, default: 0 },
  counts: { type: Object, default: () => ({}) }
})

/* Normaliza nombre/código sin importar el shape que devuelva la API */
const normalized = computed(() =>
  (props.items || []).map((p) => {
    const code = p.profit_center_code ?? p.code ?? p.id ?? null
    const name = p.profit_center_name ?? p.name ?? (code != null ? `PC ${code}` : 'Unbenannt')
    return { ...p, _code: String(code ?? ''), _name: String(name) }
  })
)

const q = ref('')
const filtered = computed(() => {
  const s = q.value.trim().toLowerCase()
  if (!s) return normalized.value
  return normalized.value.filter(
    (p) => p._name.toLowerCase().includes(s) || p._code.toLowerCase().includes(s)
  )
})
</script>

<style scoped>
.mini-card {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.mc-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.title {
  font-weight: 800;
}
.actions {
  display: flex;
  align-items: center;
  gap: 10px;
}
.muted {
  color: var(--muted);
  font-weight: 300;
  font-size: .75rem;
  margin-left: .5rem;
}

.mc-search {
  position: relative;
}
.mc-search .pi {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
}
.mc-search .input {
  padding-left: 32px;
}

.mc-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: calc(100vh - var(--navbar-h) - 260px);
  overflow: auto;
  padding-right: 4px;
}
.row {
  display: grid;
  grid-template-columns: 1fr auto auto; /* left | middle | right */
  gap: 10px;
  align-items: center;
  padding: 8px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: color-mix(in oklab, var(--surface) 90%, transparent);
}
.left .name {
  font-weight: 500;          /* más delgada */
  font-size: 0.95rem;        /* más chica */
  line-height: 1.2;
}
.middle .badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 8px;
  border-radius: 999px;
  border: 1px solid var(--border);
  color: var(--text);
  background: color-mix(in oklab, var(--surface) 85%, transparent);
  font-size: 0.85rem;
}
.right {
  display: flex;
  gap: 6px;
}
.empty {
  text-align: center;
  padding: 8px 0;
}
</style>