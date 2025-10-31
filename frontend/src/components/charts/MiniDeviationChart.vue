<script setup>
// Code in English; UI labels in German.
import { computed } from 'vue'
import { Bar, Line } from 'vue-chartjs'
import {
  Chart, BarElement, LineElement, PointElement,
  CategoryScale, LinearScale, Tooltip, Legend
} from 'chart.js'
Chart.register(BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend)

const props = defineProps({
  months:   { type: [Array, null], default: null },
  sales:    { type: [Array, Number, String], required: true },   // IST (Blue)
  budget:   { type: [Array, Number, String], required: true },   // Budget (Green)
  forecast: { type: [Array, Number, String], required: true },   // Forecast (Yellow)
  height:   { type: Number, default: 500 }
})

/* ==== NORMALIZERS + FORMAT ==== */
// enteros y miles con punto, sin decimales
const toInt = (v) => {
  if (typeof v === 'number' && Number.isFinite(v)) return Math.round(v)
  const s = String(v ?? '').replace(/\./g, '').split(',')[0].replace(/[^\d-]/g, '')
  if (s === '' || s === '-') return 0
  const n = parseInt(s, 10)
  return Number.isFinite(n) ? n : 0
}
const toIntArr = (v, len = 0) => {
  if (Array.isArray(v)) return v.map(toInt)
  const n = toInt(v)
  return len > 0 ? Array.from({ length: len }, () => n) : [n]
}
const fmt = (x) => new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(toInt(x))

// palette fija: azul=Ist, verde=Budget, amarillo=Forecast
const C = {
  salesLine:   '#6E8DA8',                 // blue
  salesFill:   'rgba(96,165,250,.20)',
  budgetLine:  '#7AA488',                 // green
  budgetFill:  'rgba(34,197,94,.20)',
  fcLine:      '#B3A45B',                 // yellow
  fcFill:      'rgba(255,194,14,.20)',
}

const isSeries = computed(() => Array.isArray(props.months) && props.months.length > 1)

/* ---- DATASETS ---- */
const chartData = computed(() => {
  if (isSeries.value) {
    // Forecast deviation view: line chart with 3 lines (Ist, Budget, Forecast)
    const len = props.months.length
    return {
      labels: props.months,
      datasets: [
        { label:'Ist',      data: toIntArr(props.sales, len),    borderColor: C.salesLine,  backgroundColor: C.salesFill,  tension:.25, fill:false, pointRadius:0 },
        { label:'Budget',   data: toIntArr(props.budget, len),   borderColor: C.budgetLine, backgroundColor: C.budgetFill, tension:.25, fill:false, pointRadius:0 },
        { label:'Forecast', data: toIntArr(props.forecast, len), borderColor: C.fcLine,     backgroundColor: C.fcFill,     tension:.25, fill:false, pointRadius:0 }
      ]
    }
  }

  // Sales deviation view: TWO separate bars (Ist blue, Budget green), no Forecast
  const s = toInt(Array.isArray(props.sales) ? props.sales.at(-1) : props.sales)
  const b = toInt(Array.isArray(props.budget) ? props.budget.at(-1) : props.budget)

  return {
    labels: ['Ist', 'Budget'],
    datasets: [{
      label: '',
      data: [s, b],
      backgroundColor: [C.salesLine, C.budgetLine]
    }]
  }
})

/* ---- OPTIONS ---- */
const options = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: isSeries.value, position: 'bottom', labels: { color: '#737373' } },
    tooltip: {
      mode: 'index',
      intersect: false,
      callbacks: {
        label: (ctx) => {
          const name = (isSeries.value ? (ctx.dataset?.label || '') : (ctx.label || ''))
          return `${name}: ${fmt(ctx.parsed?.y ?? 0)}`
        }
      }
    }
  },
  elements: {
    line: { borderWidth: 2 },
    point: { radius: 0, hoverRadius: 3 }
  },
  scales: {
    x: {
      stacked: false,
      ticks: { color: '#737373' },
      grid: { color: 'rgba(0,0,0, .15)' }
    },
    y: {
      stacked: false,
      beginAtZero: true,
      ticks: { color: '#737373', callback: (v) => fmt(v) },
      grid: { color: 'rgba(0,0,0,.15)' }
    }
  }
}))
</script>

<template>
  <div :style="{ height: `${height}px`, width: '100%' }">
    <Line v-if="isSeries" :data="chartData" :options="options" />
    <Bar v-else :data="chartData" :options="options" />
  </div>
</template>
