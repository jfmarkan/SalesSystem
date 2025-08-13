<script setup>
/* Line chart that updates on reactive data changes. Skip points for clarity. */
import { computed, ref, watch } from 'vue'
import { Line } from 'vue-chartjs'
import { Chart, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler } from 'chart.js'
Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler)

const props = defineProps({
  labels: { type: Array, required: true },
  ventas: { type: Array, required: true },
  budget: { type: Array, required: true },
  forecast: { type: Array, required: true },
  orders: { type: Array, required: true }
})

const chartRef = ref(null)

const data = computed(()=>({
  labels: props.labels,
  datasets: [
    { label: 'Verkauf', data: props.ventas, borderColor: '#22C55E', backgroundColor: 'rgba(34,197,94,0.15)', tension: .25, pointRadius: 0, fill: false },
    { label: 'Budget',  data: props.budget, borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,0.15)', tension: .25, pointRadius: 0, fill: false },
    { label: 'Forecast',data: props.forecast, borderColor: '#F59E0B', backgroundColor: 'rgba(245,158,11,0.15)', tension: .25, pointRadius: 0, fill: false },
    { label: 'Bestellungen', data: props.orders, borderColor: '#A855F7', backgroundColor: 'rgba(168,85,247,0.15)', tension: .25, pointRadius: 0, fill: false }
  ]
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } },
  interaction: { mode: 'nearest', intersect: false },
  animation: { duration: 150 },
  scales: { x: { ticks: { maxRotation: 0 } }, y: { beginAtZero: true } }
}

watch(
  () => [props.labels, props.ventas, props.budget, props.forecast, props.orders],
  () => { if (chartRef.value?.chart) chartRef.value.chart.update('none') },
  { deep: true }
)
</script>

<template>
  <div style="height:100%; width:100%;">
    <Line ref="chartRef" :data="data" :options="options" />
  </div>
</template>