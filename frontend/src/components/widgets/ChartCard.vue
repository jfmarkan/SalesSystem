<template>
    <div class="chart-card">
        <BarChart />
    </div>
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
  background: rgba(0,0,0,0.4);
  backdrop-filter: blur(10px);
  border-radius: 12px;
  box-sizing: border-box;
  box-shadow: 0 2px 6px rgba(0,0,0,0.4);
}
</style>
