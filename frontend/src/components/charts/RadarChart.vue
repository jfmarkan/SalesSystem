<script setup>
import { computed, ref, watch } from 'vue'
import { Radar } from 'vue-chartjs'
import {
  Chart,
  RadialLinearScale,
  PointElement,
  LineElement,
  Filler,
  Tooltip,
  Legend
} from 'chart.js'
Chart.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend)

const props = defineProps({
  labels: { type: Array, default: () => [] },
  datasets: { type: Array, default: () => [] }, // Chart.js datasets
  max: { type: Number, default: null },         // escala opcional
  min: { type: Number, default: 0 },
  title: { type: String, default: '' }          // opcional si lo envolvés con un card con título
})

const chartRef = ref(null)

const hasData = computed(() => props.labels.length && props.datasets.length)

const data = computed(() => {
  const src = hasData.value ? { labels: props.labels, datasets: props.datasets } : fallback.value
  return {
    labels: src.labels,
    datasets: src.datasets.map((d, i) => ({
      ...d,
      borderColor: d.borderColor || ['#3B82F6','#22C55E','#F59E0B','#A855F7'][i % 4],
      backgroundColor: d.backgroundColor || (['rgba(59,130,246,0.2)','rgba(34,197,94,0.2)','rgba(245,158,11,0.2)','rgba(168,85,247,0.2)'][i % 4]),
      pointRadius: 0,          // skip points
      pointHoverRadius: 0,
      pointHitRadius: 6,
      spanGaps: true,
      tension: 0.25
    }))
  }
})

const options = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' },
    tooltip: { mode: 'nearest', intersect: false }
  },
  animation: { duration: 150 },
  elements: { point: { radius: 0, hoverRadius: 0 } }, // seguridad extra
  scales: {
    r: {
      beginAtZero: props.min === 0,
      suggestedMin: props.min ?? undefined,
      suggestedMax: props.max ?? undefined,
      ticks: { backdropColor: 'transparent', showLabelBackdrop: false, color: '#334155' },
      angleLines: { color: 'rgba(255,255,255,0.3)' },
      grid: { color: 'rgba(255,255,255,0.2)' },
      pointLabels: { color: '#ffff', font: { size: 12 } }
    }
  }
}))

watch([data, options], () => {
  if (chartRef.value?.chart) chartRef.value.chart.update('none')
}, { deep: true })
</script>

<template>
  <div class="radar-wrap">
    <Radar ref="chartRef" :data="data" :options="options" />
  </div>
</template>

<style scoped>
.radar-wrap { width: 100%; height: 100%; }
</style>