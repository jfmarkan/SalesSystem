<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: { type: String, default: 'Extra Quotas' },
  unit: { type: String, default: 'M3' }, // 'M3' | 'EUR' | 'VKEH' | ...
  target: { type: Number, default: 0 }, // total asignado (opcional)
  achieved: { type: Number, default: 0 }, // total usado (opcional)
  // items admite legacy: current=usado, target=asignado
  items: { type: Array, default: () => [] }, // [{ name, assigned, used }]
})

function unitLabel(u) {
  const U = String(u || '').toUpperCase()
  if (U === 'M3') return 'm³'
  if (U === 'EUR') return '€'
  if (U === 'VKEH') return 'VK-EH'
  if (u === '%') return '%'
  return u || ''
}
function fmt(n) {
  const v = Number(n) || 0
  const abs = Math.abs(v)
  if (abs >= 1_000_000) return (v / 1_000_000).toFixed(2) + 'M'
  if (abs >= 1_000) return (v / 1_000).toFixed(1) + 'k'
  return v.toLocaleString(undefined, { maximumFractionDigits: 0 })
}

const itemsNorm = computed(() => {
  return (props.items || [])
    .map((it) => {
      const assigned = Number(it.assigned ?? it.target ?? 0)
      const used = Math.max(0, Number(it.used ?? it.current ?? 0))
      const available = Math.max(0, assigned - used)
      const pctAvail = assigned > 0 ? Math.min(100, (available / assigned) * 100) : 0
      return {
        name: String(it.name ?? ''),
        assigned,
        used,
        available,
        pctAvail,
      }
    })
    .sort((a, b) => b.available - a.available) // más disponibles arriba
})

const totals = computed(() => {
  const sumAssigned = itemsNorm.value.reduce((s, x) => s + x.assigned, 0)
  const sumUsed = itemsNorm.value.reduce((s, x) => s + x.used, 0)
  const totalAssigned = props.target > 0 ? props.target : sumAssigned
  const totalUsed = props.achieved >= 0 ? props.achieved : sumUsed
  const totalAvail = Math.max(0, totalAssigned - totalUsed)
  const pctAvail = totalAssigned > 0 ? Math.min(100, (totalAvail / totalAssigned) * 100) : 0
  return { totalAssigned, totalUsed, totalAvail, pctAvail }
})

function level(p) {
  // p = % disponible
  if (p >= 50) return 'ok'
  if (p >= 20) return 'mid'
  return 'low'
}
</script>

<template>
  <div class="xq-root">
    <div class="xq-title">{{ title }}</div>

    <div class="xq-row">
      <div class="xq-kpis">
        <div class="xq-value">
          {{ fmt(totals.totalAvail) }}
          <span class="xq-unit" v-if="unitLabel(unit)">{{ unitLabel(unit) }}</span>
        </div>
        <div class="xq-sub">
          Asignado: {{ fmt(totals.totalAssigned) }}
          <span v-if="unitLabel(unit)">{{ unitLabel(unit) }}</span>
          · Usado: {{ fmt(totals.totalUsed) }}
          <span v-if="unitLabel(unit)">{{ unitLabel(unit) }}</span>
        </div>
      </div>
      <div class="xq-badge" :class="level(totals.pctAvail)">
        <span>{{ Math.round(totals.pctAvail) }}%</span>
      </div>
    </div>

    <div class="xq-bar">
      <div
        class="xq-bar__fill"
        :class="level(totals.pctAvail)"
        :style="{ width: totals.pctAvail + '%' }"
      ></div>
    </div>

    <div class="xq-list" v-if="itemsNorm.length">
      <div class="xq-item" v-for="it in itemsNorm" :key="it.name">
        <div class="xq-item__head">
          <div class="xq-item__name">{{ it.name }}</div>
          <div class="xq-item__val">
            {{ fmt(it.available) }}
            <span v-if="unitLabel(unit)">{{ unitLabel(unit) }}</span>
          </div>
        </div>
        <div class="xq-meta">
          <span>Asig: {{ fmt(it.assigned) }}</span>
          <span>Usado: {{ fmt(it.used) }}</span>
        </div>
        <div class="xq-mini">
          <div
            class="xq-mini__fill"
            :class="level(it.pctAvail)"
            :style="{ width: it.pctAvail + '%' }"
          ></div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.xq-root {
  display: flex;
  flex-direction: column;
  height: 100%;
  gap: 0.6rem;
  padding: 10px 12px; /* aire interno propio */
}

/* título sutil */
.xq-title {
  font-size: 0.9rem;
  line-height: 1.2;
  font-weight: 500;
  color: #334155;
}
@media (prefers-color-scheme: dark) {
  .xq-title {
    color: #e5e7eb;
  }
}
:global(.dark) .xq-title {
  color: #e5e7eb;
}

/* header valores */
.xq-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}
.xq-kpis {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.xq-value {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  line-height: 1;
}
@media (prefers-color-scheme: dark) {
  .xq-value {
    color: #f8fafc;
  }
}
:global(.dark) .xq-value {
  color: #f8fafc;
}
.xq-unit {
  font-size: 0.95rem;
  font-weight: 600;
  opacity: 0.85;
}
.xq-sub {
  font-size: 0.85rem;
  color: #64748b;
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
@media (prefers-color-scheme: dark) {
  .xq-sub {
    color: #cbd5e1;
  }
}
:global(.dark) .xq-sub {
  color: #cbd5e1;
}

/* badge % disponible */
.xq-badge {
  min-width: 3.25rem;
  height: 2rem;
  padding: 0 0.5rem;
  border-radius: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  background: linear-gradient(to bottom, #94a3b8, #475569);
}
.xq-badge.ok {
  background: linear-gradient(to bottom, #34d399, #059669);
}
.xq-badge.mid {
  background: linear-gradient(to bottom, #fb923c, #ea580c);
}
.xq-badge.low {
  background: linear-gradient(to bottom, #f87171, #dc2626);
}

/* barra principal (disponible) */
.xq-bar {
  position: relative;
  height: 10px;
  border-radius: 999px;
  overflow: hidden;
  background: rgba(2, 6, 23, 0.08);
}
@media (prefers-color-scheme: dark) {
  .xq-bar {
    background: rgba(255, 255, 255, 0.15);
  }
}
:global(.dark) .xq-bar {
  background: rgba(255, 255, 255, 0.15);
}
.xq-bar__fill {
  height: 100%;
  border-radius: 999px;
  width: 0%;
  background: linear-gradient(to right, #94a3b8, #475569);
  transition: width 0.25s ease;
}
.xq-bar__fill.ok {
  background: linear-gradient(to right, #34d399, #059669);
}
.xq-bar__fill.mid {
  background: linear-gradient(to right, #fb923c, #ea580c);
}
.xq-bar__fill.low {
  background: linear-gradient(to right, #f87171, #dc2626);
}

/* lista vendedores */
.xq-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin-top: 0.25rem;
}
.xq-item {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}
.xq-item__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}
.xq-item__name {
  flex: 1 1 auto;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: normal;
  line-height: 1.25;
  -webkit-line-clamp: 2;
  max-height: calc(1.25em * 2);
}
.xq-item__val {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
}
.xq-meta {
  display: flex;
  gap: 0.75rem;
  font-size: 0.8rem;
  color: #64748b;
}
@media (prefers-color-scheme: dark) {
  .xq-meta {
    color: #cbd5e1;
  }
}
:global(.dark) .xq-meta {
  color: #cbd5e1;
}

.xq-mini {
  position: relative;
  height: 8px;
  border-radius: 999px;
  overflow: hidden;
  background: rgba(2, 6, 23, 0.08);
}
@media (prefers-color-scheme: dark) {
  .xq-mini {
    background: rgba(255, 255, 255, 0.15);
  }
}
:global(.dark) .xq-mini {
  background: rgba(255, 255, 255, 0.15);
}
.xq-mini__fill {
  height: 100%;
  border-radius: 999px;
  width: 0%;
  background: linear-gradient(to right, #94a3b8, #475569);
  transition: width 0.25s ease;
}
.xq-mini__fill.ok {
  background: linear-gradient(to right, #34d399, #059669);
}
.xq-mini__fill.mid {
  background: linear-gradient(to right, #fb923c, #ea580c);
}
.xq-mini__fill.low {
  background: linear-gradient(to right, #f87171, #dc2626);
}
</style>
