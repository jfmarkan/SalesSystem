<template>
  <BarChart />
</template>

<script>
import BarChart from '../charts/BarChart.vue'

export default {
  name: 'ChartCard',
  components: { BarChart }
}
</script>

<style scoped>
.chart-card {
  height: 100%;
  width: 100%;
  padding: 1rem;
  background: rgba(255,255,255,0.08);
  border-radius: 12px;
  box-sizing: border-box;
}
</style>
