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
          <!-- Unit buttons in header for Chart & Table -->
          <template #header-extra v-if="item.type==='chart' || item.type==='table'">
            <div class="unit-toggle">
              <button :class="['u-btn', unit==='VKEH' && 'active']" @click="changeUnit('VKEH')">VK-EH</button>
              <button :class="['u-btn', unit==='M3'   && 'active']" @click="changeUnit('M3')">m³</button>
              <button :class="['u-btn', unit==='EUR'  && 'active']" @click="changeUnit('EUR')">€</button>
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

    <!-- Simple error strip -->
    <div v-if="errorMsg" class="err">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
// UI German; code/comments English.
import { ref, computed, onMounted, watch } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import api from '@/plugins/axios'

import GlassCard from '@/components/ui/GlassCard.vue'
import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/CalendarCard.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'
import TaskCard from '@/components/widgets/TaskCard.vue'

// ----- grid edit state
const isEditable = ref(false)

// ----- shared query state
const unit   = ref('VKEH') // default must match backend base
const period = ref(new Date().toISOString().slice(0,7)) // YYYY-MM

// ----- backend state
const kpiItems        = ref([])   // [{id,label,value,unit,details?}]
const chartCodes      = ref([])   // ['142','233',...]
const chartSeries     = ref([])   // [{name,data:[]},...]
const tableRowsRaw    = ref([])   // [{pc_code,pc_name,ist,prognose,budget}]
const tableTotalsRaw  = ref({})   // {ist,prognose,budget,unit}
const calendarEvents  = ref([])   // [{start,end,title,content,status,...}]
const loading         = ref(false)
const errorMsg        = ref('')

// ----- helpers
function toDate(val){
  if (!val) return null
  if (val instanceof Date) return val
  if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}/.test(val)) return new Date(val + 'T00:00:00')
  return new Date(val)
}
function ddmmyyyy(d){
  const dt = toDate(d) || new Date()
  const dd = String(dt.getDate()).padStart(2,'0')
  const mm = String(dt.getMonth()+1).padStart(2,'0')
  const yyyy = dt.getFullYear()
  return `${dd}-${mm}-${yyyy}`
}

// ----- data loader
async function fetchDashboard() {
  loading.value = true
  errorMsg.value = ''
  try {
    const { data } = await api.get('/api/dashboard', {
      params: { unit: unit.value, period: period.value }
    })
    kpiItems.value       = data?.kpis?.items ?? []
    chartCodes.value     = data?.chart?.codes ?? data?.chart?.labels ?? []
    chartSeries.value    = data?.chart?.series ?? []
    tableRowsRaw.value   = data?.table?.rows ?? []
    tableTotalsRaw.value = data?.table?.totals ?? { ist:0, prognose:0, budget:0, unit: unit.value }
    calendarEvents.value = data?.calendar?.events ?? []
  } catch (e) {
    console.error('Dashboard load failed', e)
    errorMsg.value = 'Fehler beim Laden.'
  } finally {
    loading.value = false
  }
}
onMounted(fetchDashboard)
watch(unit, fetchDashboard)

function changeUnit(next) {
  if (next !== unit.value) unit.value = next
}

// ----- KPI mapping into 4 cards (backend ids)
const kpisById = computed(() => {
  const by = Object.fromEntries(kpiItems.value.map(i => [i.id, i]))
  return {
    ist_vs_prognose:     by['ist_vs_prognose']     || { label: 'Ist vs Prognose', value: 0, unit: '%' },
    ist_vs_budget:       by['ist_vs_budget']       || { label: 'Ist vs Budget',   value: 0, unit: '%' },
    diff_ist_budget_m3:  by['diff_ist_budget_m3']  || { label: 'Differenz Ist–Budget', value: 0, unit: 'M3' },
    umsatz_eur:          by['umsatz_eur']          || { label: 'Gesamtumsatz', value: 0, unit: 'EUR' }
  }
})

// ----- chart + table computed
const radarLabels = computed(() => chartCodes.value) // codes only
const radarSeries = computed(() => chartSeries.value)

const tableRows = computed(() =>
  (tableRowsRaw.value || []).map(r => ({
    pcId: r.pc_code,
    pcName: r.pc_name,
    sales: r.ist ?? 0,
    forecast: r.prognose ?? 0,
    budget: r.budget ?? 0
  }))
)
const tableTotals = computed(() => ({
  sales: tableTotalsRaw.value?.ist ?? 0,
  forecast: tableTotalsRaw.value?.prognose ?? 0,
  budget: tableTotalsRaw.value?.budget ?? 0
}))

// ----- tasks from today's calendar events
const todayISO = new Date().toISOString().slice(0,10)
const tasksToday = computed(() => {
  return (calendarEvents.value || [])
    .filter(e => {
      const s = toDate(e.start)
      return s && s.toISOString().slice(0,10) === todayISO
    })
    .map(e => ({
      title: e.title || 'Maßnahme',
      done: false,
      date: ddmmyyyy(e.start)
    }))
})

// ----- layout
const layout = ref([
  { i: '0',  x: 0,  y: 0,  w: 2, h: 4,  type: 'kpi',  kpiId: 'ist_vs_prognose' },
  { i: '1',  x: 2,  y: 0,  w: 2, h: 4,  type: 'kpi',  kpiId: 'ist_vs_budget' },
  { i: '2',  x: 4,  y: 0,  w: 2, h: 4,  type: 'kpi',  kpiId: 'diff_ist_budget_m3' },
  { i: '3',  x: 6,  y: 0,  w: 2, h: 4,  type: 'kpi',  kpiId: 'umsatz_eur' },
  { i: '4',  x: 8,  y: 0,  w: 4, h: 12, type: 'calendar' },
  { i: '7',  x: 0,  y: 4,  w: 5, h: 17, type: 'chart' },
  { i: '8',  x: 5,  y: 4,  w: 3, h: 17, type: 'table' },
  { i: '9',  x: 8,  y: 12, w: 4, h: 6,  type: 'tasks' },
])

// ----- titles
function displayUnit(u){
  if (!u) return ''
  const U = String(u).toUpperCase()
  if (U === 'M3') return 'm³'
  if (U === 'EUR') return '€'
  if (U === 'VKEH') return 'VK-EH'
  if (u === '%') return '%'
  return u
}
function getTitle(item) {
  if (item.type === 'kpi') {
    const k = kpisById.value[item.kpiId] ?? { label: 'KPI', unit: '' }
    const u = k.unit ? ` (${displayUnit(k.unit)})` : ''
    return `${k.label}${u}`
  }
  return {
    calendar: 'Kalender',
    chart: 'Diagramm',
    table: 'Profit-Center',
    tasks: 'Aufgaben (Heute)'
  }[item.type] || 'Widget'
}

// ----- component registry
function getWidgetComponent(type) {
  return {
    kpi: KpiCard,
    calendar: CalendarCard,
    chart: ChartCard,
    table: ProfitCentersTable,
    tasks: TaskCard
  }[type] || null
}

// ----- props per widget
function getPropsForType(item) {
  if (item.type === 'kpi') {
    return { modelValue: item.kpiId, kpis: kpisById.value, unit: unit.value }
  }
  if (item.type === 'chart') {
    return { labels: radarLabels.value, series: radarSeries.value, unit: unit.value }
  }
  if (item.type === 'table') {
    return { rows: tableRows.value, totals: tableTotals.value, unit: unit.value }
  }
  if (item.type === 'calendar') {
    // Convert start/end to Date and decorate title with DD-MM-YYYY for display purposes
    const evs = (calendarEvents.value || []).map(e => {
      const start = toDate(e.start)
      const end   = toDate(e.end ?? e.start)
      const title = e.title ? `${ddmmyyyy(start)} • ${e.title}` : ddmmyyyy(start)
      return { ...e, start, end, title }
    })
    return { events: evs }
  }
  if (item.type === 'tasks') {
    return { tasks: tasksToday.value }
  }
  return {}
}
</script>

<style scoped>
.dash-wrapper{ width:100%; }
.grid-widget{
  height:100%; width:100%; box-sizing:border-box; background:transparent; border:0; border-radius:0;
}
.grid-placeholder{
  height:100%; width:100%; background:transparent; color:#111827;
  display:flex; align-items:center; justify-content:center;
}
.err{
  margin-top:8px; padding:6px 10px; border-radius:8px;
  background: rgba(239,68,68,.08); color:#7f1d1d; border:1px solid rgba(239,68,68,.35);
}

/* Header unit toggle */
.unit-toggle{
  display:flex; gap:6px;
  background: rgba(255,255,255,.35);
  border: 1px solid rgba(0,0,0,.08);
  border-radius: 8px;
  padding: 2px;
}
.u-btn{
  border:0; background:transparent; padding:.25rem .5rem; font-size:.8rem; cursor:pointer; border-radius:6px;
}
.u-btn.active{
  background: rgba(31,86,115,.8); color:#fff; font-weight:700;
}
</style>