<template>
  <div class="forecast-wrapper">
    <Toast />

    <!-- Unsaved changes modal -->
    <Dialog
      v-model:visible="confirmVisible"
      :modal="true"
      :draggable="false"
      :dismissableMask="true"
      header="Ungespeicherte Änderungen"
      :style="{ width:'520px' }"
    >
      <p class="mb-3">Es gibt nicht gespeicherte Änderungen. Möchtest du sie speichern?</p>
      <div class="flex justify-content-end gap-2">
        <Button label="Abbrechen" severity="secondary" @click="confirmVisible=false; pendingChange=null" />
        <Button label="Verwerfen" severity="danger" @click="discardAndApply" />
        <Button label="Speichern" icon="pi pi-save" @click="saveAndApply" />
      </div>
    </Dialog>

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
          <!-- Filtros -->
          <div v-if="item.i==='filters'" class="h-full p-3">
            <ForecastFilters
              :mode="mode"
              :primary-options="primaryOptions"
              :primary-id="primaryId"
              :secondary-options="secondaryOptions"
              :secondary-id="secondaryId"
              @update:mode="v => guardedChange('mode', normalizeMode(v))"
              @update:primary-id="v => guardedChange('primary', v)"
              @update:secondary-id="v => guardedChange('secondary', v)"
              @next="handleNext"
            />
            <div class="mt-3 text-500 text-sm" v-if="loading">Lädt…</div>
          </div>

          <!-- Título + acciones -->
          <div v-else-if="item.i==='title'" class="h-full p-3 flex align-items-center justify-content-between">
            <ForecastTitle v-if="hasSelection" :client="selectedClienteName" :kunde="selectedClienteName" :pc="selectedPCName" />
            <div v-if="hasSelection" class="flex gap-2">
              <Button label="Speichern" icon="pi pi-save" :disabled="changedCount===0" @click="saveForecast" />
            </div>
          </div>

          <!-- Chart principal -->
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

          <!-- Chart versiones -->
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

          <!-- Tabla -->
          <div v-else-if="item.i==='table'" class="h-full">
            <template v-if="hasSelection">
              <ForecastTable
                :months="months"
                :ventas="sales"
                :budget="budget"
                :forecast="forecast"
                @edit-forecast="({index,value}) => { const n=Number(value); forecast[index]=isNaN(n)?0:n }"
              />
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
import Dialog from 'primevue/dialog'
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
function isClose(a,b,eps=1e-6){ return Math.abs(Number(a||0)-Number(b||0)) <= eps }

/* Series */
const months   = ref(genMonths(18))
const sales    = ref(fillZeros(18))
const budget   = ref(fillZeros(18))
const forecast = ref(fillZeros(18))
const orders   = ref(fillZeros(18))

/* Baseline para detectar cambios (usa 12 slots del back) */
const originalForecast = ref(fillZeros(12))

/* Selection guard */
const hasSelection = computed(() => !!mode.value && primaryId.value != null && secondaryId.value != null)

/* Version history (solo trigger para el chart lateral) */
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

/* Title */
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
    toast.add({ severity:'error', summary:'Fehler', detail:'Stammdaten nicht verfügbar', life:5000 })
  }
}

/* Clear series */
function clearSeries () {
  months.value   = genMonths(18)
  sales.value    = fillZeros(18)
  budget.value   = fillZeros(18)
  forecast.value = fillZeros(18)
  orders.value   = fillZeros(18)
  originalForecast.value = fillZeros(12)
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
    originalForecast.value = (Array.isArray(data.forecast) ? data.forecast.slice(0,12) : fillZeros(12))
    await loadCurrentMonthVersions()
  } finally { loading.value = false }
}

/* ---- Diff detection (solo guarda si cambió) ---- */
const changedIndices = computed(() => {
  const cur = (forecast.value || []).slice(0,12)
  const base = (originalForecast.value || []).slice(0,12)
  const out = []
  for (let i=0;i<12;i++){
    const ym = months.value?.[i]
    if (!isEditableYM(ym)) continue
    if (!isClose(cur[i], base[i])) out.push(i)
  }
  return out
})
const changedCount = computed(() => changedIndices.value.length)


/* ---- Normalizadores a 12 para contrato del back ---- */
function yyyymm(d){ return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}` }

function thirdWednesday(d=new Date()){
  const first = new Date(d.getFullYear(), d.getMonth(), 1)
  const wd = first.getDay() // 0=Dom..3=Mié
  const deltaToWed = (3 - wd + 7) % 7
  const firstWed = new Date(first); firstWed.setDate(1 + deltaToWed)
  const third = new Date(firstWed); third.setDate(firstWed.getDate() + 14)
  return third
}

/* Regla:
   - Bloquea meses <= mes actual.
   - Permite el mes siguiente solo hasta 3er miércoles del mes actual.
   - Meses posteriores siempre editables. */
function isEditableYM(ym){
  if (!ym) return false
  const now = new Date()
  const cur = new Date(now.getFullYear(), now.getMonth(), 1)
  const [yS,mS] = String(ym).split('-'); const y = +yS, m = +mS
  const target = new Date(y, m-1, 1)

  if (target <= cur) return false

  const next = new Date(cur.getFullYear(), cur.getMonth()+1, 1)
  if (target.getTime() === next.getTime()) {
    return now <= thirdWednesday(now)
  }
  return true
}


function build12FromFirst(ym){
  const [y,m] = ym.split('-').map(n=>parseInt(n,10))
  const base = new Date(y, m-1, 1)
  const out = []
  for(let i=0;i<12;i++){ const d=new Date(base.getFullYear(), base.getMonth()+i, 1); out.push(toYYYYMM(d)) }
  return out
}
function coerceLen12Months(monthsArr){
  if (Array.isArray(monthsArr) && monthsArr.length === 12) return monthsArr
  if (Array.isArray(monthsArr) && monthsArr.length > 0)   return build12FromFirst(monthsArr[0])
  return genMonths(12)
}
function coerceLen12Forecast(forecastArr){
  const a = Array.isArray(forecastArr) ? forecastArr : []
  const out = []
  for(let i=0;i<12;i++){ const v = Number(a[i] ?? 0); out.push(isNaN(v)||v<0?0:v) }
  return out
}

async function saveForecast () {
  if (!hasSelection.value) return
  if (changedCount.value === 0) {
    toast.add({ severity:'info', summary:'Keine Änderungen', detail:'Es gibt nichts zu speichern', life:1600 })
    return
  }
  try {
    await ensureCsrf()
    const clientId       = (mode.value === 'client') ? primaryId.value   : secondaryId.value
    const profitCenterId = (mode.value === 'client') ? secondaryId.value : primaryId.value
    const months12   = coerceLen12Months(months.value)
    const forecast12 = coerceLen12Forecast(forecast.value)

    const { data } = await api.put(API + '/forecast/series', {
      clientId, profitCenterId, months: months12, forecast: forecast12
    })

    const saved = Number(data?.changed_count ?? data?.saved ?? 0)

    // baseline
    originalForecast.value = forecast12.slice()

    toast.add({
      severity:'success',
      summary:'Gespeichert',
      detail: `${saved} Änderung${saved===1?'':'en'} gespeichert`,
      life: 2200
    })

    await loadSeries()
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Speichern fehlgeschlagen', life:2500 })
  }
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

/* Guarded filter changes (unsaved changes dialog) */
const confirmVisible = ref(false)
const pendingChange = ref(null) // { kind: 'mode'|'primary'|'secondary', value: any }

function applyChange(kind, value){
  if (kind === 'mode') {
    mode.value = value
    primaryId.value = null
    secondaryId.value = null
    clearSeries()
  } else if (kind === 'primary') {
    primaryId.value = value
    secondaryId.value = null
  } else if (kind === 'secondary') {
    secondaryId.value = value
  }
}
function guardedChange(kind, value){
  if (changedCount.value > 0) {
    pendingChange.value = { kind, value }
    confirmVisible.value = true
  } else {
    applyChange(kind, value)
  }
}
async function saveAndApply(){
  try { await saveForecast() } finally {
    confirmVisible.value = false
    if (pendingChange.value){ applyChange(pendingChange.value.kind, pendingChange.value.value) }
    pendingChange.value = null
  }
}
function discardAndApply(){
  // revert to baseline (first 12 slots)
  const base = originalForecast.value.slice(0,12)
  for (let i=0;i<12;i++){ forecast.value[i] = Number(base[i] ?? 0) }
  confirmVisible.value = false
  if (pendingChange.value){ applyChange(pendingChange.value.kind, pendingChange.value.value) }
  pendingChange.value = null
}

/* Next cycles secondary guarded */
function handleNext () {
  const list = secondaryOptions.value
  if (!list || !list.length) return
  const idx = list.findIndex(o => o.value === secondaryId.value)
  const n = (idx >= 0 ? (idx + 1) : 0) % list.length
  guardedChange('secondary', list[n].value)
}

/* React */
watch([mode, primaryId], () => { /* changes handled via guardedChange/applyChange */ })
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