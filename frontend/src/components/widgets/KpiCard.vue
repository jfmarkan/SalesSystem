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

      <!-- LADO TRASERO -->
      <div class="kpi-back">
        <div class="kpi-title">
          Detalles del KPI
        </div>

        <select v-model="selectedKpiId" @change="updateKpi" class="kpi-select">
          <option v-for="kpi in kpis" :key="kpi.id" :value="kpi.id">
            {{ kpi.label }}
          </option>
        </select>

        <div class="kpi-source">
          <strong>Origen del cálculo:</strong><br>
          {{ selectedKpi.source }}
        </div>

        <div class="flip-icon back" @click.stop="flipped = !flipped" title="Volver">
          <i class="fa-solid fa-rotate-left"></i>
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
.kpi-flip-wrapper {
  perspective: 1000px;
  width: 100%;
  height: 100%;
}

.kpi-flip-card {
  width: 100%;
  height: 100%;
  position: relative;
  transition: transform 0.6s;
  transform-style: preserve-3d;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 6px rgba(0,0,0,0.4);
}

.kpi-flip-card.flipped {
  transform: rotateY(180deg);
}

.kpi-front,
.kpi-back {
  position: absolute;
  width: 100%;
  height: 100%;

  backface-visibility: hidden;
  
  backdrop-filter: blur(10px);
  padding: 1rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.kpi-front {
  color: white;
  background: rgba(0, 0, 0, .4);
}

.kpi-back {
  color: white;
  transform: rotateY(180deg);
}

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

.kpi-select {
  padding: 0.4rem;
  margin-bottom: 1rem;
  border-radius: 6px;
  border: 1px solid #ccc;
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
}

.kpi-source {
  font-size: 0.9rem;
  opacity: 0.9;
}

.flip-icon {
  position: absolute;
  bottom: 8px;
  right: 8px;
  font-size: .75rem;
  color: #fff;
  cursor: pointer;
  opacity: 0.7;
  transition: 0.2s;
}
.flip-icon:hover {
  opacity: 1;
  transform: scale(1.1);
}
.flip-icon.back {
  left: 8px;
  right: auto;
}
</style>
