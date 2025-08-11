<template>
  <div>
                        <Button class="glass-button" @click="isEditable = !isEditable">
                        {{ isEditable ? '🔓 Modo edición activado' : '🔒 Modo bloqueado' }}
                    </Button>

    <grid-layout
      v-model:layout="layout"
      :col-num="12"
      :row-height="30"
      :is-draggable="isEditable"
      :is-resizable="isEditable"
      :margin="[10 , 10]"
      :use-css-transforms="true"
    >
      <grid-item
        v-for="item in layout"
        :key="item.i"
        :x="item.x"
        :y="item.y"
        :w="item.w"
        :h="item.h"
        :i="item.i"
        >
        <component
            v-if="getWidgetComponent(item.type)"
            :is="getWidgetComponent(item.type)"
            v-bind="getPropsForType(item)"
            class="grid-widget"
        />
        <div v-else class="grid-placeholder">Widget {{ item.i }}</div>
        </grid-item>

    </grid-layout>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { GridLayout, GridItem } from 'vue3-grid-layout';
import KpiCard from '@/components/widgets/KpiCard.vue';
import CalendarCard from '@/components/widgets/CalendarCard.vue';
import ChartCard from '@/components/widgets/ChartCard.vue';
import TaskCard from '@/components/widgets/TaskCard.vue';
import ListCard from '@/components/widgets/ListCard.vue';


const isEditable = ref(false);

// 🔢 Layout de widgets dinámicos
const layout = ref([
    { i: '0', x: 0, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'users' },
    { i: '1', x: 2, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'revenue' },
    { i: '2', x: 4, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'users' },
    { i: '3', x: 6, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'users' },
    { i: '4', x: 8, y: 0, w: 3, h: 12, type: 'task' },
    { i: '5', x: 11, y: 0, w: 1, h: 8, type: 'task' },
    { i: '7', x: 0, y: 4, w: 8, h: 16, type: 'chart' },
    { i: '6', x: 11, y: 9, w: 1, h: 4, type: 'task' },
    { i: '8', x: 8, y: 13, w: 4, h: 4, type: 'list' },
    { i: '9', x: 8, y: 17, w: 4, h: 4, type: 'task' },
    { i: '10', x: 0, y: 21, w: 5, h: 16, type: 'list' },
    { i: '11', x: 5, y: 21, w: 5, h: 16, type: 'list' },
    { i: '12', x: 10, y: 21, w: 2, h: 16, type: 'list' }
]);


// 🔌 Devuelve el componente asociado
function getWidgetComponent(type) {
  return {
    kpi: KpiCard,
    calendar: CalendarCard,
    chart: ChartCard,
    task: TaskCard,
    list: ListCard
  }[type] || null;
}

function getPropsForType(item) {
  if (item.type === 'kpi') {
    return { modelValue: item.kpiId, 'onUpdate:modelValue': val => item.kpiId = val }
  }
  return {}
}
</script>

<style scoped>
.grid-widget {
  height: 100%;
  width: 100%;
  box-sizing: border-box;
  background: rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(10px);
  border-radius: 10px;
}

.glass-button {
  font-size: 1rem;
  padding: 0.5rem 1rem;
  margin-bottom: 1rem;
  background: rgba(255, 255, 255, 0.25);
  border: 1px solid #ccc;
  border-radius: 6px;
  backdrop-filter: blur(4px);
  cursor: pointer;
  transition: 0.2s ease;
}

.grid-placeholder {
  height: 100%;
  width: 100%;
  background: #eee;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #888;
  border-radius: 6px;
}
</style>
