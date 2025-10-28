<template>
        <!-- The Toast component to show messages -->
		<Toast />

        <!-- Confirmation Dialog for unsaved changes -->
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
					@click="((confirmVisible = false), (pendingChange = null))"
				/>
				<Button label="Verwerfen" severity="danger" @click="discardAndApply" />
				<Button label="Speichern" icon="pi pi-save" @click="saveAndApply" />
			</div>
		</Dialog>

        <!-- Loader efect for the whole page -->
		<div v-if="loadingAll" class="page-loader glass">
			<ProgressSpinner style="width: 48px; height: 48px" strokeWidth="4" />
			<div class="loader-text">Lädt Daten…</div>
		</div>

        <!-- Main Forecast Grid Layout -->  
		<div class="container-fluid forecast-grid">
			<!-- L (2/12) -->
			<aside class="filters-col">
				<GlassCard title="Filter" class="filters-card">
					<div class="filters-inner">
						<div class="field-block flex-1 min-h-0">
							<div class="selector-host">
								<ForecastFilters
									class="ff-host"
									:mode="mode"
									:primary-options="primaryOptions"
									:primary-id="primaryId"
									:secondary-options="secondaryOptions"
									:secondary-id="secondaryId"
									@update:mode="onMode"
									@update:primary-id="onPrimary"
									@update:secondary-id="onSecondary"
									@next="handleNext"
								/>
								<div class="mt-2 text-muted text-sm" v-if="loading">Lädt…</div>
							</div>
						</div>

						<div class="filters-footer">
							<Button label="Weiter" icon="pi pi-arrow-right" @click="handleNext" />
						</div>
					</div>
				</GlassCard>
			</aside>

			<!-- R (10/12) -->
			<main class="content-col">
				<header class="topbar glass topbar--compact">
					<div class="title-left">
						<div class="eyebrow">Forecast</div>
						<div class="title-line">
							<strong class="kunde">{{ selectedClientName || 'Kunde' }}</strong>
							<span class="sep" aria-hidden="true"></span>
							<span class="pc">
								{{ pcTitlePretty || selectedPCNameRaw || '(PC)' }}
							</span>
						</div>
					</div>

					<div class="actions">
						<Button
							label="Speichern"
							icon="pi pi-save"
							:disabled="changedCount === 0 || loadingAll"
							:outlined="changedCount === 0 || loadingAll"
							@click="saveForecast"
						/>
					</div>
				</header>

				<div class="charts-row">
					<GlassCard
						:title="'Monatliche Entwicklung (kumuliert)'"
						class="no-strip chart-card"
					>
						<!-- Observado para medir alto disponible -->
						<div ref="cumWrap" class="card-body-fit chart-pad">
							<div class="chart-body" :style="{ height: cumH + 'px' }">
								<template v-if="hasSelection && !loadingAll">
									<LineChartSmart
										:key="`cum-${cumH}-${currentClientId}-${currentPcId}`"
										type="cumulative"
										:client-id="currentClientId"
										:profit-center-id="currentPcId"
										api-prefix="/api"
										:auto-fetch="false"
										:cum-data="liveCumData"
									/>
								</template>
								<div v-else class="chart-empty"></div>
							</div>
						</div>
					</GlassCard>

					<GlassCard :title="'Versionen'" class="no-strip chart-card">
						<!-- Observado para medir alto disponible -->
						<div ref="verWrap" class="card-body-fit chart-pad">
							<div class="chart-body" :style="{ height: verH + 'px' }">
								<template v-if="hasSelection && !loadingAll">
									<LineChartSmart
										:key="`ver-${verH}-${currentClientId}-${currentPcId}`"
										type="versions"
										:client-id="currentClientId"
										:profit-center-id="currentPcId"
										api-prefix="/api"
										:auto-fetch="true"
									/>
								</template>
								<div v-else class="chart-empty"></div>
							</div>
						</div>
					</GlassCard>
				</div>

				<GlassCard :title="''" class="no-strip table-card">
					<div class="table-pad">
						<template v-if="hasSelection && !loadingAll">
							<div class="nav-bar flex items-center justify-end gap-8 mb-2">
								<Button
									icon="pi pi-angle-left"
									size="small"
									outlined
									@click="shiftViewport(-1)"
									:disabled="viewportStart <= 0"
								/>
								<span class="range-label">{{ rangeLabelDE }}</span>
								<Button
									icon="pi pi-angle-right"
									size="small"
									outlined
									@click="shiftViewport(+1)"
									:disabled="viewportStart >= maxViewportStart"
								/>
							</div>

							<ForecastTable
								ref="tableRef"
								:months="months"
								:ventas="sales"
								:budget="budget"
								:forecast="forecast"
								:viewport-start="viewportStart"
								:viewport-size="12"
								:is-editable-ym="isEditableYM"
								@edit-forecast="
									({ index, value }) => {
										const n = Number(String(value).replace(',', '.'))
										forecast[index] = isNaN(n) ? 0 : n
									}
								"
							/>
						</template>
						<div v-else class="card-placeholder">
							Kunde / Profit-Center nicht ausgewählt
						</div>
					</div>
				</GlassCard>
			</main>
		</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Toast from 'primevue/toast'
import ProgressSpinner from 'primevue/progressspinner'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import ForecastFilters from '@/components/filters/ComponentFilter.vue'
import ForecastTable from '@/components/tables/ComponentTable.vue'
import GlassCard from '@/components/ui/GlassCard.vue'
import LineChartSmart from '@/components/charts/LineChartSmart.vue'

const toast = useToast()
const API = '/api'

/* ====================== ResizeObserver para ambos charts ====================== */
const cumWrap = ref(null)
const verWrap = ref(null)
const cumH = ref(280) // fallback
const verH = ref(280) // fallback
let roCum, roVer

function observeHeight(el, setter) {
	if (!el) return
	const measure = () => {
		// Medimos content box: clientHeight - paddings
		const style = getComputedStyle(el)
		const pt = parseFloat(style.paddingTop) || 0
		const pb = parseFloat(style.paddingBottom) || 0
		const content = Math.max(160, el.clientHeight - pt - pb)
		setter(Math.round(content))
		// Muchas libs escuchan sólo window.resize -> disparamos nudge
		requestAnimationFrame(() => window.dispatchEvent(new Event('resize')))
	}
	measure()
	const ro = new ResizeObserver(measure)
	ro.observe(el)
	// Fallback simple si RO no existe
	if (!('ResizeObserver' in window)) {
		window.addEventListener('resize', measure)
		const id = setInterval(measure, 500)
		return { disconnect() { clearInterval(id); window.removeEventListener('resize', measure) } }
	}
	return ro
}

onMounted(() => {
	roCum = observeHeight(cumWrap.value, (h) => (cumH.value = h))
	roVer = observeHeight(verWrap.value, (h) => (verH.value = h))
})
onBeforeUnmount(() => {
	roCum?.disconnect?.()
	roVer?.disconnect?.()
})

/* ====================== Lógica original ====================== */

function fyOf(d = new Date()) {
	const y = d.getFullYear()
	return d.getMonth() + 1 >= 4 ? y : y - 1
}
function genFrom(y, m, count) {
	const out = [], base = new Date(y, m - 1, 1)
	for (let i = 0; i < count; i++) {
		const d = new Date(base.getFullYear(), base.getMonth() + i, 1)
		out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
	}
	return out
}
function computeTableMonthsFiscal(now = new Date()) {
	const m = now.getMonth() + 1, fy = fyOf(now)
	return genFrom(fy, 4, m >= 10 || m <= 3 ? 18 : 12)
}
function fmtMonthShortDE(ym) {
	const [y, m] = ym.split('-').map(Number)
	const map = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
	return `${map[m - 1]} ${String(y).slice(2)}`
}

const clients = ref([]),
	profitCenters = ref([]),
	mapClientToPC = ref({}),
	mapPCToClient = ref({})
const mode = ref(''),
	primaryId = ref(null),
	secondaryId = ref(null)
const loading = ref(false),
	loadingAll = ref(false)

function normalizeMode(v) {
	const s = (v ?? '').toString().toLowerCase().trim()
	if (['client', 'cliente', 'kunde'].includes(s)) return 'client'
	if (['pc', 'profit', 'profitcenter', 'profit center'].includes(s)) return 'pc'
	return ''
}
function onMode(v) {
	guardedChange('mode', normalizeMode(v))
}
function onPrimary(v) {
	guardedChange('primary', v)
}
function onSecondary(v) {
	guardedChange('secondary', v)
}

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
				const p = profitCenters.value.find((x) => x.id === id)
				return p ? { label: `${p.code} — ${p.name}`, value: p.id } : null
			})
			.filter(Boolean)
	} else {
		const ids = mapPCToClient.value[primaryId.value] || []
		return ids
			.map((id) => {
				const c = clients.value.find((x) => x.id === id)
				return c ? { label: c.name, value: c.id } : null
			})
			.filter(Boolean)
	}
})

const hasSelection = computed(
	() => !!mode.value && primaryId.value != null && secondaryId.value != null,
)
const selectedClientName = computed(() => {
	const id = mode.value === 'client' ? primaryId.value : secondaryId.value
	const c = clients.value.find((x) => x.id === id)
	return c ? c.name : ''
})
const selectedPCNameRaw = computed(() => {
	const pcId = mode.value === 'client' ? secondaryId.value : primaryId.value
	const p = profitCenters.value.find((x) => x.id === pcId)
	return p ? `${p.code} — ${p.name}` : ''
})
const pcTitlePretty = computed(() => {
	const raw = selectedPCNameRaw.value || ''
	if (!raw) return ''
	const m = raw.split('—')
	if (m.length === 2) {
		const hit = profitCenters.value.find((x) => `${x.name}` === raw)
		const code = hit?.code, name = hit?.name
		return `${code ?? ''} ${name ?? m[1].trim()}`.replace(/\s+/g, ' ').trim()
	}
	return raw.startsWith('(') ? raw : `(${raw}`
})

const months = ref(computeTableMonthsFiscal())
const sales = ref(Array(months.value.length).fill(0))
const budget = ref(Array(months.value.length).fill(0))
const forecast = ref(Array(months.value.length).fill(0))
const orders = ref(Array(months.value.length).fill(0))

const viewportStart = ref(0),
	viewportSize = 12
const maxViewportStart = computed(() => Math.max(0, months.value.length - viewportSize))
function shiftViewport(step) {
	const n = Math.min(maxViewportStart.value, Math.max(0, viewportStart.value + step))
	if (n !== viewportStart.value) {
		viewportStart.value = n
		nextTick(() => tableRef.value?.scrollToIndex?.(viewportStart.value))
	}
}
const rangeLabelDE = computed(() => {
	const s = months.value[viewportStart.value]
	const e =
		months.value[Math.min(months.value.length - 1, viewportStart.value + viewportSize - 1)]
	return s && e ? `${fmtMonthShortDE(s)} - ${fmtMonthShortDE(e)}` : ''
})

const originalForecast = ref(forecast.value.slice())
function isClose(a, b, eps = 1e-6) {
	return Math.abs(Number(a || 0) - Number(b || 0)) <= eps
}
const changedIndices = computed(() => {
	const len = months.value?.length || 0, out = []
	for (let i = 0; i < len; i++) {
		const ym = months.value?.[i]
		if (!isEditableYM(ym)) continue
		if (!isClose(forecast.value[i], originalForecast.value[i])) out.push(i)
	}
	return out
})
const changedCount = computed(() => changedIndices.value.length)
function isEditableYM(ym) {
	if (!ym) return false
	const now = new Date(),
		cur = new Date(now.getFullYear(), now.getMonth(), 1),
		next = new Date(now.getFullYear(), now.getMonth() + 1, 1)
	const [yS, mS] = String(ym).split('-')
	const y = +yS, m = +mS
	const target = new Date(y, m - 1, 1)
	if (target <= cur) return false
	if (target.getTime() === next.getTime()) return now.getDate() <= 15
	return true
}

async function loadMaster() {
	try {
		await ensureCsrf()
		const [resC, resP, resM] = await Promise.all([
			api.get(API + '/me/clients'),
			api.get(API + '/me/profit-centers'),
			api.get(API + '/me/assignments'),
		])
		clients.value = Array.isArray(resC.data) ? resC.data : []
		profitCenters.value = Array.isArray(resP.data) ? resP.data : []
		mapClientToPC.value = resM.data?.clientToPc ?? {}
		mapPCToClient.value = resM.data?.pcToClient ?? {}
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Stammdaten nicht verfügbar',
			life: 5000,
		})
	}
}
async function loadSeriesTable() {
	if (!hasSelection.value) return
	await ensureCsrf()
	const clientId = mode.value === 'client' ? primaryId.value : secondaryId.value
	const profitCenterId = mode.value === 'client' ? secondaryId.value : primaryId.value
	const { data } = await api.get(API + '/forecast/series-table', {
		params: { clientId, profitCenterId },
	})
	months.value = Array.isArray(data.months) ? data.months : computeTableMonthsFiscal()
	sales.value = (data.sales ?? []).map(Number).slice(0, months.value.length)
	budget.value = (data.budget ?? []).map(Number).slice(0, months.value.length)
	forecast.value = (data.forecast ?? []).map(Number).slice(0, months.value.length)
	orders.value = (data.orders ?? []).map(Number).slice(0, months.value.length)
	viewportStart.value = 0
	originalForecast.value = forecast.value.slice()
	await loadCurrentMonthVersions()
	await nextTick()
	tableRef.value?.scrollToIndex?.(viewportStart.value)
}
const chartCumData = ref(null)
async function loadSeriesChart() {
	if (!hasSelection.value) {
		chartCumData.value = null
		return
	}
	await ensureCsrf()
	const clientId = mode.value === 'client' ? primaryId.value : secondaryId.value
	const profitCenterId = mode.value === 'client' ? secondaryId.value : primaryId.value
	const { data } = await api.get(API + '/forecast/series', {
		params: { clientId, profitCenterId },
	})
	chartCumData.value = {
		months: data.months || [],
		sales_cum: data.sales_cum || [],
		budget_cum: data.budget_cum || [],
		forecast_cum: data.forecast_cum || [],
		budget_fy_line: data.budget_fy_line || [],
	}
}
const liveCumData = computed(() => chartCumData.value)
const versionHistory = ref(null)
async function loadCurrentMonthVersions() {
	if (!hasSelection.value) return
	await ensureCsrf()
	const clientId = mode.value === 'client' ? primaryId.value : secondaryId.value
	const profitCenterId = mode.value === 'client' ? secondaryId.value : primaryId.value
	const res = await api.get(API + '/forecast/current-month-versions', {
		params: { clientId, profitCenterId },
	})
	versionHistory.value = res.data
}

async function saveForecast() {
	if (!hasSelection.value) return
	if (changedCount.value === 0) {
		toast.add({
			severity: 'info',
			summary: 'Keine Änderungen',
			detail: 'Es gibt nichts zu speichern',
			life: 1600,
		})
		return
	}
	try {
		await ensureCsrf()
		const clientId = mode.value === 'client' ? primaryId.value : secondaryId.value
		const profitCenterId = mode.value === 'client' ? secondaryId.value : primaryId.value
		const body = {
			clientId,
			profitCenterId,
			months: months.value.slice(),
			forecast: forecast.value.map((v) => Math.max(0, Number(v) || 0)),
		}
		await api.put(API + '/forecast/series-table', body)
		originalForecast.value = forecast.value.slice()
		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Änderungen gespeichert',
			life: 2200,
		})
		loadingAll.value = true
		await Promise.all([loadSeriesTable(), loadSeriesChart()])
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Speichern fehlgeschlagen',
			life: 2500,
		})
	} finally {
		loadingAll.value = false
	}
}

const confirmVisible = ref(false),
	pendingChange = ref(null)
function applyChange(kind, value) {
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
function guardedChange(kind, value) {
	if (changedCount.value > 0) {
		pendingChange.value = { kind, value }
		confirmVisible.value = true
	} else applyChange(kind, value)
}
async function saveAndApply() {
	try {
		await saveForecast()
	} finally {
		confirmVisible.value = false
		if (pendingChange.value) {
			applyChange(pendingChange.value.kind, pendingChange.value.value)
			pendingChange.value = null
		}
	}
}
function discardAndApply() {
	for (let i = 0; i < forecast.value.length; i++)
		forecast.value[i] = Number(originalForecast.value[i] ?? 0)
	confirmVisible.value = false
	if (pendingChange.value) {
		applyChange(pendingChange.value.kind, pendingChange.value.value)
		pendingChange.value = null
	}
}

function clearSeries() {
	const m = computeTableMonthsFiscal()
	months.value = m
	sales.value = Array(m.length).fill(0)
	budget.value = Array(m.length).fill(0)
	forecast.value = Array(m.length).fill(0)
	orders.value = Array(m.length).fill(0)
	originalForecast.value = forecast.value.slice()
	viewportStart.value = 0
}
function handleNext() {
	const list = secondaryOptions.value
	if (!list?.length) return
	const idx = list.findIndex((o) => o.value === secondaryId.value)
	const n = (idx >= 0 ? idx + 1 : 0) % list.length
	guardedChange('secondary', list[n].value)
}

watch([mode, primaryId, secondaryId], async () => {
	loading.value = false
	if (!hasSelection.value) return
	loadingAll.value = true
	try {
		await Promise.all([loadSeriesTable(), loadSeriesChart()])
	} finally {
		loadingAll.value = false
	}
})
onMounted(() => {
	loadMaster()
})

const currentClientId = computed(() =>
	mode.value === 'client' ? primaryId.value : secondaryId.value,
)
const currentPcId = computed(() => (mode.value === 'client' ? secondaryId.value : primaryId.value))
const tableRef = ref(null)
</script>

<style lang="scss" scoped>
.forecast-grid {
	--title-h: 60px;
	--title-pad-y: 6px;
	--title-pad-x: 10px;
}

.forecast-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 10px;
	height: calc(100vh - var(--navbar-h));
}
@media (min-width: 768px) {
	.forecast-grid {
		grid-template-columns: 2fr 10fr;
	}
}

.filters-col {
	min-height: 0;
	display: flex;
	flex-direction: column;
}
.filters-card {
	height: 100%;
	display: flex;
	flex-direction: column;
}
.filters-inner {
	display: flex;
	flex-direction: column;
	gap: 10px;
	padding: 10px;
	height: 100%;
	min-height: 0;
}
.selector-host {
	height: 100%;
	min-height: 0;
	display: flex;
	flex-direction: column;
	overflow: hidden;
}
.filters-footer {
	margin-top: auto;
	display: flex;
}
.filters-footer .p-button {
	width: 100%;
}
.ff-host :deep(.p-button:has(.pi-arrow-right)) {
	display: none !important;
}
.selector-host :deep(.p-dropdown),
.selector-host :deep(.p-inputtext),
.selector-host :deep(.p-autocomplete),
.selector-host :deep(.p-multiselect) {
	width: 100% !important;
	display: block;
}
.selector-host :deep(.p-listbox) {
	width: 100%;
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	flex-direction: column;
}
.selector-host :deep(.p-listbox-list-wrapper) {
	flex: 1 1 auto;
	min-height: 0;
}
.selector-host :deep(.p-listbox-list) {
	overflow: auto;
}

.content-col {
	display: grid;
	grid-template-rows: var(--title-h) 1fr auto;
	gap: 10px;
	min-height: 0;
}

.topbar {
	background: var(--surface);
	border: 1px solid var(--border);
	border-radius: 15px;
	box-shadow: var(--glass-shadow);
}
.topbar--compact {
	min-height: 42px;
	padding: 4px 20px;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}
.topbar--compact .title-left {
	display: flex;
	flex-direction: column;
	justify-content: center;
	line-height: 1.1;
}
.topbar--compact .eyebrow {
	font-size: 12px;
    margin-bottom: 5px;
	color: var(--muted);
	text-transform: uppercase;
	letter-spacing: 0.06em;
}
.topbar--compact .title-line {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 0.95rem;
}
.topbar--compact .kunde {
	font-weight: 800;
}
.topbar--compact .pc {
	font-weight: 400;
	opacity: 0.85;
}
.topbar--compact .sep {
	width: 1px;
	height: 14px;
	background: color-mix(in oklab, var(--text) 22%, transparent);
}

/* ======== CHARTS (Responsive con ResizeObserver) ======== */
.charts-row {
	display: grid;
	grid-template-columns: 1fr;
	gap: 10px;
	min-height: 0;
	height: 100%; /* ocupa todo el 1fr de la fila central */
}
@media (min-width: 992px) {
	.charts-row {
		grid-template-columns: 7fr 3fr;
	}
}
.chart-card {
	display: flex;
	flex-direction: column;
	min-height: 0;
	height: 100%; /* que la card estire todo */
}
.card-body-fit {
	display: flex;           /* flex mejor que grid para fill vertical */
	flex-direction: column;
	flex: 1 1 auto;
	min-height: 0;
}
.chart-pad {
	padding: 0.5rem 0.25rem;
}
.chart-body {
	position: relative;
	flex: 1 1 auto;
	min-height: 0;
	width: 100%;
	overflow: hidden;
	/* height se asigna inline via :style */
}
/* Forzar a libs a respetar contenedor */
.chart-body :deep(svg),
.chart-body :deep(canvas),
.chart-body :deep(.recharts-wrapper),
.chart-body :deep(.recharts-responsive-container),
.chart-body :deep(.echarts-for-react),
.chart-body :deep(.apexcharts-canvas) {
	width: 100% !important;
	height: 100% !important;
	display: block;
}
.chart-empty {
	width: 100%;
	height: 100%;
}

/* ======== TABLE ======== */
.table-card {
	display: block;
}
.table-pad {
	padding: 10px;
}
.nav-bar {
	padding: 2px 6px;
	margin-bottom: 8px;
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
	padding: 6px 8px;
}
.table-card :deep(td[style*='background']) {
	color: #111 !important;
	text-shadow: none;
}
html.dark .table-card :deep(td[style*='background']) {
	color: #fff !important;
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
.page-loader {
	position: fixed;
	inset: var(--navbar-h) 0 0 0;
	z-index: 999;
	display: grid;
	place-items: center;
	gap: 12px;
	background: color-mix(in oklab, var(--bg) 40%, transparent);
	backdrop-filter: saturate(var(--glass-sat)) blur(6px);
}
.loader-text {
	font-size: 0.9rem;
	color: var(--text);
	opacity: 0.85;
}
.page-loader .p-progress-spinner-circle {
	animation: spinnerColor 1.8s infinite linear;
}
@keyframes spinnerColor {
	0% { stroke: #0ea5e9; }
	33% { stroke: #10b981; }
	66% { stroke: #ef4444; }
	100% { stroke: #0ea5e9; }
}
</style>