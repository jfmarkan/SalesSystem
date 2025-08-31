<template>
  <div class="kpi-root">
    <div class="kpi-title">{{ current.label }}</div>

    <div class="kpi-main">
      <div class="kpi-value">
        <span v-if="current.unit === '%'">{{ formatPercent(current.value) }}</span>
        <span v-else>{{ formatNumber(current.value) }}</span>
      </div>
      <div class="kpi-icon" :style="iconStyle">
        <i :class="['pi', iconName]" />
      </div>
    </div>

    <div class="kpi-foot">
      <div class="kpi-sub" v-if="subnote">{{ subnote }}</div>

      <div class="kpi-selector" v-if="editable">
        <label>KPIs:</label>
        <select :value="modelValue" @change="$emit('update:modelValue', $event.target.value)">
          <option v-for="(v, key) in kpis" :key="key" :value="key">{{ v.label }}</option>
        </select>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: String, required: true },
  kpis:       { type: Object, required: true },
  unit:       { type: String, default: 'VK-EH' },
  editable:   { type: Boolean, default: false },
  icon:       { type: String, default: '' },
  accent:     { type: String, default: '' },
  note:       { type: String, default: '' }
})
defineEmits(['update:modelValue'])

const current = computed(() => props.kpis[props.modelValue] ?? { label: 'KPI', value: 0, unit: '' })

function formatNumber(n) {
  const num = Number(n) || 0
  const abs = Math.abs(num)
  const sign = num < 0 ? '-' : ''
  if (abs >= 1_000_000) return sign + (abs / 1_000_000).toFixed(2) + 'M'
  if (abs >= 1_000) return sign + (abs / 1_000).toFixed(1) + 'k'
  return sign + Math.round(abs).toLocaleString()
}
function formatPercent(p) {
  const v = Number(p) || 0
  return `${v.toFixed(1)}%`
}

const defaultIconByKpi = {
  ist_vs_prognose: 'pi-chart-line',
  ist_vs_budget: 'pi-wallet',
  diff_ist_budget_m3: 'pi-database',
  umsatz_eur: 'pi-euro'
}
const defaultAccentByKpi = {
  ist_vs_prognose: 'cyan',
  ist_vs_budget: 'orange',
  diff_ist_budget_m3: 'slate',
  umsatz_eur: 'violet'
}
const gradientByAccent = {
  cyan:   'linear-gradient(to bottom, #22d3ee, #0891b2)',
  orange: 'linear-gradient(to bottom, #fb923c, #ea580c)',
  slate:  'linear-gradient(to bottom, #94a3b8, #475569)',
  violet: 'linear-gradient(to bottom, #a78bfa, #7c3aed)',
  emerald:'linear-gradient(to bottom, #34d399, #059669)',
  rose:   'linear-gradient(to bottom, #fb7185, #e11d48)'
}

const kpiId = computed(() => props.modelValue)
const iconName = computed(() => props.icon || defaultIconByKpi[kpiId.value] || 'pi-chart-bar')
const accent = computed(() => props.accent || defaultAccentByKpi[kpiId.value] || 'cyan')
const iconStyle = computed(() => ({ background: gradientByAccent[accent.value] || gradientByAccent.cyan }))

const subnote = computed(() => props.note || (current.value.unit && current.value.unit !== '%' ? `Menge: ${current.value.unit}` : ''))
</script>

<style scoped>
.kpi-root{
  display:flex; flex-direction:column; height:100%;
  gap:.75rem;
  padding:10px 12px;            /* aire extra solo en KPI */
}

.kpi-title{
  font-size:.9rem; line-height:1.2; font-weight:500; color:#334155;
}
@media (prefers-color-scheme: dark) { .kpi-title{ color:#e5e7eb; } }
:global(.dark) .kpi-title{ color:#e5e7eb; }

.kpi-main{
  display:flex; align-items:center; justify-content:space-between; gap:1rem;
}
.kpi-value{
  font-size:2.25rem; line-height:1; font-weight:800; color:#0f172a;
}
@media (prefers-color-scheme: dark) { .kpi-value{ color:#f8fafc; } }
:global(.dark) .kpi-value{ color:#f8fafc; }

.kpi-icon{
  width:2.75rem; height:2.75rem; border-radius:.8rem; color:#fff;
  display:flex; align-items:center; justify-content:center;
}
.kpi-icon .pi{ font-size:1.25rem; line-height:1; }

.kpi-foot{
  margin-top:.25rem;
  display:flex; align-items:center; justify-content:space-between; gap:.5rem;
}
.kpi-sub{ color:#64748b; font-weight:500; }
@media (prefers-color-scheme: dark) { .kpi-sub{ color:#cbd5e1; } }
:global(.dark) .kpi-sub{ color:#cbd5e1; }

.kpi-selector{ display:flex; gap:.5rem; align-items:center; }
.kpi-selector label{ font-size:.85rem; color:#64748b; }
@media (prefers-color-scheme: dark) { .kpi-selector label{ color:#cbd5e1; } }
:global(.dark) .kpi-selector label{ color:#cbd5e1; }
.kpi-selector select{
  border:1px solid rgba(2,6,23,.15); background:transparent; color:inherit;
  border-radius:.5rem; padding:.25rem .5rem;
}
@media (prefers-color-scheme: dark) { .kpi-selector select{ border-color: rgba(255,255,255,.2); } }
:global(.dark) .kpi-selector select{ border-color: rgba(255,255,255,.2); }
</style>