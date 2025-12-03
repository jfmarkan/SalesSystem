<template>
	<Toast />
	<div class="budget-case-grid">
		<Dialog
			v-model:visible="confirmVisible"
			modal
			dismissable-mask
			header="Ungespeicherte Änderungen"
			:style="{ width: '520px' }"
		>
			<p class="mb-3">
				Es gibt nicht gespeicherte Änderungen. Möchtest du sie speichern?
			</p>
			<div class="flex justify-content-end gap-2">
				<Button
					label="Abbrechen"
					severity="secondary"
					@click="
						() => {
							confirmVisible = false
							pendingChange.value = null
						}
					"
				/>
				<Button
					label="Verwerfen"
					severity="danger"
					icon="pi pi-trash"
					@click="discardAndApply"
				/>
				<Button
					label="Speichern"
					severity="success"
					icon="pi pi-save"
					@click="saveAndApply"
				/>
			</div>
		</Dialog>

		<!-- Sidebar / Filters -->
		<aside class="filters-col">
			<Card class="filters-card">
				<template #content>
					<div class="filters-inner">
						<div class="field-block flex-1 min-h-0">
							<div class="selector-host">
								<ForecastFilters
									class="ff-host"
									:mode="mode"
									:primary-options="primaryOptions"
									:primary-id="primaryId"
									:secondary-options="secondaryOptionsWithDots"
									:secondary-id="secondaryId"
									@update:mode="(v) => guardedChange('mode', normalizeMode(v))"
									@update:primary-id="(v) => guardedChange('primary', v)"
									@update:secondary-id="(v) => guardedChange('secondary', v)"
									@next="handleNext"
								/>
								<div class="mt-2 text-muted text-sm" v-if="loading">Lädt…</div>
							</div>
						</div>
						<div class="filters-footer">
							<div class="legend">
								<span class="legend-item">
									<i class="pi pi-check-circle legend-icon legend-icon-done"></i>
									Vorhanden
								</span>
								<span class="legend-item">
									<i class="pi pi-circle legend-icon legend-icon-pending"></i>
									Fehlt
								</span>
							</div>
						</div>
					</div>
				</template>
			</Card>
		</aside>

		<!-- Main content -->
		<main class="content-col">
			<!-- Header -->
			<Card class="topbar-card">
				<template #content>
					<div class="topbar-inner">
						<div class="title-left">
							<div class="eyebrow">Budget Case</div>
							<div class="title-line">
								<strong class="kunde">{{ selectedClientName || 'Kunde' }}</strong>
								<span class="sep" aria-hidden="true"> | </span>
								<span class="pc">{{ selectedPCName || '(PC)' }}</span>
							</div>
						</div>
						<div class="actions">
							<Button
								label="Speichern"
								icon="pi pi-save"
								:disabled="!budgetDirty"
								:outlined="!budgetDirty"
								@click="saveBudgetCase"
							/>
						</div>
					</div>
				</template>
			</Card>

			<!-- Charts -->
			<div class="charts-row">
				<Card class="chart-card chart-lg">
					<template #content>
						<div class="chart-pad">
							<div class="chart-body">
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
								<div v-else class="card-placeholder">Keine Auswahl</div>
							</div>
						</div>
					</template>
				</Card>

				<Card class="chart-card chart-sm">
					<template #content>
						<div class="chart-pad">
							<div class="chart-body">
								<BudgetCasePanel
									v-if="hasSelection"
									:key="`${currentClientId}-${currentPcId}`"
									ref="bcRef"
									:client-group-number="cgnForChild"
									:profit-center-code="pccForChild"
									@dirty-change="(v) => (budgetDirty = !!v)"
									@values-change="onChildValues"
									@simulated="onSimulated"
								/>
								<div v-else class="card-placeholder">Keine Auswahl</div>
							</div>
						</div>
					</template>
				</Card>
			</div>

			<!-- Table -->
			<Card class="table-card">
				<template #content>
					<div class="table-pad">
						<template v-if="hasSelection">
							<ForecastTable
								ref="tableRef"
								:months="months"
								:ventas="sales"
								:budget="budget"
								:forecast="forecast"
								:viewport-start="0"
								:viewport-size="12"
								:is-editable-ym="() => false"
								@edit-forecast="() => {}"
							/>
						</template>
						<div v-else class="card-placeholder">Keine Auswahl</div>
					</div>
				</template>
			</Card>
		</main>
	</div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Toast from 'primevue/toast'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

import ForecastFilters from '@/components/filters/ComponentFilter.vue'
import ForecastTable from '@/components/tables/ComponentTable.vue'
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

	clientById.value = Object.fromEntries(clients.value.map((c) => [c.id, c]))
	pcById.value = Object.fromEntries(profitCenters.value.map((p) => [p.id, p]))

	// ✅ load CPC ids
	const cpcIds = resM.data?.cpcIds || []
	const cpcMap = {}
	for (const item of cpcIds) {
		const key = `${item.client_id}-${item.profit_center_id}`
		cpcMap[key] = item.client_profit_center_id
	}
	cpcByPair.value = cpcMap
}

/* ---------------------------------------------------
   Filters / selection + URL persistence
---------------------------------------------------- */
const mode = ref('') // 'client' | 'pc'
const primaryId = ref(null)
const secondaryId = ref(null)
const loading = ref(false)
const suspendGuard = ref(false)

function normalizeMode(v) {
	const s = String(v || '')
		.toLowerCase()
		.trim()
	if (['client', 'cliente', 'kunde'].includes(s)) return 'client'
	if (['pc', 'profit', 'profitcenter', 'profit center'].includes(s)) return 'pc'
	return ''
}
const hasSelection = computed(
	() => !!mode.value && primaryId.value != null && secondaryId.value != null,
)

const primaryOptions = computed(() => {
	if (mode.value === 'client') return clients.value.map((c) => ({ label: c.name, value: c.id }))
	if (mode.value === 'pc') return profitCenters.value.map((p) => ({ label: p.name, value: p.id }))
	return []
})

function syncRouteQuery() {
	const q = { ...route.query }
	if (mode.value) q.mode = mode.value
	else delete q.mode
	if (primaryId.value != null) q.primaryId = String(primaryId.value)
	else delete q.primaryId
	if (secondaryId.value != null) q.secondaryId = String(secondaryId.value)
	else delete q.secondaryId
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
			await loadSeries()
		}
	} finally {
		suspendGuard.value = false
	}
}
watch([mode, primaryId, secondaryId], () => syncRouteQuery())

/* ---------------------------------------------------
   Fiscal year rule
---------------------------------------------------- */
function budgetYearByToday() {
	const d = new Date(),
		m = d.getMonth() + 1,
		y = d.getFullYear()
	return m >= 4 && m <= 12 ? y + 1 : y
}
const budgetFiscalYear = ref(budgetYearByToday())

/* ---------------------------------------------------
   Current selection helpers
---------------------------------------------------- */
function toNumberSafe(...vals) {
	for (const v of vals) {
		const n = Number(v)
		if (Number.isFinite(n)) return n
	}
	return null
}
const currentClientId = computed(() =>
	mode.value === 'client' ? primaryId.value : secondaryId.value,
)
const currentPcId = computed(() => (mode.value === 'client' ? secondaryId.value : primaryId.value))

const currentCGN = computed(() => {
	const c = clientById.value[currentClientId.value]
	const v = toNumberSafe(
		c?.client_group_number,
		c?.group_number,
		c?.clientGroupNumber,
		c?.client_group,
	)
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
const cgnForChild = computed(() =>
	Number.isFinite(Number(currentCGN.value)) ? Number(currentCGN.value) : null,
)
const pccForChild = computed(() =>
	Number.isFinite(Number(currentPCC.value)) ? Number(currentPCC.value) : null,
)

/* ---------------------------------------------------
   CPC id resolution and ready flags (✓)
---------------------------------------------------- */
function cpcIdFor(clientId, pcId) {
	const id = cpcByPair.value[pairKey(clientId, pcId)]
	const n = Number(id)
	return Number.isFinite(n) && n > 0 ? n : null
}

const hasCaseCpcSet = ref(new Set())
function addReadyCpc(id) {
	const n = Number(id)
	if (!Number.isFinite(n) || n <= 0) return
	const next = new Set(hasCaseCpcSet.value)
	next.add(n)
	hasCaseCpcSet.value = next
}

async function refreshCaseFlagsForSecondary() {
	hasCaseCpcSet.value = new Set()
	if (!mode.value || primaryId.value == null) return

	const cpcIds = []

	if (mode.value === 'client') {
		const pcIds = mapClientToPC.value[primaryId.value] || []
		for (const pcId of pcIds) {
			const cpcId = cpcIdFor(primaryId.value, pcId)
			if (Number.isFinite(cpcId) && cpcId > 0) cpcIds.push(cpcId)
		}
	} else {
		const clientIds = mapPCToClient.value[primaryId.value] || []
		for (const clientId of clientIds) {
			const cpcId = cpcIdFor(clientId, primaryId.value)
			if (Number.isFinite(cpcId) && cpcId > 0) cpcIds.push(cpcId)
		}
	}

	if (!cpcIds.length) return

	try {
		await ensureCsrf()
		const params = { fiscal_year: budgetFiscalYear.value, cpc_ids: cpcIds.join(',') }
		const { data } = await api.get('/api/budget-cases/exists', { params })
		if (data?.exists && Array.isArray(data.exists)) {
			const exists = data.exists.map(Number).filter(Number.isFinite)
			hasCaseCpcSet.value = new Set(exists)
		}
	} catch {
		const found = new Set()
		for (const cpcId of cpcIds) {
			try {
				const { data } = await api.get('/api/budget-cases', {
					params: { client_profit_center_id: cpcId, fiscal_year: budgetFiscalYear.value },
				})
				if (data?.data) found.add(cpcId)
			} catch (e) {
				if (e?.response?.status !== 404) {
					// ignore
				}
			}
		}
		hasCaseCpcSet.value = found
	}
}

/* ---------------------------------------------------
   Secondary options + icons
---------------------------------------------------- */
const secondaryOptions = computed(() => {
	if (!mode.value || primaryId.value == null) return []
	if (mode.value === 'client') {
		const ids = mapClientToPC.value[primaryId.value] || []
		return ids
			.map((id) => {
				const p = pcById.value[id]
				if (!p) return null
				return { label: `${p.code} — ${p.name}`, value: p.id }
			})
			.filter(Boolean)
	} else {
		const ids = mapPCToClient.value[primaryId.value] || []
		return ids
			.map((id) => {
				const c = clientById.value[id]
				if (!c) return null
				return { label: c.name, value: c.id }
			})
			.filter(Boolean)
	}
})

const decoratedSecondaryOptions = computed(() => {
	const list = secondaryOptions.value
	if (!list.length || !mode.value) return list

	return list.map((opt) => {
		const clientId = mode.value === 'client' ? primaryId.value : opt.value
		const pcId = mode.value === 'client' ? opt.value : primaryId.value
		const cpcId = cpcIdFor(clientId, pcId)
		const ready = Number.isFinite(cpcId) && hasCaseCpcSet.value.has(cpcId)
		return { ...opt, hasCase: ready }
	})
})

const secondaryOptionsWithDots = computed(() => {
	return decoratedSecondaryOptions.value.map((opt) => {
		const iconClass = opt.hasCase ? 'pi pi-check-circle' : 'pi pi-circle'
		return { ...opt, label: `<i class="${iconClass}"></i>${opt.label}` }
	})
})

/* ---------------------------------------------------
   Series (chart/table)
---------------------------------------------------- */
function genMonths(n) {
	const out = [],
		base = new Date()
	base.setDate(1)
	for (let i = 0; i < n; i++) {
		const d = new Date(base.getFullYear(), base.getMonth() + i, 1)
		out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
	}
	return out
}
function fillZeros(n) {
	return Array(n).fill(0)
}

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
		const { data } = await api.get('/api/forecast/series', {
			params: { clientId, profitCenterId },
		})
		months.value =
			Array.isArray(data.months) && data.months.length ? data.months : genMonths(18)
		sales.value = Array.isArray(data.sales) && data.sales.length ? data.sales : fillZeros(18)
		budget.value =
			Array.isArray(data.budget) && data.budget.length ? data.budget : fillZeros(18)
		forecast.value =
			Array.isArray(data.forecast) && data.forecast.length ? data.forecast : fillZeros(18)
		orders.value =
			Array.isArray(data.orders) && data.orders.length ? data.orders : fillZeros(18)
		originalForecast.value = Array.isArray(data.forecast)
			? data.forecast.slice(0, 12)
			: fillZeros(12)
	} finally {
		loading.value = false
	}
}

/* ---------------------------------------------------
   Chart overlays
---------------------------------------------------- */
function cumulateToLen(arr, len) {
	const out = []
	let s = 0
	for (let i = 0; i < len; i++) {
		s += Number(arr?.[i] ?? 0)
		out.push(s)
	}
	return out
}
const overlayBest = ref([])
const overlayWorst = ref([])

function toCum(arr) {
	const out = []
	let s = 0
	for (let i = 0; i < arr.length; i++) {
		s += Number(arr[i] || 0)
		out.push(s)
	}
	return out
}
function onSimulated(payload) {
	const t = Array.isArray(payload?.seriesTarget) ? payload.seriesTarget : []
	const bestC = toCum(t.map((x) => Number(x?.best || 0)))
	const worstC = toCum(t.map((x) => Number(x?.worst || 0)))
	const len = months.value?.length || 0
	const dest = Math.min(12, len),
		start = Math.max(0, len - dest)
	const B = Array(len).fill(0),
		W = Array(len).fill(0)
	for (let i = 0; i < dest; i++) {
		B[start + i] = bestC[i] ?? 0
		W[start + i] = worstC[i] ?? 0
	}
	overlayBest.value = B
	overlayWorst.value = W
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
const cumDataForChart = computed(() => {
	const base = liveCumData.value
	if (!base) return null
	const out = { ...base }
	if (overlayBest.value.length === base.months.length) out.overlay_best = overlayBest.value
	if (overlayWorst.value.length === base.months.length) out.overlay_worst = overlayWorst.value
	return out
})

/* ---------------------------------------------------
   Save budget case
---------------------------------------------------- */
const bcRef = ref(null)
const budgetDirty = ref(false)
const confirmVisible = ref(false)
const pendingChange = ref(null)
const hasUnsaved = computed(() => !!budgetDirty.value)

// último snapshot que manda el hijo
const lastChildValues = ref({
	best_case: 0,
	worst_case: 0,
	skip_budget: false,
})

function onChildValues({ best_case, worst_case, skip_budget }) {
	lastChildValues.value = {
		best_case: Number(best_case ?? 0),
		worst_case: Number(worst_case ?? 0),
		skip_budget: !!skip_budget,
	}
	// OJO: dirty lo maneja solo el hijo vía @dirty-change
}

function sanitize(v, fb = 0) {
	const n = Number(v)
	return Number.isFinite(n) ? n : Number(fb) || 0
}

async function saveBudgetCase() {
	if (!bcRef.value) return

	const clientId = currentClientId.value
	const pcId = currentPcId.value
	const cpcId = cpcIdFor(clientId, pcId)
	const cgn = Number(cgnForChild.value)
	const pcc = Number(pccForChild.value)

	// Intentamos leer directamente del hijo; si no, usamos el último snapshot
	const fromChild = bcRef.value.getValues?.() || lastChildValues.value

	const best = sanitize(fromChild.best_case, lastChildValues.value.best_case)
	const worst = sanitize(fromChild.worst_case, lastChildValues.value.worst_case)
	const skip = !!fromChild.skip_budget

	try {
		await ensureCsrf()
		const payload = {
			fiscal_year: budgetFiscalYear.value,
			best_case: best,
			worst_case: worst,
			skip_budget: skip,
		}

		if (Number.isFinite(cpcId) && cpcId > 0) {
			payload.client_profit_center_id = cpcId
		} else {
			if (!Number.isFinite(cgn) || !Number.isFinite(pcc)) {
				toast.add({
					severity: 'warn',
					summary: 'Hinweis',
					detail: 'Zuordnung (CGN/PCC) fehlt',
					life: 2500,
				})
				return
			}
			payload.client_group_number = cgn
			payload.profit_center_code = pcc
		}

		const { data } = await api.post('/api/budget-cases', payload, {
			withCredentials: true,
		})

		const savedCpcId = Number(data?.data?.client_profit_center_id)
		if (Number.isFinite(savedCpcId) && savedCpcId > 0) {
			registerCpcPair(clientId, pcId, savedCpcId)
			addReadyCpc(savedCpcId)
		}

		// avisamos al hijo que se guardó, y limpiamos "dirty"
		bcRef.value?.markSaved?.()
		budgetDirty.value = false

		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Budget Case gespeichert',
			life: 2200,
		})

		await refreshCaseFlagsForSecondary()
	} catch (e) {
		const msg =
			e?.response?.data?.message || e?.message || 'Speichern fehlgeschlagen'
		toast.add({ severity: 'error', summary: 'Fehler', detail: msg, life: 3000 })
		throw e
	}
}

/* ---------------------------------------------------
   Guarded changes & navigation
---------------------------------------------------- */
function clearAll() {
	months.value = genMonths(18)
	sales.value = fillZeros(18)
	budget.value = fillZeros(18)
	forecast.value = fillZeros(18)
	orders.value = fillZeros(18)
	originalForecast.value = fillZeros(12)
	overlayBest.value = []
	overlayWorst.value = []
	lastChildValues.value = {
		best_case: 0,
		worst_case: 0,
		skip_budget: false,
	}
	budgetDirty.value = false
	bcRef.value?.hardReset?.()
}

async function applyChange(kind, value) {
	if (kind === 'mode') {
		mode.value = value
		primaryId.value = null
		secondaryId.value = null
		clearAll()
	} else if (kind === 'primary') {
		primaryId.value = value
		secondaryId.value = null
		clearAll()
		await refreshCaseFlagsForSecondary()
	} else if (kind === 'secondary') {
		secondaryId.value = value
		clearAll()
	}
	await loadSeries()
}

function guardedChange(kind, value) {
	if (suspendGuard.value) {
		applyChange(kind, value)
		return
	}
	if (hasUnsaved.value) {
		pendingChange.value = { kind, value }
		confirmVisible.value = true
	} else {
		applyChange(kind, value)
	}
}
async function saveAndApply() {
	try {
		if (hasUnsaved.value) await saveBudgetCase()
	} finally {
		confirmVisible.value = false
		if (pendingChange.value)
			await applyChange(pendingChange.value.kind, pendingChange.value.value)
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
	const list = secondaryOptions.value
	if (!list?.length) return
	const idx = list.findIndex((o) => o.value === secondaryId.value)
	const n = (idx >= 0 ? idx + 1 : 0) % list.length
	guardedChange('secondary', list[n].value)
}

/* ---------------------------------------------------
   Watches / lifecycle
---------------------------------------------------- */
watch([mode, primaryId], () => {
	if (primaryId.value != null) refreshCaseFlagsForSecondary()
})
watch(secondaryId, () => {
	loadSeries()
})

onMounted(async () => {
	budgetFiscalYear.value = budgetYearByToday()
	await loadMaster()
	await restoreSelectionFromRoute()
	if (primaryId.value) await refreshCaseFlagsForSecondary()
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
.budget-case-grid {
	--gap: 16px;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	padding: var(--pad-y) var(--pad-x, 16px);
	box-sizing: border-box;
	height: 100%;
	overflow: hidden;
}

/* Sidebar */
.filters-col {
	grid-column: span 2;
	min-width: 0;
	display: flex;
	height: 100%;
	overflow: auto;
}

.filters-card {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.filters-inner {
	flex: 1;
	min-height: 0;
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

.filters-footer {
	margin-top: auto;
}

.legend {
	font-size: 13px;
	display: flex;
	justify-content: space-evenly;
	gap: 6px;
}

.legend-item {
	display: flex;
	align-items: center;
	gap: 6px;
}

.legend-icon-done {
	color: var(--p-green-500, #10b981);
}

.legend-icon-pending {
	color: var(--p-surface-500, #9ca3af);
}

/* Main content */
.content-col {
	grid-column: span 10;
	display: grid;
	gap: var(--gap);
	grid-template-columns: repeat(12, minmax(0, 1fr));
	grid-template-rows: auto 1fr auto;
	min-height: 0;
}

/* Header */
.topbar-card {
	grid-column: 1 / -1;
}

.topbar-inner {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.title-left {
	display: flex;
	flex-direction: column;
}

.title-line {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}

.eyebrow {
	font-size: 0.75rem;
	color: var(--text-muted);
	text-transform: uppercase;
	margin-bottom: 0.25rem;
}

.kunde {
	font-weight: bold;
}

.pc {
	color: var(--text);
}

.actions {
	display: flex;
	align-items: center;
}

/* Charts */
.charts-row {
	grid-column: 1 / -1;
	grid-row: 2;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	align-items: stretch;
	min-height: 0;
	height: 100%;
}

.chart-lg {
	grid-column: span 9;
}

.chart-sm {
	grid-column: span 3;
}

/* Card / chart layout */
.chart-card {
	min-height: 0;
	height: 100%;
	overflow: hidden;
}

.chart-card :deep(.p-card) {
	display: flex;
	flex-direction: column;
	min-height: 0;
	height: 100%;
}

.chart-card :deep(.p-card-header) {
	flex: 0 0 auto;
}

.chart-card :deep(.p-card-body) {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	padding: 8px 10px;
}

.chart-card :deep(.p-card-content) {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
}

.chart-pad,
.chart-body {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
}

.chart-card :deep(canvas),
.chart-card :deep(svg) {
	width: 100% !important;
	height: 100% !important;
	max-width: none;
	max-height: none;
}

.chart-card :deep(.p-card-header) {
	padding: 0.25rem 0.5rem !important;
	display: flex;
	align-items: center;
	gap: 0.5rem;
	font-weight: 600;
	font-size: 0.95rem;
	color: var(--text, #334155);
	background: color-mix(in oklab, var(--surface) 94%, transparent);
	border-bottom: 1px solid
		color-mix(in oklab, var(--border, #e5e7eb) 70%, transparent);
}

.chart-card :deep(.p-card-header .pi) {
	margin-right: 0.25rem;
}

/* Table */
.table-card {
	grid-column: 1 / -1;
	height: clamp(200px, 20vh, 360px);
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.table-pad {
	padding: 0.15rem;
}

.table-card :deep(input),
.table-card :deep(.p-inputtext) {
	width: 100%;
	box-sizing: border-box;
	height: 28px;
	padding: 2px 6px;
	font-size: 0.875rem;
	line-height: 1.2;
	color: var(--text) !important;
}

.table-card :deep(::placeholder) {
	color: color-mix(in oklab, var(--text) 55%, transparent);
}

.table-card :deep(td) {
	padding: 1px 2px;
}

.card-placeholder {
	min-height: 120px;
	display: grid;
	place-items: center;
	color: var(--muted);
	border: 1px dashed var(--border);
	border-radius: 12px;
	background: color-mix(in oklab, var(--surface) 65%, transparent);
}

/* Responsive */
@media (max-width: 1199px) {
	.filters-col {
		grid-column: 1 / -1;
	}

	.content-col {
		grid-column: 1 / -1;
	}

	.chart-lg,
	.chart-sm {
		grid-column: 1 / -1;
	}
}
</style>
