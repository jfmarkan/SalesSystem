<template>
  <div class="chart-wrap">
    <div class="chart-toolbar">
      <div class="btn-group">
        <button :class="{active: unit==='VK-EH'}" @click="$emit('unitChange','VK-EH')">VK-EH</button>
        <button :class="{active: unit==='M3'}"    @click="$emit('unitChange','M3')">m³</button>
        <button :class="{active: unit==='EUR'}"   @click="$emit('unitChange','EUR')">€</button>
      </div>
    </div>

    <div class="chart-area">
      <Radar v-if="chartData" :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

<script setup>
// Code/vars/comments in English
import { computed } from 'vue'
import {
  Chart as ChartJS,
  RadialLinearScale, PointElement, LineElement, Filler,
  Tooltip, Legend, Title
} from 'chart.js'
import { Radar } from 'vue-chartjs'

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend, Title)

const props = defineProps({
  labels: { type: Array, required: true },         // e.g. ['Zentrum Nord', ...]
  series: { type: Array, required: true },         // e.g. [{ name:'Verkäufe', data:[...] }, ...]
  unit:   { type: String, default: 'VK-EH' }       // 'VK-EH' | 'M3' | 'EUR'
})
defineEmits(['unitChange'])

const palette = [
  { border: 'rgba(59,130,246,1)',  bg: 'rgba(59,130,246,.20)' }, // blue
  { border: 'rgba(16,185,129,1)',  bg: 'rgba(16,185,129,.20)' }, // green
  { border: 'rgba(244,63,94,1)',   bg: 'rgba(244,63,94,.20)' },  // red
  { border: 'rgba(245,158,11,1)',  bg: 'rgba(245,158,11,.20)' }, // amber (spare)
]

// Build reactive Chart.js dataset from incoming series
const chartData = computed(() => ({
  labels: props.labels,
  datasets: props.series.map((s, i) => ({
    label: s.name,                 // UI label in German comes from parent
    data: s.data ?? [],
    borderColor: palette[i % palette.length].border,
    backgroundColor: palette[i % palette.length].bg,
    pointBackgroundColor: palette[i % palette.length].border,
    pointBorderColor: '#fff',
    pointHoverRadius: 4,
    borderWidth: 2,
    fill: true,
    tension: 0.25
  }))
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom', labels: { usePointStyle: true } },
    tooltip: {
      mode: 'nearest',
      callbacks: {
        label: (ctx) => {
          const v = ctx.parsed.r ?? 0
          return `${ctx.dataset.label}: ${formatNumber(v)}`
        },
        title: (items) => items?.[0]?.label ?? ''
      }
    },
    title: { display: false }
  },
  scales: {
    r: {
      angleLines: { color: 'rgba(0,0,0,.08)' },
      grid: { color: 'rgba(0,0,0,.08)' },
      pointLabels: { color: '#111827', font: { weight: '600' } },
      ticks: {
        backdropColor: 'transparent',
        showLabelBackdrop: false,
        color: '#374151',
        z: 1,
        callback: (value) => formatNumber(Number(value))
      },
      suggestedMin: 0
    }
  },
  animation: { duration: 300 }
}))

function formatNumber(n) {
  const abs = Math.abs(n)
  if (abs >= 1_000_000) return (n/1_000_000).toFixed(2) + 'M'
  if (abs >= 1_000)     return (n/1_000).toFixed(1) + 'k'
  return (Math.round(n * 100) / 100).toLocaleString(undefined, { maximumFractionDigits: 2 })
}
</script>

<style scoped>
.chart-wrap{ height: 100%; display: flex; flex-direction: column; }
.chart-toolbar{
  display: flex; justify-content: flex-end; gap: .5rem; margin-bottom: .25rem;
}
.btn-group{
  background: rgba(255,255,255,.25);
  border: 1px solid rgba(0,0,0,.1);
  border-radius: 8px;
  overflow: hidden;
}
.btn-group button{
  padding: .35rem .6rem; font-size: .8rem; border: 0; background: transparent; cursor: pointer;
}
.btn-group button.active{ background: rgba(255,255,255,.5); font-weight: 700; }
.chart-area{ flex: 1; min-height: 0; }
</style>