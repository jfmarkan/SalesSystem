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
