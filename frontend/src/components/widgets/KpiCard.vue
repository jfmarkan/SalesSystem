<template>
  <div class="kpi-flip-wrapper" @mouseleave="flipped = false">
    <div :class="['kpi-flip-card', { flipped }]">
      
      <!-- LADO FRONTAL -->
      <div class="kpi-front">
        <div class="kpi-title">
          <i class="fa-solid fa-chart-line"></i>
          {{ selectedKpi.label }}
        </div>
        <div class="kpi-value">{{ selectedKpi.value }}</div>

        <div class="flip-icon" @click.stop="flipped = !flipped" title="Ver detalles">
          <i class="fa-solid fa-repeat"></i>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { kpiList } from '@/data/kpiData.js';

const props = defineProps({
  modelValue: String
});
const emit = defineEmits(['update:modelValue']);

const flipped = ref(false);
const selectedKpiId = ref();
const kpis = kpiList;

watch(() => props.modelValue, (val) => {
  selectedKpiId.value = val;
}, { immediate: true });

watch(() => selectedKpiId.value, (val) => {
  emit('update:modelValue', val);
});

const selectedKpi = computed(() => {
  return kpis.find(k => k.id === selectedKpiId.value) || kpis[0];
});
</script>

<style scoped>

.kpi-title {
  font-size: 1rem;
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.kpi-value {
  font-size: 2.5rem;
  font-weight: bold;
  text-align: center;
  margin: auto 0;
}
</style>
