<template>
  <div class="dash-wrapper">
<!--    
    <Button class="glass-button" @click="isEditable = !isEditable">
      {{ isEditable ? '🔓 Modo edición activado' : '🔒 Modo bloqueado' }}
    </Button>
-->
    <grid-layout
      v-model:layout="layout"
      :col-num="12"
      :row-height="30"
      :is-draggable="isEditable"
      :is-resizable="isEditable"
      :margin="[10, 10]"
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
        <GlassCard :title="getTitle(item)">
          <component
            v-if="getWidgetComponent(item.type)"
            :is="getWidgetComponent(item.type)"
            v-bind="getPropsForType(item)"
            class="grid-widget"
          />
          <div v-else class="grid-placeholder">Widget {{ item.i }}</div>
        </GlassCard>
      </grid-item>
    </grid-layout>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import Button from 'primevue/button'
import GlassCard from '@/components/ui/GlassCard.vue'

import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/CalendarCard.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import TaskCard from '@/components/widgets/TaskCard.vue'
import ListCard from '@/components/widgets/ListCard.vue'
import OrderList from '@/components/lists/OrderList.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'


const isEditable = ref(false)

const layout = ref([
  { i: '0', x: 0,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'users' },
  { i: '1', x: 2,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'revenue' },
  { i: '2', x: 4,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'conversion' },
  { i: '3', x: 6,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'bounce' },
  { i: '4', x: 8,  y: 0,  w: 4, h: 12, type: 'calendar' },
  { i: '7', x: 0,  y: 4,  w: 5, h: 16, type: 'chart' },
  { i: '8', x: 5,  y: 4, w: 3, h: 16,  type: 'table' },
  { i: '9', x: 8,  y: 17, w: 4, h: 8,  type: 'task' },
  { i: '10',x: 0,  y: 21, w: 5, h: 16, type: 'orders', title: 'Órdenes' },
  { i: '11',x: 5,  y: 21, w: 5, h: 16, type: 'list' },
  { i: '12',x: 10, y: 21, w: 2, h: 16, type: 'list' }
])

function getWidgetComponent(type) {
  return {
    kpi: KpiCard,
    calendar: CalendarCard,
    chart: ChartCard,
    task: TaskCard,
    list: ListCard,
    orders: OrderList,
    table: ProfitCentersTable
  }[type] || null
}

function getTitle(item) {
  if (item.title) return item.title
  return {
    kpi: 'KPIs',
    calendar: 'Calendario',
    chart: 'Gráfico',
    task: 'Tareas',
    list: 'Listado'
  }[item.type] || 'Widget'
}

function getPropsForType(item) {
  if (item.type === 'kpi') {
    return { modelValue: item.kpiId, 'onUpdate:modelValue': val => item.kpiId = val }
  }
  return {}
}
</script>

<style scoped>
.dash-wrapper{
  width: 100%; /* ancho útil menos la barra */

}

.grid-widget{
  height: 100%;
  width: 100%;
  box-sizing: border-box;
  /* asegúrate que los widgets no apliquen su propio “card” */
  background: transparent;
  border: 0;
  border-radius: 0;
}

.glass-button{
  font-size: 1rem;
  padding: 0.5rem 1rem;
  margin-bottom: 1rem;
  background: rgba(255, 255, 255, 0.25);
  border: 1px solid #ccc;
  border-radius: 6px;
  backdrop-filter: blur(4px);
  cursor: pointer;
  transition: .2s ease;
}

.grid-placeholder{
  height: 100%;
  width: 100%;
  background: transparent;
  color: #111827;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
