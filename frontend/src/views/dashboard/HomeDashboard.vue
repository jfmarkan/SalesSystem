<template>
  <div class="dash-wrapper">
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
        :x="item.x" :y="item.y" :w="item.w" :h="item.h" :i="item.i"
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
// UI must be German; all code/variables/comments in English.
import { ref, computed } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import GlassCard from '@/components/ui/GlassCard.vue'

// Widgets
import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/CalendarCard.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import TaskCard from '@/components/widgets/TaskCard.vue'
import ListCard from '@/components/widgets/ListCard.vue'
import OrderList from '@/components/lists/OrderList.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'

// Grid edit state
const isEditable = ref(false)

// Shared unit across widgets: 'VK-EH' | 'M3' | 'EUR'
const unit = ref('VK-EH')

// Profit centers (sample; replace with API)
const profitCenters = ref([
  { id: 'PC01', name: 'Zentrum Nord' },
  { id: 'PC02', name: 'Zentrum Süd' },
  { id: 'PC03', name: 'Zentrum Ost' },
  { id: 'PC04', name: 'Zentrum West' }
])

// Conversion factors per PC from VK-EH to target units
const unitConversions = ref({
  PC01: { 'VK-EH->M3': 0.72, 'VK-EH->EUR': 48.5 },
  PC02: { 'VK-EH->M3': 0.80, 'VK-EH->EUR': 51.0 },
  PC03: { 'VK-EH->M3': 0.66, 'VK-EH->EUR': 46.2 },
  PC04: { 'VK-EH->M3': 0.75, 'VK-EH->EUR': 49.7 }
})

// Metrics per PC (base in VK-EH)
const metricsByPc = ref({
  PC01: { sales: 1200, forecast: 1350, budget: 1300 },
  PC02: { sales: 900,  forecast: 1000, budget: 1100 },
  PC03: { sales: 650,  forecast: 700,  budget: 800  },
  PC04: { sales: 1050, forecast: 1150, budget: 1200 }
})

// Action plan events for calendar
const actionPlan = ref([
  { id: 1, title: 'Promo PC01 starten', pcId: 'PC01', date: '2025-08-19', status: 'done' },
  { id: 2, title: 'Teamtraining PC02',   pcId: 'PC02', date: '2025-08-20', status: 'pending' },
  { id: 3, title: 'Portfolio-Check PC03',pcId: 'PC03', date: '2025-08-18', status: 'overdue' },
  { id: 4, title: 'Preisupdate PC04',    pcId: 'PC04', date: '2025-08-22', status: 'pending' }
])

// ----- Conversion helpers
function toUnit(pcId, valueVKEH, targetUnit) {
  if (targetUnit === 'VK-EH') return valueVKEH
  const conv = unitConversions.value[pcId] || { 'VK-EH->M3': 1, 'VK-EH->EUR': 1 }
  if (targetUnit === 'M3')  return valueVKEH * (conv['VK-EH->M3']  ?? 1)
  if (targetUnit === 'EUR') return valueVKEH * (conv['VK-EH->EUR'] ?? 1)
  return valueVKEH
}
function sumField(field, targetUnit) {
  return profitCenters.value.reduce((acc, pc) => {
    const base = metricsByPc.value[pc.id]?.[field] ?? 0
    return acc + toUnit(pc.id, base, targetUnit)
  }, 0)
}

// ----- Aggregates for KPIs (VK-EH for ratio computations)
const totalSales          = computed(() => sumField('sales', unit.value))
const totalBudgetVK       = computed(() => sumField('budget', 'VK-EH'))
const totalSalesVK        = computed(() => sumField('sales', 'VK-EH'))
const totalForecastVK     = computed(() => sumField('forecast', 'VK-EH'))
const fulfillmentPct      = computed(() => totalBudgetVK.value === 0 ? 0 : (totalSalesVK.value / totalBudgetVK.value) * 100)
const budgetGap           = computed(() =>
  profitCenters.value.reduce((acc, pc) => {
    const b = metricsByPc.value[pc.id]?.budget ?? 0
    const s = metricsByPc.value[pc.id]?.sales ?? 0
    const gapVK = b - s
    if (unit.value === 'VK-EH') return acc + gapVK
    return acc + toUnit(pc.id, Math.abs(gapVK), unit.value) * Math.sign(gapVK)
  }, 0)
)
const forecastAccuracyPct = computed(() => {
  // 100 - simple MAPE (using VK-EH totals)
  const denom = totalSalesVK.value || 1
  const absErr = Math.abs(totalForecastVK.value - totalSalesVK.value)
  const mape = (absErr / denom) * 100
  return Math.max(0, 100 - mape)
})

// ----- Radar chart series (German labels in UI)
const radarLabels = computed(() => profitCenters.value.map(pc => pc.name))
const radarSeries = computed(() => {
  const s = profitCenters.value.map(pc => toUnit(pc.id, metricsByPc.value[pc.id]?.sales    ?? 0, unit.value))
  const f = profitCenters.value.map(pc => toUnit(pc.id, metricsByPc.value[pc.id]?.forecast ?? 0, unit.value))
  const b = profitCenters.value.map(pc => toUnit(pc.id, metricsByPc.value[pc.id]?.budget   ?? 0, unit.value))
  return [
    { name: 'Verkäufe', data: s },
    { name: 'Forecast', data: f },
    { name: 'Budget',   data: b }
  ]
})

// ----- Table rows per PC
const tableRows = computed(() => profitCenters.value.map(pc => {
  const base = metricsByPc.value[pc.id] || { sales: 0, forecast: 0, budget: 0 }
  return {
    pcId: pc.id,
    pcName: pc.name,
    sales: toUnit(pc.id, base.sales, unit.value),
    forecast: toUnit(pc.id, base.forecast, unit.value),
    budget: toUnit(pc.id, base.budget, unit.value)
  }
}))

// ----- KPI map (German labels)
const kpis = computed(() => ({
  users:      { label: 'Gesamtverkäufe',          value: totalSales.value,            unit: unit.value },
  revenue:    { label: 'Erfüllung vs. Budget', value: fulfillmentPct.value,        unit: '%' },
  conversion: { label: 'Lücke vs. Budget',     value: budgetGap.value,             unit: unit.value },
  bounce:     { label: 'Forecast-Genauigkeit',    value: forecastAccuracyPct.value,   unit: '%' }
}))

// ----- Grid layout
const layout = ref([
  { i: '0',  x: 0,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'users' },
  { i: '1',  x: 2,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'conversion' },
  { i: '2',  x: 4,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'revenue' },
  { i: '3',  x: 6,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'bounce' },
  { i: '4',  x: 8,  y: 0,  w: 4, h: 12, type: 'calendar' },
  { i: '7',  x: 0,  y: 4,  w: 5, h: 17, type: 'chart' },
  { i: '8',  x: 5,  y: 4,  w: 3, h: 17, type: 'table' },
  { i: '9',  x: 8,  y: 17, w: 4, h: 9,  type: 'task' },
//  { i: '10', x: 0,  y: 21, w: 5, h: 16, type: 'orders', title: 'Bestellungen' },
//  { i: '11', x: 5,  y: 21, w: 5, h: 16, type: 'list' },
//  { i: '12', x: 10, y: 21, w: 2, h: 16, type: 'list' }
  
])

// ----- Title helpers
function displayUnit(u){
  if (u === 'M3')  return 'm³'
  if (u === 'EUR') return '€'
  if (u === '%')   return '%'
  return 'VK-EH'
}

// ----- Component registry
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

// ----- Titles (German). For KPI: use label + unit in parentheses.
function getTitle(item) {
  if (item.type === 'kpi') {
    const k = kpis.value[item.kpiId] ?? { label: 'KPI', unit: '' }
    const unitSuffix = k.unit ? ` (${displayUnit(k.unit)})` : ''
    return `${k.label}${unitSuffix}`
  }
  if (item.title) return item.title
  return {
    calendar: 'Kalender',
    chart: 'Diagramm',
    task: 'Aufgaben',
    list: 'Liste',
    table: 'Profitcenter'
  }[item.type] || 'Widget'
}

// ----- Props per widget
function getPropsForType(item) {
  if (item.type === 'kpi') {
    return {
      modelValue: item.kpiId,
      'onUpdate:modelValue': val => (item.kpiId = val),
      kpis: kpis.value,
      unit: unit.value
    }
  }
  if (item.type === 'chart') {
    return {
      labels: radarLabels.value,
      series: radarSeries.value,
      unit: unit.value,
      onUnitChange: (u) => (unit.value = u)
    }
  }
  if (item.type === 'table') {
    return {
      rows: tableRows.value,
      totals: {
        sales: sumField('sales', unit.value),
        forecast: sumField('forecast', unit.value),
        budget: sumField('budget', unit.value)
      },
      unit: unit.value,
      onUnitChange: (u) => (unit.value = u)
    }
  }
  if (item.type === 'calendar') {
    return { events: actionPlan.value, profitCenters: profitCenters.value }
  }
  return {}
}
</script>

<style scoped>
.dash-wrapper{ width: 100%; }
.grid-widget{
  height: 100%; width: 100%; box-sizing: border-box; background: transparent; border: 0; border-radius: 0;
}
.grid-placeholder{
  height: 100%; width: 100%; background: transparent; color: #111827;
  display: flex; align-items: center; justify-content: center;
}
</style>