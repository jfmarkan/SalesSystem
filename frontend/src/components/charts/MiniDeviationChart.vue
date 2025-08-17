<script setup>
import { computed } from 'vue'
import { Bar, Line } from 'vue-chartjs'
import { Chart, BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'
Chart.register(BarElement, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend)

const props = defineProps({
  months: { type: [Array, null], default: null },
  sales: { type: [Array, Number], required: true },
  budget: { type: [Array, Number], required: true },
  forecast: { type: [Array, Number], required: true }
})

const isSeries = computed(() => Array.isArray(props.months) && props.months.length > 1)
const labels = computed(() => isSeries.value ? props.months : ['Verkauf','Budget','Prognose'])
const datasets = computed(() => {
  if (isSeries.value) {
    return [
      { label:'Verkauf',  data: props.sales  || [], borderColor:'#05a46f', backgroundColor:'rgba(5,164,111,.15)', tension:.2, fill:false },
      { label:'Budget',   data: props.budget || [], borderColor:'#54849A', backgroundColor:'rgba(84,132,154,.15)', tension:.2, fill:false },
      { label:'Prognose', data: props.forecast||[], borderColor:'#E88D1E', backgroundColor:'rgba(232,141,30,.15)', tension:.2, fill:false }
    ]
  }
  return [
    { label:'', data:[Number(props.sales||0), Number(props.budget||0), Number(props.forecast||0)],
      backgroundColor:['#05a46f','#54849A','#E88D1E'] }
  ]
})
const options = {
  responsive:true, maintainAspectRatio:false,
  plugins:{ legend:{ display:isSeries.value, position:'bottom' }, tooltip:{ mode:'index', intersect:false } },
  scales:{
    x:{ ticks:{ color:'#e5e7eb' }, grid:{ color:'rgba(255,255,255,.08)' } },
    y:{ beginAtZero:true, ticks:{ color:'#e5e7eb' }, grid:{ color:'rgba(255,255,255,.08)' } }
  }
}
</script>

<template>
  <div style="height:180px; width:100%;">
    <Line v-if="isSeries" :data="{ labels, datasets }" :options="options" />
    <Bar v-else :data="{ labels, datasets }" :options="options" />
  </div>
</template>
