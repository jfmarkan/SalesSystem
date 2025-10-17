<template>
  <div class="forecast-page">
    <Toast />

    <!-- Confirm dialog -->
    <Dialog
      v-model:visible="confirmVisible"
      :modal="true"
      :draggable="false"
      :dismissableMask="true"
      header="Ungespeicherte Änderungen"
      :style="{ width: '520px' }"
    >
      <p class="mb-3">Es gibt nicht gespeicherte Änderungen. Möchtest du sie speichern?</p>
      <div class="dialog-actions">
        <Button label="Abbrechen" severity="secondary" @click="confirmVisible=false; pendingChange=null" />
        <Button label="Verwerfen" severity="danger" @click="discardAndApply" />
        <Button label="Speichern" icon="pi pi-save" @click="saveAndApply" />
      </div>
    </Dialog>

    <!-- Top bar -->
    <header class="topbar card">
      <div class="title-side">
        <div class="title">
          <span class="eyebrow">Forecast</span>
          <span v-if="hasSelection" class="main-title">
            <ForecastTitle
              :client="selectedClientName"
              :kunde="selectedClientName"
              :pc="selectedPCName"
            />
          </span>
          <span v-else class="main-title muted">Bitte Kunde und Profit Center wählen</span>
        </div>
        <span
          v-if="hasSelection && hasCaseForSelection"
          class="pill-ok"
          title="Budget Case vorhanden"
        >✓ Budget Case</span>
      </div>
      <div class="actions">
        <Button
          label="Speichern"
          icon="pi pi-save"
          :disabled="!budgetDirty"
          @click="saveBudgetCase"
        />
      </div>
    </header>

    <!-- Main content -->
    <div class="content">
      <!-- Filters -->
      <aside class="sidebar card">
        <ForecastFilters
          :mode="mode"
          :primary-options="primaryOptions"
          :primary-id="primaryId"
          :secondary-options="decoratedSecondaryOptions"
          :secondary-id="secondaryId"
          @update:mode="(v)=>guardedChange('mode', normalizeMode(v))"
          @update:primary-id="(v)=>guardedChange('primary', v)"
          @update:secondary-id="(v)=>guardedChange('secondary', v)"
          @next="handleNext"
        />
		<div class="legend">
			<span class="legend-item"><span class="dot dot-done"> </span>Fertig</span>
			<span class="legend-item"><span class="dot dot-open"> </span>Offen</span>
		</div>
        <div class="note" v-if="loading">Lädt…</div>
      </aside>

      <!-- Chart + Case -->
      <main class="main">
        <GlassCard title="Diagramm" class="card pad">
          <div class="panel">
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
            <div v-else class="empty">Keine Auswahl</div>
          </div>
        </GlassCard>

        <GlassCard title="Budget-Fall" class="card pad">
          <div class="panel">
            <BudgetCasePanel
              v-if="hasSelection"
              :key="`${currentClientId}-${currentPcId}`"
              ref="bcRef"
              :client-group-number="cgnForChild"
              :profit-center-code="pccForChild"
              :disabled="false"
              :prefill="prefillFromDb"
              @dirty-change="(v)=> budgetDirty = !!v"
              @values-change="onChildValues"
              @simulated="onSimulated"
            />
            <div v-else class="empty">Keine Auswahl</div>
          </div>
        </GlassCard>
      </main>
    </div>

    <!-- Readonly table -->
    <GlassCard title="Tabelle" class="card pad blocked">
      <div class="panel">
        <ForecastTable
          v-if="hasSelection"
          :months="months"
          :ventas="sales"
          :budget="budget"
          :forecast="forecast"
          @edit-forecast="() => {}"
        />
        <div v-else class="empty">Keine Auswahl</div>
        <div class="overlay" aria-hidden="true" title="Deaktiviert"></div>
      </div>
    </GlassCard>
  </div>
</template>

<script setup>
// All comments in English

import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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
const route = useRoute()
const router = useRouter()

/* ---------------------------------------------------
   Master data & assignment mapping (CPC id per pair)
---------------------------------------------------- */
const clients = ref([])
const profitCenters = ref([])
const mapClientToPC = ref({})
const mapPCToClient = ref({})
const clientById = ref({})
const pcById = ref({})

const cpcByPair = ref({}) // key: "clientId-pcId" -> client_profit_center_id (number)
const pairKey = (cId, pId) => `${Number(cId)}-${Number(pId)}`
function registerCpcPair(cId, pId, cpcIdRaw) {
  const id = Number(cpcIdRaw)
  if (Number.isFinite(id) && id > 0) {
    cpcByPair.value = { ...cpcByPair.value, [pairKey(cId, pId)]: id }
  }
}

async function loadMaster() {
  await ensureCsrf()
  const [resC, resP, resM] = await Promise.all([
    api.get('/api/me/clients'),
    api.get('/api/me/profit-centers'),
    api.get('/api/me/assignments'),
  ])
  clients.value = Array.isArray(resC.data) ? resC.data : []
  profitCenters.value = Array.isArray(resP.data) ? resP.data : []
  mapClientToPC.value = resM.data?.clientToPc || {}
  mapPCToClient.value = resM.data?.pcToClient || {}

  clientById.value = Object.fromEntries(clients.value.map(c => [c.id, c]))
  pcById.value = Object.fromEntries(profitCenters.value.map(p => [p.id, p]))

  const m = resM.data || {}
  if (m.cpcByPair && typeof m.cpcByPair === 'object') {
    const cleaned = {}
    for (const [k, v] of Object.entries(m.cpcByPair)) {
      const n = Number(v); if (Number.isFinite(n) && n > 0) cleaned[k] = n
    }
    cpcByPair.value = cleaned
  } else if (Array.isArray(m.pairs)) {
    for (const row of m.pairs) {
      registerCpcPair(row.clientId, row.profitCenterId, row.cpcId ?? row.client_profit_center_id)
    }
  }
}

/* ---------------------------------------------------
   Filters / selection + URL persistence
---------------------------------------------------- */
const mode = ref('') // 'client' | 'pc'
const primaryId = ref(null)
const secondaryId = ref(null)
const loading = ref(false)
const suspendGuard = ref(false) // avoid save/discard modal during initial restore

function normalizeMode(v) {
  const s = String(v || '').toLowerCase().trim()
  if (['client', 'cliente', 'kunde'].includes(s)) return 'client'
  if (['pc', 'profit', 'profitcenter', 'profit center'].includes(s)) return 'pc'
  return ''
}
const hasSelection = computed(() => !!mode.value && primaryId.value != null && secondaryId.value != null)

const primaryOptions = computed(() => {
  if (mode.value === 'client') return clients.value.map(c => ({ label: c.name, value: c.id }))
  if (mode.value === 'pc')     return profitCenters.value.map(p => ({ label: p.name, value: p.id }))
  return []
})

function syncRouteQuery() {
  const q = { ...route.query }
  if (mode.value) q.mode = mode.value; else delete q.mode
  if (primaryId.value != null) q.primaryId = String(primaryId.value); else delete q.primaryId
  if (secondaryId.value != null) q.secondaryId = String(secondaryId.value); else delete q.secondaryId
  router.replace({ query: q })
}
async function restoreSelectionFromRoute() {
  const m = normalizeMode(route.query.mode)
  const p = route.query.primaryId != null ? Number(route.query.primaryId) : null
  const s = route.query.secondaryId != null ? Number(route.query.secondaryId) : null

  suspendGuard.value = true
  try {
    if (m) mode.value = m
    if (p != null) primaryId.value = p
    if (s != null) secondaryId.value = s
    if (hasSelection.value) {
      await refreshCaseFlagsForSecondary()
      await Promise.all([loadSeries(), loadBudgetCasePrefill()])
    }
  } finally {
    suspendGuard.value = false
  }
}
watch([mode, primaryId, secondaryId], () => syncRouteQuery())

/* ---------------------------------------------------
   Fiscal year rule
   Jan–Mar = same year, Apr–Dec = next year
---------------------------------------------------- */
function budgetYearByToday() {
  const d = new Date(), m = d.getMonth() + 1, y = d.getFullYear()
  return (m >= 4 && m <= 12) ? y + 1 : y
}
const budgetFiscalYear = ref(budgetYearByToday())

/* ---------------------------------------------------
   Current selection helpers
---------------------------------------------------- */
function toNumberSafe(...vals) {
  for (const v of vals) { const n = Number(v); if (Number.isFinite(n)) return n }
  return null
}
const currentClientId = computed(() => mode.value === 'client' ? primaryId.value : secondaryId.value)
const currentPcId     = computed(() => mode.value === 'client' ? secondaryId.value : primaryId.value)

const currentCGN = computed(() => {
  const c = clientById.value[currentClientId.value]
  const v = toNumberSafe(c?.client_group_number, c?.group_number, c?.clientGroupNumber, c?.client_group)
  if (Number.isFinite(v)) return v
  const fb = Number(mode.value === 'client' ? primaryId.value : secondaryId.value)
  return Number.isFinite(fb) ? fb : null
})
const currentPCC = computed(() => {
  const p = pcById.value[currentPcId.value]
  const v = toNumberSafe(p?.profit_center_code, p?.code, p?.profitCenterCode)
  if (Number.isFinite(v)) return v
  const fb = Number(mode.value === 'client' ? secondaryId.value : primaryId.value)
  return Number.isFinite(fb) ? fb : null
})
const cgnForChild = computed(() => Number.isFinite(Number(currentCGN.value)) ? Number(currentCGN.value) : null)
const pccForChild = computed(() => Number.isFinite(Number(currentPCC.value)) ? Number(currentPCC.value) : null)

/* ---------------------------------------------------
   CPC id resolution and ready flags (✓)
---------------------------------------------------- */
function cpcIdFor(clientId, pcId) {
  const id = cpcByPair.value[pairKey(clientId, pcId)]
  const n = Number(id)
  return Number.isFinite(n) && n > 0 ? n : null
}

const hasCaseCpcSet = ref(new Set()) // Set<number>, reactive via reassignment
function addReadyCpc(id) {
  const n = Number(id)
  if (!Number.isFinite(n) || n <= 0) return
  const next = new Set(hasCaseCpcSet.value)
  next.add(n)
  hasCaseCpcSet.value = next
}

/* Batch exists check (prefer fast endpoint; fallback to per-id GET) */
async function refreshCaseFlagsForSecondary() {
  hasCaseCpcSet.value = new Set()
  if (!mode.value || primaryId.value == null) return

  // Build list of CPC ids for visible secondaries
  let cpcIds = []
  if (mode.value === 'client') {
    const pcIds = mapClientToPC.value[primaryId.value] || []
    cpcIds = pcIds.map(pid => cpcIdFor(primaryId.value, pid)).filter(id => Number.isFinite(id) && id > 0)
  } else {
    const clIds = mapPCToClient.value[primaryId.value] || []
    cpcIds = clIds.map(cid => cpcIdFor(cid, primaryId.value)).filter(id => Number.isFinite(id) && id > 0)
  }
  if (!cpcIds.length) return

  try {
    await ensureCsrf()
    const params = { fiscal_year: budgetFiscalYear.value, cpc_ids: cpcIds.join(',') }
    const { data } = await api.get('/api/budget-cases/exists', { params })
    const exists = Array.isArray(data?.exists) ? data.exists.map(Number).filter(Number.isFinite) : []
    hasCaseCpcSet.value = new Set(exists)
  } catch {
    const found = new Set()
    await Promise.all(
      cpcIds.map(id =>
        api.get('/api/budget-cases', { params: { client_profit_center_id: id, fiscal_year: budgetFiscalYear.value } })
          .then(({ data }) => { if (data?.data) found.add(id) })
          .catch(() => {})
      )
    )
    hasCaseCpcSet.value = found
  }
}

/* Prefill form by CPC id; fallback to CGN/PCC if CPC map is missing */
const prefillFromDb = ref({ best_case: null, worst_case: null })
const savedBest = ref(0)
const savedWorst = ref(0)
async function loadBudgetCasePrefill() {
  prefillFromDb.value = { best_case: null, worst_case: null }
  savedBest.value = 0
  savedWorst.value = 0
  if (!hasSelection.value) return

  const cpcId = cpcIdFor(currentClientId.value, currentPcId.value)
  try {
    await ensureCsrf()
    if (Number.isFinite(cpcId) && cpcId > 0) {
      const { data } = await api.get('/api/budget-cases', {
        params: { client_profit_center_id: cpcId, fiscal_year: budgetFiscalYear.value }
      })
      if (data?.data) {
        const b = Number(data.data.best_case) || 0
        const w = Number(data.data.worst_case) || 0
        prefillFromDb.value = { best_case: b, worst_case: w }
        savedBest.value = b
        savedWorst.value = w
        addReadyCpc(cpcId)
      } else {
        // no record -> keep saved as 0,0
        prefillFromDb.value = { best_case: null, worst_case: null }
      }
    } else {
      // Fallback by CGN/PCC
      const cgn = Number(cgnForChild.value), pcc = Number(pccForChild.value)
      if (Number.isFinite(cgn) && Number.isFinite(pcc)) {
        const { data } = await api.get('/api/budget-cases', {
          params: { client_group_number: cgn, profit_center_code: pcc, fiscal_year: budgetFiscalYear.value }
        })
        if (data?.data) {
          const b = Number(data.data.best_case) || 0
          const w = Number(data.data.worst_case) || 0
          prefillFromDb.value = { best_case: b, worst_case: w }
          savedBest.value = b
          savedWorst.value = w
        }
      }
    }
  } catch { /* silent */ }
  // ensure not dirty after prefill init
  budgetDirty.value = false
  await nextTick()
  budgetDirty.value = false
}

/* Current selection ✓ badge */
const hasCaseForSelection = computed(() => {
  if (!hasSelection.value) return false
  const cpcId = cpcIdFor(currentClientId.value, currentPcId.value)
  return Number.isFinite(cpcId) && hasCaseCpcSet.value.has(cpcId)
})

/* ---------------------------------------------------
   Secondary options + visual decoration (🟢 / ⚪)
---------------------------------------------------- */
const secondaryOptions = computed(() => {
  if (!mode.value || primaryId.value == null) return []
  if (mode.value === 'client') {
    const ids = mapClientToPC.value[primaryId.value] || []
    return ids.map(id => {
      const p = pcById.value[id]; if (!p) return null
      return { label: `${p.code} — ${p.name}`, value: p.id }
    }).filter(Boolean)
  } else {
    const ids = mapPCToClient.value[primaryId.value] || []
    return ids.map(id => {
      const c = clientById.value[id]; if (!c) return null
      return { label: c.name, value: c.id }
    }).filter(Boolean)
  }
})

const decoratedSecondaryOptions = computed(() => {
  const list = secondaryOptions.value
  if (!list.length || !mode.value) return list
  return list.map(opt => {
    const clientId = mode.value === 'client' ? primaryId.value : opt.value
    const pcId     = mode.value === 'client' ? opt.value     : primaryId.value
    const cpcId    = cpcIdFor(clientId, pcId)
    const ready    = Number.isFinite(cpcId) && hasCaseCpcSet.value.has(cpcId)
    return { ...opt, label: (ready ? '🟢 ' : '⚪ ') + opt.label, ready }
  })
})

/* ---------------------------------------------------
   Series (chart/table)
---------------------------------------------------- */
function genMonths(n) {
  const out = [], base = new Date(); base.setDate(1)
  for (let i = 0; i < n; i++) {
    const d = new Date(base.getFullYear(), base.getMonth() + i, 1)
    out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
  }
  return out
}
function fillZeros(n) { return Array(n).fill(0) }

const months = ref(genMonths(18))
const sales = ref(fillZeros(18))
const budget = ref(fillZeros(18))
const forecast = ref(fillZeros(18))
const orders = ref(fillZeros(18))
const originalForecast = ref(fillZeros(12))

async function loadSeries() {
  if (!hasSelection.value) return
  loading.value = true
  try {
    await ensureCsrf()
    const clientId = mode.value === 'client' ? primaryId.value : secondaryId.value
    const profitCenterId = mode.value === 'client' ? secondaryId.value : primaryId.value
    const { data } = await api.get('/api/forecast/series', { params: { clientId, profitCenterId } })
    months.value   = Array.isArray(data.months)   && data.months.length   ? data.months   : genMonths(18)
    sales.value    = Array.isArray(data.sales)    && data.sales.length    ? data.sales    : fillZeros(18)
    budget.value   = Array.isArray(data.budget)   && data.budget.length   ? data.budget   : fillZeros(18)
    forecast.value = Array.isArray(data.forecast) && data.forecast.length ? data.forecast : fillZeros(18)
    orders.value   = Array.isArray(data.orders)   && data.orders.length   ? data.orders   : fillZeros(18)
    originalForecast.value = Array.isArray(data.forecast) ? data.forecast.slice(0, 12) : fillZeros(12)
  } finally {
    loading.value = false
  }
}

/* ---------------------------------------------------
   Chart data + overlays
---------------------------------------------------- */
function cumulateToLen(arr, len) {
  const out = []; let s = 0
  for (let i = 0; i < len; i++) { s += Number(arr?.[i] ?? 0); out.push(s) }
  return out
}
const overlayBest = ref([])   // cumulative best
const overlayWorst = ref([])  // cumulative worst

function toCum(arr) { const out = []; let s = 0; for (let i = 0; i < arr.length; i++) { s += Number(arr[i] || 0); out.push(s) } return out }
function onSimulated(payload) {
  const t = Array.isArray(payload?.seriesTarget) ? payload.seriesTarget : []
  const bestC = toCum(t.map(x => Number(x?.best || 0)))
  const worstC = toCum(t.map(x => Number(x?.worst || 0)))
  const len = months.value?.length || 0
  const dest = Math.min(12, len), start = Math.max(0, len - dest)
  const B = Array(len).fill(0), W = Array(len).fill(0)
  for (let i = 0; i < dest; i++) { B[start + i] = bestC[i] ?? 0; W[start + i] = worstC[i] ?? 0 }
  overlayBest.value = B; overlayWorst.value = W
}
const liveCumData = computed(() => {
  if (!hasSelection.value) return null
  const len = months.value?.length || 0
  const salesCum    = cumulateToLen(sales.value, len)
  const budgetCum   = cumulateToLen(budget.value, len)
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
const cumDataForChart = computed(() => {
  const base = liveCumData.value; if (!base) return null
  const out = { ...base }
  if (overlayBest.value.length === base.months.length) out.overlay_best = overlayBest.value
  if (overlayWorst.value.length === base.months.length) out.overlay_worst = overlayWorst.value
  return out
})

/* ---------------------------------------------------
   Save budget case (CPC id preferred, CGN/PCC fallback)
   + robust dirty detection (no false modals)
---------------------------------------------------- */
const bcRef = ref(null)
const budgetDirty = ref(false)
const confirmVisible = ref(false)
const pendingChange = ref(null)
const hasUnsaved = computed(() => !!budgetDirty.value)

const bestLatest = ref(0)
const worstLatest = ref(0)
const round4 = (n) => Math.round((Number(n) || 0) * 10000) / 10000

function onChildValues({ best_case, worst_case }) {
  const b = Number(best_case) || 0
  const w = Number(worst_case) || 0
  bestLatest.value = b
  worstLatest.value = w
  // Dirty only if values differ from last saved snapshot
  budgetDirty.value = (round4(b) !== round4(savedBest.value)) || (round4(w) !== round4(savedWorst.value))
}
function sanitize(v, fb = 0) { const n = Number(v); return Number.isFinite(n) ? n : (Number(fb) || 0) }

async function saveBudgetCase() {
  if (!bcRef.value) return

  const clientId = currentClientId.value
  const pcId     = currentPcId.value
  const cpcId    = cpcIdFor(clientId, pcId)
  const cgn      = Number(cgnForChild.value)
  const pcc      = Number(pccForChild.value)

  const fromChild = bcRef.value.getValues?.()
  const best  = sanitize(fromChild?.best_case, bestLatest.value)
  const worst = sanitize(fromChild?.worst_case, worstLatest.value)

  try {
    await ensureCsrf()
    const payload = {
      fiscal_year: budgetFiscalYear.value,
      best_case: best,
      worst_case: worst,
    }

    if (Number.isFinite(cpcId) && cpcId > 0) {
      payload.client_profit_center_id = cpcId
    } else {
      if (!Number.isFinite(cgn) || !Number.isFinite(pcc)) {
        toast.add({ severity: 'warn', summary: 'Hinweis', detail: 'Zuordnung (CGN/PCC) fehlt', life: 2500 })
        return
      }
      payload.client_group_number = cgn
      payload.profit_center_code  = pcc
    }

    const { data } = await api.post('/api/budget-cases', payload, { withCredentials: true })

    const savedCpcId = Number(data?.data?.client_profit_center_id)
    if (Number.isFinite(savedCpcId) && savedCpcId > 0) {
      registerCpcPair(clientId, pcId, savedCpcId)
      addReadyCpc(savedCpcId)
    }

    // Update snapshot to new saved values -> no dirty
    savedBest.value  = best
    savedWorst.value = worst
    budgetDirty.value = false
    bcRef.value?.markSaved?.()
    toast.add({ severity: 'success', summary: 'Gespeichert', detail: 'Budget Case gespeichert', life: 2200 })
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Speichern fehlgeschlagen'
    toast.add({ severity: 'error', summary: 'Fehler', detail: msg, life: 3000 })
    throw e
  }
}

/* ---------------------------------------------------
   Guarded changes & navigation (no false prompts)
---------------------------------------------------- */
function clearAll() {
  months.value = genMonths(18)
  sales.value = fillZeros(18)
  budget.value = fillZeros(18)
  forecast.value = fillZeros(18)
  orders.value = fillZeros(18)
  originalForecast.value = fillZeros(12)
  overlayBest.value = []; overlayWorst.value = []
  bestLatest.value = 0; worstLatest.value = 0
  savedBest.value = 0; savedWorst.value = 0
  prefillFromDb.value = { best_case: null, worst_case: null }
  budgetDirty.value = false
  bcRef.value?.hardReset?.()
}

async function applyChange(kind, value) {
  if (kind === 'mode') {
    mode.value = value; primaryId.value = null; secondaryId.value = null; clearAll()
  } else if (kind === 'primary') {
    primaryId.value = value; secondaryId.value = null; clearAll(); await refreshCaseFlagsForSecondary()
  } else if (kind === 'secondary') {
    secondaryId.value = value; clearAll()
  }
  await Promise.all([loadSeries(), loadBudgetCasePrefill()])
}

function guardedChange(kind, value) {
  if (suspendGuard.value) { applyChange(kind, value); return }
  if (hasUnsaved.value) {
    pendingChange.value = { kind, value }
    confirmVisible.value = true
  } else {
    applyChange(kind, value)
  }
}
async function saveAndApply() {
  try { if (hasUnsaved.value) await saveBudgetCase() }
  finally {
    confirmVisible.value = false
    if (pendingChange.value) await applyChange(pendingChange.value.kind, pendingChange.value.value)
    pendingChange.value = null
    bcRef.value?.hardReset?.()
  }
}
async function discardAndApply() {
  bcRef.value?.hardReset?.()
  budgetDirty.value = false
  confirmVisible.value = false
  if (pendingChange.value) await applyChange(pendingChange.value.kind, pendingChange.value.value)
  pendingChange.value = null
}
function handleNext() {
  const list = secondaryOptions.value; if (!list?.length) return
  const idx = list.findIndex(o => o.value === secondaryId.value)
  const n = (idx >= 0 ? idx + 1 : 0) % list.length
  guardedChange('secondary', list[n].value)
}

/* ---------------------------------------------------
   Watches / lifecycle
---------------------------------------------------- */
watch([mode, primaryId], () => { if (primaryId.value != null) refreshCaseFlagsForSecondary() })
watch(secondaryId, () => { loadSeries(); loadBudgetCasePrefill() })

onMounted(async () => {
  budgetFiscalYear.value = budgetYearByToday()
  await loadMaster()
  await restoreSelectionFromRoute() // restore selection from URL so content doesn't vanish when returning
})

/* ---------------------------------------------------
   Header labels
---------------------------------------------------- */
const selectedClientName = computed(() => {
  if (mode.value === 'client') return clientById.value[primaryId.value]?.name || ''
  return clientById.value[secondaryId.value]?.name || ''
})
const selectedPCName = computed(() => {
  const pcId = mode.value === 'client' ? secondaryId.value : primaryId.value
  const p = pcById.value[pcId]
  return p ? `${p.name}` : ''
})
</script>


<style scoped>
/* Design tokens (light/dark) */
:root {
  --bg: #f6f7f9;
  --surface: #ffffff;
  --text: #111827;
  --muted: #6b7280;
  --border: #e5e7eb;
  --ok-bg: #e8f7ee;
  --ok-text: #0e7a3e;
  --ok-border: #b6e2c8;
  --shadow: 0 8px 24px rgba(0,0,0,0.06);
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg: #0b1020;
    --surface: #12182a;
    --text: #e5e7eb;
    --muted: #9aa3b2;
    --border: #1f2937;
    --ok-bg: #0e2a1b;
    --ok-text: #8ce0ae;
    --ok-border: #1f5a3b;
    --shadow: 0 10px 28px rgba(0,0,0,0.35);
  }
}

/* Layout */
.forecast-page { display:flex; flex-direction:column; gap:16px; background:var(--bg); color:var(--text); min-height:100vh; padding:16px; }
.card { background:var(--surface); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); }
.pad { padding:12px; }

.topbar { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; }
.title-side { display:flex; align-items:center; gap:12px; }
.eyebrow { font-size:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }
.main-title { font-weight:600; font-size:16px; }
.muted { color: var(--muted); }
.actions { display:flex; gap:8px; }

.content { display:grid; grid-template-columns: 320px 1fr; gap:16px; }
@media (max-width: 1100px) { .content { grid-template-columns: 1fr; } }

.sidebar { padding:14px; }
.main { display:grid; grid-template-columns: 1fr 420px; gap:16px; }
@media (max-width: 1400px) { .main { grid-template-columns: 1fr; } }

.panel { min-height: 240px; }
.empty { color: var(--muted); font-size: 14px; padding: 8px; }

/* Table readonly overlay */
.blocked { position: relative; }
.blocked .overlay { position:absolute; inset:0; cursor:not-allowed; z-index:2; border-radius:12px; }

/* Dialog actions */
.dialog-actions { display:flex; justify-content:flex-end; gap:8px; }

/* ✓ pill */
.pill-ok {
  background: var(--ok-bg);
  color: var(--ok-text);
  border: 1px solid var(--ok-border);
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 0.85rem;
  line-height: 1.4;
}

/* Notes */
.note { margin-top: 8px; color: var(--muted); font-size: 13px; }

.legend { margin-top: 8px; display: flex; gap: 16px; align-items: center; font-size: 13px; }
.legend-item { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); }
.dot { width: 10px; height: 10px; display: inline-block; border-radius: 50%; border: 1px solid transparent; }
.dot-done { background: #16a34a; border-color: rgba(0,0,0,0.05); } /* green */
.dot-open { background: #d1d5db; border-color: rgba(0,0,0,0.05); } /* gray */
@media (prefers-color-scheme: dark) {
  .dot-open { background: #374151; border-color: rgba(255,255,255,0.08); }
}
</style>
