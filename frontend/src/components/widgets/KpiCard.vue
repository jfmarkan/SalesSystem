<template>
  <div class="kpi-card">
    <div class="kpi-value">
      <span v-if="current.unit === '%'">{{ formatPercent(current.value) }}</span>
      <span v-else>{{ formatNumber(current.value) }}</span>
    </div>

    <div class="kpi-selector" v-if="editable">
      <label>KPIs:</label>
      <select :value="modelValue" @change="$emit('update:modelValue', $event.target.value)">
        <option v-for="(v, key) in kpis" :key="key" :value="key">
          {{ v.label }}
        </option>
      </select>
    </div>
  </div>
</template>

<script setup>
// Code/vars/comments in English
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: String, required: true }, // KPI id
  kpis: { type: Object, required: true }, // { id: { label, value, unit } }
  unit: { type: String, default: 'VK-EH' },
  editable: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])

const current = computed(() => props.kpis[props.modelValue] ?? { label: 'KPI', value: 0, unit: '' })

function formatNumber(n) {
  const abs = Math.abs(n)
  const sign = n < 0 ? '-' : ''
  if (abs >= 1_000_000) return sign + (abs / 1_000_000).toFixed(2) + 'M'
  if (abs >= 1_000) return sign + (abs / 1_000).toFixed(1) + 'k'
  return sign + Math.round(abs).toLocaleString()
}
function formatPercent(p) {
  return `${p.toFixed(1)}%`
}
</script>

<style scoped>
.kpi-card {
  height: 100%;

}
.kpi-value {
  display: flex;
  width: 100%;
  height: 100%;
  align-items: center;
  justify-content: center;
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1;
}
.kpi-selector {
  margin-top: 0.5rem;
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
</style>
