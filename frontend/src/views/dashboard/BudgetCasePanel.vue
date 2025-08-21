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
      <GridItem
        v-for="item in layout"
        :key="item.i + '-' + item.x + '-' + item.y"
        :i="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h"
      >
        <GlassCard :class="{ 'no-strip': item.type==='title' }" :title="getTitle(item)">
          <!-- FILTERS -->
          <div v-if="item.type==='filters'" class="h-full p-3">
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

          <!-- TITLE (no header strip) + Save button (Budget Case) -->
          <div v-else-if="item.type==='title'" class="h-full p-3 flex align-items-center justify-content-between">
            <ForecastTitle
              v-if="hasSelection"
              :client="selectedClienteName"
              :kunde="selectedClienteName"
              :pc="selectedPCName"
            />
            <div v-if="hasSelection" class="flex gap-2">
              <Button
                label="Speichern"
                icon="pi pi-save"
                :disabled="!budgetDirty"
                @click="saveBudgetCase"
              />
            </div>
          </div>

          <!-- MAIN CHART -->
          <div v-else-if="item.type==='chart'" class="h-full">
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

          <!-- BUDGET CASE PANEL -->
          <div v-else-if="item.type==='cases'" class="h-full p-2">
            <BudgetCasePanel
              v-if="hasSelection"
              ref="bcRef"
              :client-id="currentClientId"
              :profit-center-id="currentPcId"
              @dirty-change="v => budgetDirty = !!v"
              @simulated="applySimulation"
            />
          </div>

          <!-- TABLE (readonly) -->
          <div v-else-if="item.type==='table'" class="h-full">
            <div class="blocked">
              <ForecastTable
                v-if="hasSelection"
                :months="months"
                :ventas="sales"
                :budget="budget"
                :forecast="forecast"
                @edit-forecast="() => {}"
              />
              <div class="overlay" aria-hidden="true" title="Deaktiviert"></div>
            </div>
          </div>

          <div v-else class="grid-placeholder">Widget {{ item.i }}</div>
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
import BudgetCasePanel from '@/components/elements/BudgetCaseItem.vue'

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

/* Series (backend reading preserved) */
const months   = ref(genMonths(18))
const sales    = ref(fillZeros(18))
const budget   = ref(fillZeros(18))
const forecast = ref(fillZeros(18))
const orders   = ref(fillZeros(18))

/* Keep baseline (table is readonly though) */
const originalForecast = ref(fillZeros(12))

/* Selection guard */
const hasSelection = computed(() => !!mode.value && primaryId.value != null && secondaryId.value != null)

/* Version history (kept) */
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
const selectedClienteName = computed(() => {
  if (mode.value === 'client') {
    const c = clients.value.find(x => x.id === primaryId.value)
    return c ? c.name : ''
  } else {
    const c = clients.value.find(x => x.id === secondaryId.value)
    return c ? c.name : ''
  }
})
const selectedPCName = computed(() => {
  const pcId = (mode.value === 'client') ? secondaryId.value : primaryId.value
  const p = profitCenters.value.find(x => x.id === pcId)
  return p ? `${p.code} — ${p.name}` : ''
})

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

/* Clear series (+ clear overlays) */
const overlayBest = ref(null)
const overlayWorst = ref(null)
function clearSeries () {
  months.value   = genMonths(18)
  sales.value    = fillZeros(18)
  budget.value   = fillZeros(18)
  forecast.value = fillZeros(18)
  orders.value   = fillZeros(18)
  originalForecast.value = fillZeros(12)
  versionHistory.value = null
  overlayBest.value = null
  overlayWorst.value = null
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
    // keep any simulation overlays; months may have changed -> optional: you could recompute here if needed
  } finally { loading.value = false }
}

/* Diff detection (kept) */
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

/* Normalizers + rules */
function yyyymm(d){ return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}` }
function toYYYYMM(d){ return yyyymm(d) }

function thirdWednesday(d=new Date()){
  const first = new Date(d.getFullYear(), d.getMonth(), 1)
  const wd = first.getDay()
  const deltaToWed = (3 - wd + 7) % 7
  const firstWed = new Date(first); firstWed.setDate(1 + deltaToWed)
  const third = new Date(firstWed); third.setDate(firstWed.getDate() + 14)
  return third
}
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

/* saveForecast (kept, not used here) */
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
    originalForecast.value = forecast12.slice()

    toast.add({ severity:'success', summary:'Gespeichert', detail: `${saved} Änderung${saved===1?'':'en'} gespeichert`, life: 2200 })
    await loadSeries()
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Speichern fehlgeschlagen', life:2500 })
  }
}

/* Cumulative helpers */
function cumulateToLen(arr, len){
  const out = []; let s = 0
  for (let i=0;i<len;i++){ s += Number(arr?.[i] ?? 0); out.push(s) }
  return out
}
function pad2(n){ return String(n).padStart(2,'0') }

/* Apply simulation overlays from child */
function applySimulation({ targetFY, seriesTarget }) {
  if (!Array.isArray(months.value) || !months.value.length) return
  const len = months.value.length
  const bestMon = Array(len).fill(0)
  const worstMon = Array(len).fill(0)

  for (const row of (seriesTarget || [])) {
    const m = Number(row.month) || 0
    if (m < 1 || m > 12) continue
    const y = (m >= 4) ? Number(targetFY) : (Number(targetFY) + 1)
    const key = `${y}-${pad2(m)}`
    const idx = months.value.findIndex(x => String(x) === key)
    if (idx >= 0) {
      bestMon[idx]  = Number(row.best  ?? 0)
      worstMon[idx] = Number(row.worst ?? 0)
    }
  }

  overlayBest.value  = cumulateToLen(bestMon, len)
  overlayWorst.value = cumulateToLen(worstMon, len)
}

/* Live cumulative data for main chart (adds overlays if present) */
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
    budget_fy_line: Array(len).fill(fy),
    ...(overlayBest.value  ? { overlay_best:  overlayBest.value }  : {}),
    ...(overlayWorst.value ? { overlay_worst: overlayWorst.value } : {})
  }
})

/* Unsaved (Budget Case) */
const bcRef = ref(null)
const budgetDirty = ref(false)

const confirmVisible = ref(false)
const pendingChange = ref(null)
const hasUnsaved = computed(() => !!budgetDirty.value)

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
  if (hasUnsaved.value) {
    pendingChange.value = { kind, value }
    confirmVisible.value = true
  } else {
    applyChange(kind, value)
  }
}
async function saveBudgetCase(){
  if (!bcRef.value) return
  await bcRef.value.save()
  budgetDirty.value = false
  toast.add({ severity:'success', summary:'Gespeichert', detail:'Budget-Fall gespeichert', life:2200 })
}
async function saveAndApply(){
  try { if (hasUnsaved.value) await saveBudgetCase() } finally {
    confirmVisible.value = false
    if (pendingChange.value){ applyChange(pendingChange.value.kind, pendingChange.value.value) }
    pendingChange.value = null
  }
}
function discardAndApply(){
  if (bcRef.value) bcRef.value.reset?.()
  budgetDirty.value = false
  confirmVisible.value = false
  if (pendingChange.value){ applyChange(pendingChange.value.kind, pendingChange.value.value) }
  pendingChange.value = null
}

/* Next */
function handleNext () {
  const list = secondaryOptions.value
  if (!list || !list.length) return
  const idx = list.findIndex(o => o.value === secondaryId.value)
  const n = (idx >= 0 ? (idx + 1) : 0) % list.length
  guardedChange('secondary', list[n].value)
}

/* React */
watch([mode, primaryId], () => {})
watch(secondaryId, () => { loadSeries() })

/* Mount */
onMounted(() => { loadMaster() })

/* Grid */
const layout = ref([
  { i:'filters', x:0,  y:0,  w:2,  h:47, static:true, type:'filters' },
  { i:'title',   x:2,  y:0,  w:10, h:4,  static:true, type:'title'   },
  { i:'chart',   x:2,  y:4,  w:7,  h:26, static:true, type:'chart'   },
  { i:'cases',   x:9,  y:4,  w:3,  h:26, static:true, type:'cases'   },
  { i:'table',   x:2,  y:30, w:10, h:17, static:true, type:'table'   }
])
function getTitle(item){
  if (item.type === 'title')   return ''
  if (item.type === 'filters') return 'Filter'
  if (item.type === 'chart')   return 'Diagramm'
  if (item.type === 'cases')   return 'Budget-Fall'
  if (item.type === 'table')   return 'Tabelle'
  return ''
}

/* IDs */
const currentClientId = computed(() => mode.value==='client' ? primaryId.value : secondaryId.value)
const currentPcId     = computed(() => mode.value==='client' ? secondaryId.value : primaryId.value)

// Front helper: DUMP and print EVERYTHING in console (no UI changes needed)
const body = { client_profit_center_id:490};

fetch('/api/budget-cases/dump', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify(body)
})
.then(async res => {
  const trace = res.headers.get('X-Dump-Trace');
  const hdr   = res.headers.get('X-Dump');
  const json  = await res.json();
  console.group(`BUDGET-CASE DUMP [trace=${trace}]`);
  try { console.log('X-Dump HEADER', JSON.parse(hdr || '{}')); } catch (e) { console.log('X-Dump HEADER (raw)', hdr); }
  console.log('BODY', json);
  console.groupEnd();
})
.catch(console.error);

</script>

<style scoped>
.forecast-wrapper{ height: 100vh; width: 100%; overflow: hidden; }

/* Hide header strip ONLY for 'title' */
.no-strip :deep(.card-header),
.no-strip :deep(.glass-title),
.no-strip :deep(.p-card-header){
  display: none !important;
}

/* Disabled table overlay */
.blocked{ position: relative; height: 100%; }
.blocked .overlay{
  position:absolute; inset:0;
  cursor: not-allowed;
  z-index: 2;
}

.grid-placeholder{
  height:100%; width:100%;
  display:flex; align-items:center; justify-content:center;
  color:#111827;
}
</style>