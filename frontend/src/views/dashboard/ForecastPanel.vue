<template>
  <div class="forecast-wrapper">
    <Toast />
    <GridLayout
      :layout="layout"
      :col-num="12"
      :row-height="8"
      :is-draggable="false"
      :is-resizable="false"
      :margin="[10,10]"
      :use-css-transforms="true"
    >
      <GridItem v-for="item in layout" :key="item.i + '-' + item.x + '-' + item.y" :i="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h">
        <GlassCard>
          <!-- Filtros siempre visibles -->
          <div v-if="item.i==='filters'" class="h-full p-3">
            <ForecastFilters
              :mode="mode"
              :primary-options="primaryOptions"
              :primary-id="primaryId"
              :secondary-options="secondaryOptions"
              :secondary-id="secondaryId"
              @update:mode="handleModeChange"
              @update:primary-id="v=>primaryId=v"
              @update:secondary-id="v=>secondaryId=v"
              @next="handleNext"
            />
          </div>

          <!-- Título solo con selección -->
          <div v-else-if="item.i==='title'" class="h-full p-3 flex align-items-center">
            <ForecastTitle v-if="hasSelection" :client="selectedClienteName" :kunde="selectedClienteName" :pc="selectedPCName" />
          </div>

          <!-- Chart principal: usa loader interno con :busy y datos reactivos -->
          <div v-else-if="item.i==='chart' && item.x===2" class="h-full">
            <LineChartSmart
              v-if="hasSelection"
              type="cumulative"
              :client-id="currentClientId"
              :profit-center-id="currentPcId"
              api-prefix="/api"
              :auto-fetch="false"
              :cum-data="liveCumData"
              :busy="loading"
            />
          </div>

          <!-- Chart versiones: fetch propio y loader interno -->
          <div v-else-if="item.i==='chart' && item.x===9" class="h-full">
            <LineChartSmart
              v-if="hasSelection"
              type="versions"
              :client-id="currentClientId"
              :profit-center-id="currentPcId"
              api-prefix="/api"
              :auto-fetch="true"
            />
          </div>

          <!-- Tabla solo con selección -->
          <div v-else-if="item.i==='table'" class="h-full">
            <template v-if="hasSelection">
              <ForecastTable
                :months="months"
                :ventas="sales"
                :budget="budget"
                :forecast="forecast"
                @edit-forecast="({index,value}) => { const n=Number(value); forecast[index]=isNaN(n)?0:n }"
              />
              <div class="mt-3 flex gap-2 justify-content-end">
                <Button label="Zurücksetzen (Budget)" icon="pi pi-refresh" severity="secondary" @click="resetForecastToBudget" />
                <Button label="Speichern" icon="pi pi-save" @click="saveForecast" />
              </div>
            </template>
          </div>
        </GlassCard>
      </GridItem>
    </GridLayout>
  </div>
</template>



<script setup>
/* All code in English; UI texts live in child components */
import { ref, computed, watch, onMounted } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import ForecastTitle from '@/components/titles/ComponentTitle.vue'
import ForecastFilters from '@/components/filters/ComponentFilter.vue'
import ForecastTable from '@/components/tables/ComponentTable.vue'
import GlassCard from '@/components/ui/GlassCard.vue'
import LineChartSmart from '@/components/charts/LineChartSmart.vue'

const toast = useToast()
const API = '/api'

/* Master data */
const clients = ref([])
const profitCenters = ref([])
const mapClientToPC = ref({})
const mapPCToClient = ref({})

/* Filters */
const mode = ref('')               // '' | 'client' | 'pc'
const primaryId = ref(null)
const secondaryId = ref(null)
const loading = ref(false)

/* Helpers */
function genMonths(n){
  const out=[], base=new Date(); base.setDate(1)
  for(let i=0;i<n;i++){ const d=new Date(base.getFullYear(), base.getMonth()+i, 1)
    out.push(`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`) }
  return out
}
function fillZeros(n){ return Array(n).fill(0) }
function normalizeMode(v){
  if (!v) return ''
  const s = String(v).toLowerCase().trim()
  if (['client','cliente','kunde','cunde'].includes(s)) return 'client'
  if (['pc','profit','profitcenter','profit center'].includes(s)) return 'pc'
  return ''
}

/* Series */
const months   = ref(genMonths(18))
const sales    = ref(fillZeros(18))
const budget   = ref(fillZeros(18))
const forecast = ref(fillZeros(18))
const orders   = ref(fillZeros(18))

/* Selection guard */
const hasSelection = computed(() => !!mode.value && primaryId.value != null && secondaryId.value != null)

/* Version history (side chart) */
const versionHistory = ref(null)
async function loadCurrentMonthVersions () {
  if (!hasSelection.value) return
  await ensureCsrf()
  const clientId       = (mode.value === 'client') ? primaryId.value   : secondaryId.value
  const profitCenterId = (mode.value === 'client') ? secondaryId.value : primaryId.value
  const res = await api.get(API + '/forecast/current-month-versions', { params: { clientId, profitCenterId } })
  versionHistory.value = res.data
}

/* Options */
const primaryOptions = computed(() => {
  if (mode.value === 'client') return clients.value.map(c => ({ label: c.name, value: c.id }))
  if (mode.value === 'pc')     return profitCenters.value.map(p => ({ label: `${p.code} — ${p.name}`, value: p.id }))
  return []
})
const secondaryOptions = computed(() => {
  if (!mode.value || primaryId.value == null) return []
  if (mode.value === 'client') {
    const ids = mapClientToPC.value[primaryId.value] || []
    const out = []
    for (let i = 0; i < ids.length; i++) {
      const id = ids[i]
      const p = profitCenters.value.find(x => x.id === id)
      if (p) out.push({ label: `${p.code} — ${p.name}`, value: p.id })
    }
    return out
  } else {
    const ids = mapPCToClient.value[primaryId.value] || []
    const out = []
    for (let i = 0; i < ids.length; i++) {
      const id = ids[i]
      const c = clients.value.find(x => x.id === id)
      if (c) out.push({ label: c.name, value: c.id })
    }
    return out
  }
})

/* Title computed */
const _titleClientName = computed(() => {
  if (mode.value === 'client') {
    const c = clients.value.find(x => x.id === primaryId.value)
    return c ? c.name : ''
  } else {
    const c = clients.value.find(x => x.id === secondaryId.value)
    return c ? c.name : ''
  }
})
const _titlePCName = computed(() => {
  const pcId = (mode.value === 'client') ? secondaryId.value : primaryId.value
  const p = profitCenters.value.find(x => x.id === pcId)
  return p ? `${p.code} — ${p.name}` : ''
})
const selectedClienteName = _titleClientName
const selectedPCName = _titlePCName

/* Master fetch */
async function loadMaster () {
  try {
    await ensureCsrf()
    const resClients = await api.get(API + '/me/clients')
    const resPCs     = await api.get(API + '/me/profit-centers')
    const resMap     = await api.get(API + '/me/assignments')
    clients.value       = Array.isArray(resClients.data) ? resClients.data : []
    profitCenters.value = Array.isArray(resPCs.data) ? resPCs.data : []
    mapClientToPC.value = (resMap.data && resMap.data.clientToPc) ? resMap.data.clientToPc : {}
    mapPCToClient.value = (resMap.data && resMap.data.pcToClient) ? resMap.data.pcToClient : {}
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Stammdaten nicht verfügbar', life:2500 })
  }
}

/* Clear series */
function clearSeries () {
  months.value   = genMonths(18)
  sales.value    = fillZeros(18)
  budget.value   = fillZeros(18)
  forecast.value = fillZeros(18)
  orders.value   = fillZeros(18)
  versionHistory.value = null
}

/* Series fetch */
async function loadSeries () {
  if (!hasSelection.value) return
  loading.value = true
  try {
    await ensureCsrf()
    const clientId       = (mode.value === 'client') ? primaryId.value   : secondaryId.value
    const profitCenterId = (mode.value === 'client') ? secondaryId.value : primaryId.value
    const res = await api.get(API + '/forecast/series', { params: { clientId, profitCenterId } })
    const data = res.data || {}
    months.value   = Array.isArray(data.months)   && data.months.length   ? data.months   : genMonths(18)
    sales.value    = Array.isArray(data.sales)    && data.sales.length    ? data.sales    : fillZeros(18)
    budget.value   = Array.isArray(data.budget)   && data.budget.length   ? data.budget   : fillZeros(18)
    forecast.value = Array.isArray(data.forecast) && data.forecast.length ? data.forecast : fillZeros(18)
    orders.value   = Array.isArray(data.orders)   && data.orders.length   ? data.orders   : fillZeros(18)
    await loadCurrentMonthVersions()
  } finally { loading.value = false }
}

/* Save */
async function saveForecast () {
  if (!hasSelection.value) return
  try {
    await ensureCsrf()
    const clientId       = (mode.value === 'client') ? primaryId.value   : secondaryId.value
    const profitCenterId = (mode.value === 'client') ? secondaryId.value : primaryId.value
    await api.put(API + '/forecast/series', { clientId, profitCenterId, months: months.value, forecast: forecast.value })
    toast.add({ severity:'success', summary:'Gespeichert', detail:'Forecast aktualisiert', life:2000 })
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Speichern fehlgeschlagen', life:2500 })
  }
}
function resetForecastToBudget () {
  budget.value = Array.isArray(budget.value) ? budget.value : []
  forecast.value = budget.value.slice()
}

/* Live cumulative data for main chart */
function cumulateToLen(arr, len){
  const out = []; let s = 0
  for (let i=0;i<len;i++){ s += Number(arr?.[i] ?? 0); out.push(s) }
  return out
}
const liveCumData = computed(() => {
  if (!hasSelection.value) return null
  const len = months.value?.length || 0
  const salesCum    = cumulateToLen(sales.value, len)
  const budgetCum   = cumulateToLen(budget.value, len)
  const forecastCum = cumulateToLen(forecast.value, len)
  const fy = budgetCum.length ? Number(budgetCum[budgetCum.length-1] || 0) : 0
  return {
    months: months.value || [],
    sales_cum: salesCum,
    budget_cum: budgetCum,
    forecast_cum: forecastCum,
    budget_fy_line: Array(len).fill(fy)
  }
})

/* UI events */
function handleModeChange (v) {
  const n = normalizeMode(v)
  mode.value = n
  primaryId.value = null
  secondaryId.value = null
  clearSeries()
}
function handleNext () {
  const list = secondaryOptions.value
  if (!list || !list.length) return
  const idx = list.findIndex(o => o.value === secondaryId.value)
  const n = (idx >= 0 ? (idx + 1) : 0) % list.length
  secondaryId.value = list[n].value
}

/* React */
watch([mode, primaryId], () => { secondaryId.value = null; clearSeries() })
watch(secondaryId, () => { loadSeries() })

/* Mount */
onMounted(() => { loadMaster() })

/* Grid */
const layout = ref([
  { i:'filters', x:0,  y:0,  w:2,  h:47, static:true },
  { i:'title',   x:2,  y:0,  w:10, h:4,  static:true },
  { i:'chart',   x:2,  y:4,  w:7,  h:26, static:true },
  { i:'chart',   x:9,  y:4,  w:3,  h:26, static:true },
  { i:'table',   x:2,  y:30, w:10, h:17, static:true }
])

/* IDs actuales para charts */
const currentClientId = computed(() => mode.value==='client' ? primaryId.value : secondaryId.value)
const currentPcId     = computed(() => mode.value==='client' ? secondaryId.value : primaryId.value)
</script>

<style scoped>
.forecast-wrapper{ 
  height: 100vh;
  width: 100%; 
  overflow: hidden; 
}
</style>
