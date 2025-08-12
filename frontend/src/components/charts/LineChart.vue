<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import { Chart, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js'
Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend)

const props = defineProps({
  labels: { type: Array, required: true },
  ventas: { type: Array, required: true },
  budget: { type: Array, required: true },
  forecast: { type: Array, required: true },
  orders: { type: Array, required: true }
})

const data = computed(()=>({
  labels: props.labels,
  datasets: [
    { label: 'Ventas',   data: props.ventas,   borderColor: '#22C55E', backgroundColor: 'rgba(34,197,94,0.2)', tension: .25 },
    { label: 'Budget',   data: props.budget,   borderColor: '#3B82F6', backgroundColor: 'rgba(59,130,246,0.2)', tension: .25 },
    { label: 'Forecast', data: props.forecast, borderColor: '#F59E0B', backgroundColor: 'rgba(245,158,11,0.2)', tension: .25 },
    { label: 'Órdenes',  data: props.orders,   borderColor: '#A855F7', backgroundColor: 'rgba(168,85,247,0.2)', tension: .25 }
  ]
}))
const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' }, tooltip: { mode: 'index', intersect: false } },
  interaction: { mode: 'nearest', intersect: false },
  scales: { x: { ticks: { maxRotation: 0 } }, y: { beginAtZero: true } }
}
</script>

<template>
  <div style="height:100%; width:100%;">
    <Line :data="data" :options="options" />
  </div>
</template>

<style scoped>

</style>