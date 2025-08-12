<template>
  <div class="radar-card">
    <div class="header">
      <h3>Leistungsübersicht nach Profit-Center</h3>

      <div class="toggle">
        <button :class="{active: unit==='raw'}"  @click="setUnit('raw')">Stück</button>
        <button :class="{active: unit==='m3'}"   @click="setUnit('m3')">m³</button>
        <button :class="{active: unit==='euro'}" @click="setUnit('euro')">€</button>
      </div>
    </div>

    <Radar v-if="chartData" :data="chartData" :options="chartOptions" />

    <div class="legend">
      <span class="dot dot-sales"></span>Ist
      <span class="dot dot-budget"></span>Budget
      <span class="dot dot-forecast"></span>Forecast
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { Radar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend
} from 'chart.js'

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

const unit = ref('raw')
const raw = ref([]) // API rows
const chartData = ref(null)

const chartOptions = {
  responsive: true,
  plugins: { legend: { display: false } },
  scales: {
    r: {
      angleLines: { color: 'rgba(255,255,255,0.1)' },
      grid:       { color: 'rgba(255,255,255,0.1)' },
      pointLabels:{ color: '#cbd5e1', font: { size: 11, weight: 600 } },
      ticks:      { showLabelBackdrop: false, color: '#94a3b8' }
    }
  }
}

function setUnit(u) {
  if (unit.value !== u) {
    unit.value = u
    fetchData()
  }
}

async function fetchData() {
  const res = await fetch(`/api/radar?unit=${unit.value}`, { headers: { 'Accept': 'application/json' } })
  const json = await res.json()
  raw.value = json.data || []
  buildChart()
}

function buildChart() {
  const labels = raw.value.map(r => `${r.profit_center_code}`)
  const sales   = raw.value.map(r => Number(r.total_sales)    || 0)
  const budget  = raw.value.map(r => Number(r.total_budget)   || 0)
  const forecast= raw.value.map(r => Number(r.total_forecast) || 0)

  chartData.value = {
    labels,
    datasets: [
      {
        label: 'Verkauf',
        data: sales,
        borderColor: '#22d3ee',
        backgroundColor: 'rgba(34,211,238,0.25)',
        pointBackgroundColor: '#22d3ee',
        pointBorderColor: '#22d3ee',
        pointHoverBackgroundColor: '#0ea5e9',
        pointHoverBorderColor: '#0ea5e9',
        borderWidth: 2,
        fill: true,
      },
      {
        label: 'Budget',
        data: budget,
        borderColor: '#a78bfa',
        backgroundColor: 'rgba(167,139,250,0.20)',
        pointBackgroundColor: '#a78bfa',
        pointBorderColor: '#a78bfa',
        pointHoverBackgroundColor: '#7c3aed',
        pointHoverBorderColor: '#7c3aed',
        borderWidth: 2,
        fill: true,
      },
      {
        label: 'Prognose',
        data: forecast,
        borderColor: '#f472b6',
        backgroundColor: 'rgba(244,114,182,0.18)',
        pointBackgroundColor: '#f472b6',
        pointBorderColor: '#f472b6',
        pointHoverBackgroundColor: '#ec4899',
        pointHoverBorderColor: '#ec4899',
        borderWidth: 2,
        fill: true,
      }
    ]
  }
}

onMounted(fetchData)
watch(unit, () => buildChart())
</script>

<style scoped>
.radar-card {
  background: rgba(15, 23, 42, 0.75);
  border: 1px solid rgba(148, 163, 184, 0.15);
  border-radius: 14px;
  padding: 1rem 1rem 0.5rem;
  color: #e2e8f0;
}
.header {
  display: flex; align-items: center; justify-content: space-between; gap: .5rem;
}
h3 { font-size: 1rem; margin: 0; }
.toggle { display: flex; gap: .5rem; }
.toggle button {
  background: transparent; color: #cbd5e1; border: 1px solid #334155; border-radius: 8px;
  padding: .35rem .6rem; cursor: pointer; font-weight: 600; font-size: .85rem;
}
.toggle button.active { background: #0ea5e9; border-color: #0ea5e9; color: #001018; }
.legend { display: flex; gap: 1rem; align-items: center; padding: .5rem 0 1rem; color:#94a3b8; font-size:.85rem;}
.dot { width:10px; height:10px; border-radius: 50%; display:inline-block; margin-right:.35rem; }
.dot-sales { background:#22d3ee; }
.dot-budget{ background:#a78bfa; }
.dot-forecast{ background:#f472b6; }
</style>