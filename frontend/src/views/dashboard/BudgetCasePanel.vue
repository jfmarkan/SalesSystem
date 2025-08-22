<template>
  <div class="forecast-wrapper">
    <Toast />

    <Dialog
      v-model:visible="confirmVisible"
      :modal="true"
      :draggable="false"
      :dismissableMask="true"
      header="Ungespeicherte Änderungen"
      :style="{ width: '520px' }"
    >
      <p class="mb-3">Es gibt nicht gespeicherte Änderungen. Möchtest du sie speichern?</p>
      <div class="flex justify-content-end gap-2">
        <Button
          label="Abbrechen"
          severity="secondary"
          @click="
            confirmVisible = false,
            pendingChange = null
          "
        />
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
      :margin="[10, 10]"
      :use-css-transforms="true"
    >
      <GridItem
        v-for="item in layout"
        :key="item.i"
        :i="item.i"
        :x="item.x"
        :y="item.y"
        :w="item.w"
        :h="item.h"
      >
        <GlassCard :class="{ 'no-strip': item.type === 'title' }" :title="getTitle(item)">
          <!-- FILTERS -->
          <div v-if="item.type === 'filters'" class="h-full p-3">
            <ForecastFilters
              :mode="mode"
              :primary-options="primaryOptions"
              :primary-id="primaryId"
              :secondary-options="secondaryOptions"
              :secondary-id="secondaryId"
              @update:mode="(v) => guardedChange('mode', normalizeMode(v))"
              @update:primary-id="(v) => guardedChange('primary', v)"
              @update:secondary-id="(v) => guardedChange('secondary', v)"
              @next="handleNext"
            />
            <div class="mt-3 text-500 text-sm" v-if="loading">Lädt…</div>
          </div>

          <!-- TITLE -->
          <div
            v-else-if="item.type === 'title'"
            class="h-full p-3 flex align-items-center justify-content-between"
          >
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

          <!-- CHART (usa cumDataForChart con overlays) -->
          <div v-else-if="item.type === 'chart'" class="h-full">
            <LineChartSmart
              v-if="hasSelection"
              type="cumulative"
              :client-id="currentClientId"
              :profit-center-id="currentPcId"
              api-prefix="/api"
              :auto-fetch="false"
              :cum-data="cumDataForChart"
              :busy="loading"
            />
          </div>

          <!-- CASES -->
          <div v-else-if="item.type === 'cases'" class="h-full p-2">
            <BudgetCasePanel
              v-if="hasSelection"
              :key="`${currentClientId}-${currentPcId}`"
              ref="bcRef"
              :client-group-number="cgnForChild"
              :profit-center-code="pccForChild"
              @dirty-change="v => budgetDirty = !!v"
              @values-change="onChildValues"
              @simulated="onSimulated"
            />
          </div>

          <!-- TABLE (readonly) -->
          <div v-else-if="item.type === 'table'" class="h-full">
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
import { ref, computed, watch, onMounted, nextTick } from 'vue'
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

/* Master data */
const clients = ref([])
const profitCenters = ref([])
const mapClientToPC = ref({})
const mapPCToClient = ref({})
const clientById = ref({})
const pcById = ref({})

/* Filters */
const mode = ref('')
const primaryId = ref(null)
const secondaryId = ref(null)
const loading = ref(false)

/* Helpers */
function genMonths(n) {
  const out = [], base = new Date()
  base.setDate(1)
  for (let i = 0; i < n; i++) {
    const d = new Date(base.getFullYear(), base.getMonth() + i, 1)
    out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
  }
  return out
}
function fillZeros(n) { return Array(n).fill(0) }
function normalizeMode(v) {
  if (!v) return ''
  const s = String(v).toLowerCase().trim()
  if (['client', 'cliente', 'kunde'].includes(s)) return 'client'
  if (['pc', 'profit', 'profitcenter', 'profit center'].includes(s)) return 'pc'
  return ''
}
function toNumberSafe(...vals) {
  for (const v of vals) {
    const n = Number(v)
    if (Number.isFinite(n)) return n
  }
  return null
}
function pad2(n) { return String(n).padStart(2, '0') }
function fiscalYearFromDate(d) {
  const m = d.getMonth() + 1, y = d.getFullYear()
  return m < 4 ? y - 1 : y
}

/* Series */
const months = ref(genMonths(18))
const sales = ref(fillZeros(18))
const budget = ref(fillZeros(18))
const forecast = ref(fillZeros(18))
const orders = ref(fillZeros(18))
const originalForecast = ref(fillZeros(12))

/* overlays para el chart */
const overlayBest = ref([])  // cumulativos
const overlayWorst = ref([]) // cumulativos

const hasSelection = computed(
  () => !!mode.value && primaryId.value != null && secondaryId.value != null,
)

/* Options */
const primaryOptions = computed(() => {
  if (mode.value === 'client') return clients.value.map((c) => ({ label: c.name, value: c.id }))
  if (mode.value === 'pc')
    return profitCenters.value.map((p) => ({ label: `${p.name}`, value: p.id }))
  return []
})
const secondaryOptions = computed(() => {
  if (!mode.value || primaryId.value == null) return []
  if (mode.value === 'client') {
    const ids = mapClientToPC.value[primaryId.value] || []
    return ids
      .map((id) => {
        const p = pcById.value[id]
        return p ? { label: `${p.code} — ${p.name}`, value: p.id } : null
      })
      .filter(Boolean)
  } else {
    const ids = mapPCToClient.value[primaryId.value] || []
    return ids
      .map((id) => {
        const c = clientById.value[id]
        return c ? { label: c.name, value: c.id } : null
      })
      .filter(Boolean)
  }
})

/* Titles */
const selectedClienteName = computed(() => {
  if (mode.value === 'client') return clientById.value[primaryId.value]?.name || ''
  return clientById.value[secondaryId.value]?.name || ''
})
const selectedPCName = computed(() => {
  const pcId = mode.value === 'client' ? secondaryId.value : primaryId.value
  const p = pcById.value[pcId]
  return p ? `${p.name}` : ''
})

/* Master fetch */
async function loadMaster() {
  try {
    await ensureCsrf()
    const resC = await api.get('/api/me/clients')
    const resP = await api.get('/api/me/profit-centers')
    const resM = await api.get('/api/me/assignments')

    clients.value = Array.isArray(resC.data) ? resC.data : []
    profitCenters.value = Array.isArray(resP.data) ? resP.data : []
    mapClientToPC.value = resM.data?.clientToPc || {}
    mapPCToClient.value = resM.data?.pcToClient || {}

    const cMap = {}
    for (const c of clients.value) cMap[c.id] = c
    clientById.value = cMap
    const pMap = {}
    for (const p of profitCenters.value) pMap[p.id] = p
    pcById.value = pMap
  } catch {
    toast.add({ severity: 'error', summary: 'Fehler', detail: 'Stammdaten nicht verfügbar', life: 5000 })
  }
}

/* Series load */

async function loadSeries() {
  if (!hasSelection.value) return
  loading.value = true
  try {
    await ensureCsrf()
    const clientId = mode.value === 'client' ? primaryId.value : secondaryId.value
    const profitCenterId = mode.value === 'client' ? secondaryId.value : primaryId.value
    const { data } = await api.get('/api/forecast/series', { params: { clientId, profitCenterId } })
    months.value = Array.isArray(data.months) && data.months.length ? data.months : genMonths(18)
    sales.value = Array.isArray(data.sales) && data.sales.length ? data.sales : fillZeros(18)
    budget.value = Array.isArray(data.budget) && data.budget.length ? data.budget : fillZeros(18)
    forecast.value = Array.isArray(data.forecast) && data.forecast.length ? data.forecast : fillZeros(18)
    orders.value = Array.isArray(data.orders) && data.orders.length ? data.orders : fillZeros(18)
    originalForecast.value = Array.isArray(data.forecast) ? data.forecast.slice(0, 12) : fillZeros(12)
  } finally {
    loading.value = false
  }
}

/* Chart data */
function cumulateToLen(arr, len) {
  const out = []
  let s = 0
  for (let i = 0; i < len; i++) { s += Number(arr?.[i] ?? 0); out.push(s) }
  return out
}
const liveCumData = computed(() => {
  if (!hasSelection.value) return null
  const len = months.value?.length || 0
  const salesCum = cumulateToLen(sales.value, len)
  const budgetCum = cumulateToLen(budget.value, len)
  const forecastCum = cumulateToLen(forecast.value, len)
  const fy = budgetCum.length ? Number(budgetCum[budgetCum.length - 1] || 0) : 0
  return {
    months: months.value || [],
    sales_cum: salesCum,
    budget_cum: budgetCum,
    forecast_cum: forecastCum,
    budget_fy_line: Array(len).fill(fy),
  }
})

/* ========= overlays Best/Worst para el gráfico ========= */
function toCum(arr) { const out=[]; let s=0; for (let i=0;i<arr.length;i++){ s+=Number(arr[i]||0); out.push(s) } return out }

/* Construye overlays a partir del emit del hijo: { seriesTarget: [{month, best, worst}, ...]} */
function onSimulated(payload) {
  const t = Array.isArray(payload?.seriesTarget) ? payload.seriesTarget : []
  const bestM = t.map((x) => Number(x?.best || 0))
  const worstM = t.map((x) => Number(x?.worst || 0))
  const bestC = toCum(bestM)
  const worstC = toCum(worstM)

  const len = months.value?.length || 0
  const dest = Math.min(12, len)
  const start = Math.max(0, len - dest)
  const B = Array(len).fill(0)
  const W = Array(len).fill(0)
  for (let i = 0; i < dest; i++) {
    B[start + i] = bestC[i] ?? 0
    W[start + i] = worstC[i] ?? 0
  }
  overlayBest.value = B
  overlayWorst.value = W
}

/* Mezcla liveCumData con overlays para el chart */
const cumDataForChart = computed(() => {
  const base = liveCumData.value
  if (!base) return null
  const out = { ...base }
  if (overlayBest.value.length === base.months.length) out.overlay_best = overlayBest.value
  if (overlayWorst.value.length === base.months.length) out.overlay_worst = overlayWorst.value
  return out
})

/* Budget Case guard */
const bcRef = ref(null)
const budgetDirty = ref(false)
const confirmVisible = ref(false)
const pendingChange = ref(null)
const hasUnsaved = computed(() => !!budgetDirty.value)

// Últimos valores vivos del hijo
const bestLatest = ref(0)
const worstLatest = ref(0)
function onChildValues({ best_case, worst_case }){
  bestLatest.value  = Number(best_case)  || 0
  worstLatest.value = Number(worst_case) || 0
  // mantener habilitado el botón Guardar si hay cambios
  budgetDirty.value = true
  console.log('[Parent] onChildValues ->', { best:bestLatest.value, worst:worstLatest.value })
}

/* GUARDAR en backend (no llama al save del hijo) */
async function saveBudgetCase() {
  if (!bcRef.value) return
  try {
    await ensureCsrf()

    // 1) intenta leer del método expuesto
    const fromChild = bcRef.value.getValues?.()
    let best  = Number(fromChild?.best_case)
    let worst = Number(fromChild?.worst_case)

    // 2) fallback a los últimos emitos
    if (!Number.isFinite(best))  best  = Number(bestLatest.value)  || 0
    if (!Number.isFinite(worst)) worst = Number(worstLatest.value) || 0

    console.log('[Parent] saving best/worst ->', { best, worst })

    // 🔴 IMPORTANTE: arma tu payload EXACTO (no cambio tus IDs/fiscal_year).
    // Si ya tenés armado el payload en otro lado, solo asegura que estos 2 campos
    // se envíen con estos valores:
    const fyNext = fiscalYearFromDate(new Date()) + 1
    const payload = {
      // usa tu esquema real:
      // client_profit_center_id: <TU_ID_OK>,
      // fiscal_year:             fyNext,
      best_case:  best,
      worst_case: worst,
      // si tu endpoint guarda por CGN/PCC en lugar de ID, añade:
      client_group_number: Number(cgnForChild.value),
      profit_center_code:  Number(pccForChild.value),
      fiscal_year:         fyNext,
    }

    console.log('[Parent] BudgetCase payload:', payload)
    await api.post('/api/budget-cases', payload, { withCredentials: true })

    bcRef.value?.markSaved?.()
    budgetDirty.value = false

    toast.add({ severity:'success', summary:'Gespeichert', detail:'Budget Case gespeichert', life:2200 })
  } catch (e) {
    console.error('[Parent] Save error:', e)
    const msg = e?.response?.data?.message || e?.message || 'Speichern fehlgeschlagen'
    toast.add({ severity:'error', summary:'Fehler', detail: msg, life: 3000 })
    throw e
  }
}

function guardedChange(kind, value) {
  if (hasUnsaved.value) {
    pendingChange.value = { kind, value }
    confirmVisible.value = true
  } else {
    applyChange(kind, value)
  }
}

function clearSeries(){
  months.value = genMonths(18)
  sales.value = fillZeros(18)
  budget.value = fillZeros(18)
  forecast.value = fillZeros(18)
  orders.value = fillZeros(18)
  originalForecast.value = fillZeros(12)
  overlayBest.value = []
  overlayWorst.value = []
  bestLatest.value = 0
  worstLatest.value = 0
  bcRef.value?.hardReset?.()
}

function applyChange(kind, value) {
  if (kind === 'mode') {
    mode.value = value
    primaryId.value = null
    secondaryId.value = null
    clearSeries()
  } else if (kind === 'primary') {
    primaryId.value = value
    secondaryId.value = null
    clearSeries()
  } else if (kind === 'secondary') {
    secondaryId.value = value
    clearSeries()
  }
}

async function saveAndApply(){
  try { if (hasUnsaved.value) await saveBudgetCase() } finally {
    confirmVisible.value = false
    if (pendingChange.value){ applyChange(pendingChange.value.kind, pendingChange.value.value) }
    pendingChange.value = null
    bcRef.value?.hardReset?.()
  }
}

function discardAndApply(){
  bcRef.value?.hardReset?.()
  budgetDirty.value = false
  confirmVisible.value = false
  if (pendingChange.value){ applyChange(pendingChange.value.kind, pendingChange.value.value) }
  pendingChange.value = null
}

function handleNext() {
  const list = secondaryOptions.value
  if (!list?.length) return
  const idx = list.findIndex((o) => o.value === secondaryId.value)
  const n = (idx >= 0 ? idx + 1 : 0) % list.length
  guardedChange('secondary', list[n].value)
}

/* Derived IDs from filters */
const currentClientId = computed(() =>
  mode.value === 'client' ? primaryId.value : secondaryId.value,
)
const currentPcId = computed(() => (mode.value === 'client' ? secondaryId.value : primaryId.value))
const currentCGN = computed(() => {
  const clientId = currentClientId.value
  const c = clientById.value[clientId]
  const fromClient = toNumberSafe(
    c?.client_group_number,
    c?.group_number,
    c?.clientGroupNumber,
    c?.client_group,
  )
  if (Number.isFinite(fromClient)) return fromClient
  const fallback = Number(mode.value === 'client' ? primaryId.value : secondaryId.value)
  return Number.isFinite(fallback) ? fallback : null
})
const currentPCC = computed(() => {
  const p = pcById.value[currentPcId.value]
  const fromPc = toNumberSafe(p?.profit_center_code, p?.code, p?.profitCenterCode)
  if (Number.isFinite(fromPc)) return fromPc
  const fallback = Number(mode.value === 'client' ? secondaryId.value : primaryId.value)
  return Number.isFinite(fallback) ? fallback : null
})
const cgnForChild = computed(() =>
  Number.isFinite(Number(currentCGN.value)) ? Number(currentCGN.value) : null,
)
const pccForChild = computed(() =>
  Number.isFinite(Number(currentPCC.value)) ? Number(currentPCC.value) : null,
)

watch(secondaryId, () => { loadSeries() })
onMounted(() => { loadMaster() })

/* Layout */
const layout = ref([
  { i: 'filters', x: 0, y: 0, w: 2, h: 47, static: true, type: 'filters' },
  { i: 'title', x: 2, y: 0, w: 10, h: 4, static: true, type: 'title' },
  { i: 'chart', x: 2, y: 4, w: 7, h: 26, static: true, type: 'chart' },
  { i: 'cases', x: 9, y: 4, w: 3, h: 26, static: true, type: 'cases' },
  { i: 'table', x: 2, y: 30, w: 10, h: 17, static: true, type: 'table' },
])
function getTitle(item) {
  if (item.type === 'title') return ''
  if (item.type === 'filters') return 'Filter'
  if (item.type === 'chart') return 'Diagramm'
  if (item.type === 'cases') return 'Budget-Fall'
  if (item.type === 'table') return 'Tabelle'
  return ''
}
</script>

<style scoped>
.forecast-wrapper {
  height: 100vh;
  width: 100%;
  overflow: hidden;
}
.no-strip :deep(.card-header),
.no-strip :deep(.glass-title),
.no-strip :deep(.p-card-header) {
  display: none !important;
}
.blocked { position: relative; height: 100%; }
.blocked .overlay { position: absolute; inset: 0; cursor: not-allowed; z-index: 2; }
.grid-placeholder { height: 100%; display: flex; align-items: center; justify-content: center; color: #111827; }
</style>