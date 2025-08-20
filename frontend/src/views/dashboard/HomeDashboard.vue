<!-- src/views/HomeDashboard.vue -->
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
          <!-- Unit toggle (only for chart & table) -->
          <template #header-extra>
            <div v-if="item.type==='chart' || item.type==='table'" class="unit-toggle">
              <button class="btn" :class="{active: unit==='M3'}"   @click="setUnit('M3')">m³</button>
              <button class="btn" :class="{active: unit==='VKEH'}" @click="setUnit('VKEH')">VK-EH</button>
              <button class="btn" :class="{active: unit==='EUR'}"  @click="setUnit('EUR')">€</button>
            </div>
          </template>

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
import { ref, computed, onMounted } from 'vue'
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

// Axios (Sanctum client -> http://localhost:8000)
import api from '@/plugins/axios'

// Grid edit state
const isEditable = ref(false)

// Shared unit: 'M3' | 'VKEH' | 'EUR'
const unit = ref('M3')

// Backend state
const kpiItems       = ref([])     // [{ id,label,value,unit,details? }]
const chartRaw       = ref({ pc_codes: [], labels: [], series: [], unit: 'VKEH' }) // series in VKEH
const tableRowsVKEH  = ref([])     // [{ pc_code, pc_name, sales, forecast, budget }] in VKEH
const tableTotalsVKEH= ref({ sales:0, forecast:0, budget:0, unit:'VKEH' })
const calendarEvents = ref([])
const conversions    = ref({})     // { [pc]: { factor_to_m3, factor_to_euro } }
const loading        = ref(false)
const errorMsg       = ref('')

// Fetch dashboard
async function fetchDashboard() {
  loading.value = true; errorMsg.value = ''
  try {
    const { data } = await api.get('/api/dashboard')
    kpiItems.value        = data?.kpis?.items ?? []
    chartRaw.value        = data?.chart ?? { pc_codes: [], labels: [], series: [], unit:'VKEH' }
    tableRowsVKEH.value   = data?.table?.rows ?? []
    tableTotalsVKEH.value = data?.table?.totals ?? { sales:0, forecast:0, budget:0, unit:'VKEH' }
    calendarEvents.value  = data?.calendar?.events ?? []

    // conversions from payload (recommended)
    if (data?.conversions) {
      conversions.value = data.conversions
    } else {
      // optional endpoint if you prefer to keep payload slim
      try {
        const conv = await api.get('/api/unit-conversions')
        if (conv?.data?.conversions) conversions.value = conv.data.conversions
      } catch {}
    }
  } catch (e) {
    console.error('Dashboard load failed', e)
    errorMsg.value = 'Fehler beim Laden des Dashboards.'
  } finally {
    loading.value = false
  }
}
onMounted(fetchDashboard)

// Helpers
function toUnit(pc, vkeh, u){
  const v = Number(vkeh ?? 0)
  const c = conversions.value?.[pc] || {}
  if (u === 'M3')  return v * (c.factor_to_m3  ?? 1)
  if (u === 'EUR') return v * (c.factor_to_euro ?? 1)
  if (u === 'VKEH') return v // base
  return v
}
function pcNumeric(code){
  const m = String(code ?? '').match(/\d+/g)
  return m ? m.join('') : String(code ?? '')
}
function displayUnit(u){
  if (u === 'M3' || u === 'm³') return 'm³'
  if (u === 'EUR') return '€'
  if (u === 'VKEH') return 'VK-EH'
  return u
}

// Chart (convert using pc_codes)
const radarLabels = computed(() => (chartRaw.value.pc_codes || []).map(pcNumeric))
const radarSeries = computed(() => {
  const codes = chartRaw.value.pc_codes || []
  const srcSeries = chartRaw.value.series || []
  return srcSeries.map(ds => ({
    name: ds.name,
    data: (ds.data || []).map((v, i) => toUnit(codes[i], v, unit.value))
  }))
})

// Table (convert row by row)
const tableRows = computed(() =>
  (tableRowsVKEH.value || []).map(r => ({
    pcId: r.pc_code,
    pcIdNumeric: pcNumeric(r.pc_code),
    pcName: r.pc_name,
    sales:    toUnit(r.pc_code, r.sales,    unit.value),
    forecast: toUnit(r.pc_code, r.forecast, unit.value),
    budget:   toUnit(r.pc_code, r.budget,   unit.value)
  }))
)
// Totals only for M3/EUR
const showTotals = computed(() => unit.value === 'M3' || unit.value === 'EUR')
const tableTotals = computed(() => {
  if (!showTotals.value) return { sales:0, forecast:0, budget:0 }
  const sum = k => tableRows.value.reduce((a, r) => a + (Number(r[k] ?? 0)), 0)
  return { sales: sum('sales'), forecast: sum('forecast'), budget: sum('budget') }
})

// KPIs (backend-driven)
const kpis = computed(() => {
  const byId = Object.fromEntries((kpiItems.value || []).map(i => [i.id, i]))
  return {
    users:      byId['perf_vs_forecast_pct'] || { label:'Ist vs Prognose (%)', value:0, unit:'%' },
    conversion: byId['perf_vs_budget_pct']   || { label:'Ist vs Budget (%)',   value:0, unit:'%' },
    revenue:    byId['sales_total_eur']      || { label:'Gesamt-Ist (€)',      value:0, unit:'€' },
    bounce:     byId['extra_quota_m3']       || { label:'Zusatzquote (m³)',    value:0, unit:'m³' }
  }
})

// Layout
const layout = ref([
  { i: '0',  x: 0,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'users' },
  { i: '1',  x: 2,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'conversion' },
  { i: '2',  x: 4,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'revenue' },
  { i: '3',  x: 6,  y: 0,  w: 2, h: 4,  type: 'kpi', kpiId: 'bounce' },
  { i: '4',  x: 8,  y: 0,  w: 4, h: 12, type: 'calendar' },
  { i: '7',  x: 0,  y: 4,  w: 5, h: 17, type: 'chart' },
  { i: '8',  x: 5,  y: 4,  w: 3, h: 17, type: 'table' },
  { i: '9',  x: 8,  y: 17, w: 4, h: 9,  type: 'task' }
])

// Titles (German) — include current unit on chart/table
function getTitle(item) {
  if (item.type === 'kpi') {
    const k = kpis.value[item.kpiId] ?? { label:'KPI', unit:'' }
    return `${k.label}${k.unit ? ` (${displayUnit(k.unit)})` : ''}`
  }
  if (item.type === 'chart') return `Diagramm (${displayUnit(unit.value)})`
  if (item.type === 'table') return `Profitcenter (${displayUnit(unit.value)})`
  if (item.title) return item.title
  return { calendar:'Kalender', task:'Aufgaben', list:'Liste', orders:'Bestellungen' }[item.type] || 'Widget'
}

// Registry
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

// Props per widget
function getPropsForType(item) {
  if (item.type === 'kpi') {
    return {
      modelValue: item.kpiId,
      'onUpdate:modelValue': v => (item.kpiId = v),
      kpis: kpis.value,
      unit: kpis.value[item.kpiId]?.unit || ''
    }
  }
  if (item.type === 'chart') {
    return { labels: radarLabels.value, series: radarSeries.value, unit: displayUnit(unit.value) }
  }
  if (item.type === 'table') {
    return { rows: tableRows.value, totals: tableTotals.value, unit: displayUnit(unit.value), showTotals: showTotals.value }
  }
  if (item.type === 'calendar') {
    return { events: calendarEvents.value }
  }
  return {}
}

// Handlers
function setUnit(u){
  if (u === unit.value) return
  unit.value = u
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
/* Header unit toggle (selected = rgba(31,86,115,.8)) */
.unit-toggle{
  display: inline-flex; gap: .25rem; background: rgba(255,255,255,.25);
  border: 1px solid rgba(0,0,0,.08); border-radius: 8px; padding: .15rem;
}
.unit-toggle .btn{
  border: 0; padding: .25rem .55rem; font-size: .8rem; background: transparent;
  color: #0f172a; border-radius: 6px; cursor: pointer;
}
.unit-toggle .btn.active{
  background: rgba(31,86,115,.8); color: #fff; font-weight: 700;
}
</style>