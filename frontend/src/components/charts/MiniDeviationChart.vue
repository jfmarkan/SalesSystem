<script setup>
// Code in English; UI labels in German.
import { computed } from 'vue'
import { Bar, Line } from 'vue-chartjs'
import { Chart, BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'
Chart.register(BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend)

const props = defineProps({
  months: { type: [Array, null], default: null },
  sales: { type: [Array, Number], required: true },
  budget: { type: [Array, Number], required: true },
  forecast: { type: [Array, Number], required: true },
  height: { type: Number, default: 500 }
})

const isSeries = computed(() => Array.isArray(props.months) && props.months.length > 1)

const chartData = computed(() => {
  if (isSeries.value) {
    return {
      labels: props.months,
      datasets: [
        { label:'Ist',      data: Array.isArray(props.sales) ? props.sales : [],      borderColor:'#05a46f', backgroundColor:'rgba(5,164,111,.15)', tension:.2, fill:false },
        { label:'Budget',   data: Array.isArray(props.budget) ? props.budget : [],    borderColor:'#54849A', backgroundColor:'rgba(84,132,154,.15)', tension:.2, fill:false },
        { label:'Forecast', data: Array.isArray(props.forecast) ? props.forecast : [], borderColor:'#E88D1E', backgroundColor:'rgba(232,141,30,.15)', tension:.2, fill:false }
      ]
    }
  }
  return {
    labels: ['Ist','Budget','Forecast'],
    datasets: [{
      label: '',
      data: [
        Number(Array.isArray(props.sales) ? props.sales.at(-1) ?? 0 : props.sales ?? 0),
        Number(Array.isArray(props.budget) ? props.budget.at(-1) ?? 0 : props.budget ?? 0),
        Number(Array.isArray(props.forecast) ? props.forecast.at(-1) ?? 0 : props.forecast ?? 0)
      ],
      backgroundColor: ['#05a46f','#54849A','#E88D1E']
    }]
  }
})

const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: isSeries.value, position: 'bottom' },
    tooltip: { mode: 'index', intersect: false }
  },
  scales: {
    x: { ticks: { color: '#e5e7eb' }, grid: { color: 'rgba(255,255,255,.08)' } },
    y: { beginAtZero: true, ticks: { color: '#e5e7eb' }, grid: { color: 'rgba(255,255,255,.08)' } }
  }
}
</script>

<template>
  <div :style="{ height: `${height}px`, width: '100%' }">
    <Line v-if="isSeries" :data="chartData" :options="options" />
    <Bar v-else :data="chartData" :options="options" />
  </div>
</template>