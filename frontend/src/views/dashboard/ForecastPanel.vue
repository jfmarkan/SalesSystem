<template>
	<!-- Toast para mensajes -->
	<Toast />

<!-- Confirmación de cambios sin guardar → estilo PrimeVue demo -->
<Dialog
  v-model:visible="confirmVisible"
  :modal="true"
  :draggable="false"
  :dismissableMask="true"
  header="Ungespeicherte Änderungen"
  :style="{ width: '380px' }"
  class="confirm-dialog"
  :maskClass="'confirm-dialog-mask'"
  appendTo="body"
>
  <div class="confirm-body">
    <!-- Icono central redondo como en el ejemplo -->
    <div class="confirm-icon-row">
      <div class="confirm-icon-circle">
        <span class="confirm-icon-mark">!</span>
      </div>
    </div>

    <!-- Texto -->
    <p class="confirm-text">
      Es gibt nicht gespeicherte Änderungen. Möchtest du sie speichern?
    </p>

    <div class="confirm-divider"></div>

    <!-- Botones estilo Cancel / Save -->
    <div class="confirm-actions">
      <Button
        label="Abbrechen"
        icon="pi pi-times"
        size="small"
		class="cancel-btn"
        @click="(() => { confirmVisible = false; pendingChange = null })()"
      />
      <Button
        label="Verwerfen"
        icon="pi pi-trash"
        size="small"
		class="discard-btn"
        @click="discardAndApply"
      />
      <Button
        label="Speichern"
        icon="pi pi-check"
        size="small"
        class="confirm-save-btn"
        @click="saveAndApply"
      />
    </div>
  </div>
</Dialog>

	<!-- Loader fullscreen -->
	<LoaderFullScreen v-if="loadingAll" />

	<!-- Grid principal -->
	<div class="forecast-grid">
		<!-- Filtros -->
		<aside class="pane left">
			<!-- Bloque de filtros: se estira, el botón está abajo -->
			<div class="filters-inner p-3">
				<ForecastFilters class="ff-host" :mode="mode" :primary-options="primaryOptions" :primary-id="primaryId"
					:secondary-options="secondaryOptions" :secondary-id="secondaryId" @update:mode="onMode"
					@update:primary-id="onPrimary" @update:secondary-id="onSecondary" />

				<div class="mt-2 text-muted text-sm" v-if="loading">
					Lädt…
				</div>
			</div>

			<!-- Botón fijo abajo del pane -->
			<Button icon="pi pi-arrow-right" label="Weiter" severity="primary" class="btn-next" @click="handleNext" />
		</aside>



		<!-- Contenido -->
		<main class="content-col">
			<!-- Header -->
			<Card class="topbar-card">
				<template #content>
					<div class="topbar-inner">
						<div class="title-left">
							<div class="eyebrow">Forecast</div>
							<div class="title-line">
								<strong class="kunde">{{ selectedClientName || 'Kunde' }}</strong>
								<span class="sep" aria-hidden="true"> | </span>
								<span class="pc">{{ pcTitlePretty || selectedPCNameRaw || '(PC)' }}</span>
							</div>
						</div>
						<div class="actions">
							<Button label="Speichern" icon="pi pi-save" :disabled="changedCount === 0 || loadingAll"
								:outlined="changedCount === 0 || loadingAll" @click="saveForecast" />
						</div>
					</div>
				</template>
			</Card>

			<!-- Charts -->
			<div class="charts-row">
				<Card class="chart-card chart-lg">
					<template #header>
						Monatliche Entwicklung (kumuliert)
					</template>
					<template #content>
						<div ref="cumWrap" class="card-body-fit chart-pad">
							<div class="chart-body">
								<template v-if="hasSelection && !loadingAll">
									<LineChartSmart :key="`cum-${cumH}-${currentClientId}-${currentPcId}`"
										type="cumulative" :client-id="currentClientId" :profit-center-id="currentPcId"
										api-prefix="/api" :auto-fetch="false" :cum-data="liveCumData" />
								</template>
								<div v-else class="chart-empty" />
							</div>
						</div>
					</template>
				</Card>

				<Card class="chart-card chart-sm">
					<template #header>Versionen</template>
					<template #content>
						<div ref="verWrap" class="card-body-fit chart-pad">
							<div class="chart-body">
								<template v-if="hasSelection && !loadingAll">
									<LineChartSmart :key="`ver-${verH}-${currentClientId}-${currentPcId}`"
										type="versions" :client-id="currentClientId" :profit-center-id="currentPcId"
										api-prefix="/api" :auto-fetch="true" />
								</template>
								<div v-else class="chart-empty" />
							</div>
						</div>
					</template>
				</Card>
			</div>

			<!-- Tabla -->
			<Card class="table-card">
				<template #content>
					<div class="table-pad">
						<template v-if="hasSelection && !loadingAll">
							<div class="nav-bar flex items-center justify-end gap-8 mb-2">
								<Button icon="pi pi-angle-left" size="small" outlined @click="shiftViewport(-1)"
									:disabled="viewportStart <= 0" />
								<span class="range-label">{{ rangeLabelDE }}</span>
								<Button icon="pi pi-angle-right" size="small" outlined @click="shiftViewport(+1)"
									:disabled="viewportStart >= maxViewportStart" />
							</div>
							<ForecastTable ref="tableRef" :months="months" :ventas="sales" :budget="budget"
								:forecast="forecast" :viewport-start="viewportStart" :viewport-size="12"
								:is-editable-ym="isEditableYM" :highlight-mandatory="true" @edit-forecast="({ index, value }) => {
									const n = Number(String(value).replace(',', '.'))
									forecast[index] = isNaN(n) ? 0 : n
								}" />
						</template>
						<div v-else class="card-placeholder">
							Kunde / Profit-Center nicht ausgewählt
						</div>
					</div>
				</template>
			</Card>
		</main>
	</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Toast from 'primevue/toast'
import LoaderFullScreen from '@/components/ui/LoaderFullScreen.vue'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import ForecastFilters from '@/components/filters/ComponentFilter.vue'
import ForecastTable from '@/components/tables/ComponentTable.vue'
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
		return {
			disconnect() {
				clearInterval(id)
				window.removeEventListener('resize', measure)
			},
		}
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
	const out = []
	const base = new Date(y, m - 1, 1)
	for (let i = 0; i < count; i++) {
		const d = new Date(base.getFullYear(), base.getMonth() + i, 1)
		out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
	}
	return out
}
function computeTableMonthsFiscal(now = new Date()) {
	const m = now.getMonth() + 1
	const fy = fyOf(now)
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
	if (mode.value === 'pc') return profitCenters.value.map((p) => ({ label: `${p.name}`, value: p.id }))
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
		const code = hit?.code
		const name = hit?.name
		return `${code ?? ''} ${name ?? m[1].trim()}`.replace(/\s+/g, ' ').trim()
	}
	return raw.startsWith('(') ? raw : `(${raw})`
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
	const e = months.value[Math.min(months.value.length - 1, viewportStart.value + viewportSize - 1)]
	return s && e ? `${fmtMonthShortDE(s)} - ${fmtMonthShortDE(e)}` : ''
})

const originalForecast = ref(forecast.value.slice())
function isClose(a, b, eps = 1e-6) {
	return Math.abs(Number(a || 0) - Number(b || 0)) <= eps
}
const changedIndices = computed(() => {
	const len = months.value?.length || 0
	const out = []
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
	const now = new Date()
	const cur = new Date(now.getFullYear(), now.getMonth(), 1)
	const next = new Date(now.getFullYear(), now.getMonth() + 1, 1)
	const [yS, mS] = String(ym).split('-')
	const y = +yS
	const m = +mS
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
		await Promise.all([
			loadSeriesTable(), // tabla + versiones
			loadSeriesChart(), // datos del chart acumulado
		])

		await nextTick() // Esperar render del DOM
		// Esperar un frame más para que los gráficos monten visualmente
		await new Promise((resolve) => requestAnimationFrame(resolve))
	} finally {
		loadingAll.value = false // Solo ahora desaparece el loader
	}
})

onMounted(() => {
	loadMaster()
})

const currentClientId = computed(
	() => (mode.value === 'client' ? primaryId.value : secondaryId.value),
)
const currentPcId = computed(
	() => (mode.value === 'client' ? secondaryId.value : primaryId.value),
)
const tableRef = ref(null)
</script>

<style scoped>
/* === GRID PRINCIPAL === */
.forecast-grid {
	--gap: 16px;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	padding: var(--pad-y) var(--pad-x, 16px);
	box-sizing: border-box;
	height: 100%;
	overflow: hidden;
}

/* === SIDEBAR (Filtros) === */
.pane {
	background: var(--surface-card, #fff);
	border-radius: 10px;
	box-shadow: 0 1px 8px rgba(0, 0, 0, .06);
	padding: 10px;
	overflow: auto;
}

/* LEFT */
.pane.left {
	grid-column: span 2;
	padding: 1rem;
	display: flex;
	flex-direction: column;
	gap: 16px;
	min-height: 0;
}

.pane.left .pane-head {
	display: flex;
	gap: 16px;
	align-items: center;
}

/* El wrapper directo del componente hijo: le pasa el alto */
.selector-host {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	min-height: 0;
}

/* El host del componente hijo: debe crecer */
.ff-host {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	min-height: 0;
}

.filters-inner {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	min-height: 0;
}

.filters-footer {
	margin-top: auto;
}

.btn-next {
	width: 100%;
}

/* === CONTENIDO PRINCIPAL === */
.content-col {
	grid-column: span 10;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	grid-template-rows: auto 1fr auto;
	/* Header, Charts, Tabla */
	gap: var(--gap);
	min-height: 0;
	height: 100%;
}

/* === TOPBAR === */
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

/* === CHARTS (100% alto dentro de la card) === */
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

/* El contenedor del Card ocupa todo y permite que el body crezca */
.chart-card {
	min-height: 0;
	height: 100%;
	overflow: hidden;
}

/* PrimeVue Card: header fijo, body estirable */
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
	/* si querés margen interno, ajustá aquí: */
	padding: 8px 10px;
}

.chart-card :deep(.p-card-content) {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
}

/* Tus wrappers internos también llenan el alto */
.chart-pad,
.chart-body {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
}

/* El lienzo del chart ocupa 100% del espacio disponible */
.chart-card :deep(canvas),
.chart-card :deep(svg) {
	width: 100% !important;
	height: 100% !important;
	max-width: none;
	max-height: none;
}

.chart-card :deep(.p-card-header) {
	padding: .25rem .5rem !important;
	/* lo que pediste */
	display: flex;
	align-items: center;
	gap: .5rem;

	/* toques suaves opcionales (no rompen nada) */
	font-weight: 600;
	font-size: .95rem;
	color: var(--text, #334155);
	background: color-mix(in oklab, var(--surface) 94%, transparent);
	border-bottom: 1px solid color-mix(in oklab, var(--border, #e5e7eb) 70%, transparent);
}

/* Si incluís iconos en el header en algún momento */
.chart-card :deep(.p-card-header .pi) {
	margin-right: .25rem;
}


/* === TABLA === */
.table-card {
	grid-column: 1 / -1;
	height: clamp(240px, 25vh, 360px);
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.table-pad {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-height: 0;

}

.nav-bar {
	padding: 2px 6px;
	margin-bottom: 6px;
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 0.75rem;
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

.table-card :deep(.p-datatable-wrapper) {
	flex: 1 1 auto;
	min-height: 0;
	overflow: auto;
}

.table-card :deep(.p-paginator) {
	flex: 0 0 auto;
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

/* ========== CONFIRM DIALOG ========== */

/* ===== Confirm dialog estilo PrimeVue demo ===== */

/* Fondo con ligera oscuridad y blur suave */
:deep(.confirm-dialog-mask) {
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  background: rgba(15, 23, 42, 0.25);
}

/* Caja del diálogo */
:deep(.confirm-dialog) {
  border-radius: 14px;
  box-shadow: 0 18px 40px rgba(15, 23, 42, 0.35);
}

/* Header tipo screenshot: compacto, con título y X */
:deep(.confirm-dialog .p-dialog-header) {
  padding: 0.8rem 1rem;
  border-bottom: 1px solid #e5e7eb;
}

:deep(.confirm-dialog .p-dialog-title) {
  font-size: 0.9rem;
  font-weight: 600;
  color: #111827;
}

/* Icono de cerrar más pequeño y discreto */
:deep(.confirm-dialog .p-dialog-header-icon) {
  width: 26px;
  height: 26px;
  border-radius: 999px;
  padding: 0;
  font-size: 0.75rem;
  color: #6b7280;
}

:deep(.confirm-dialog .p-dialog-header-icon:hover) {
  background: rgba(148, 163, 184, 0.2);
  color: #111827;
}

/* Contenido interior */
:deep(.confirm-dialog .p-dialog-content) {
  padding: 0.75rem 1rem 0.75rem 1rem;
}

/* Layout interno del dialog */
.confirm-body {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

/* Icono circular central */
.confirm-icon-row {
  display: flex;
  justify-content: center;
  margin-top: 0.25rem;
}

.confirm-icon-circle {
  width: 52px;
  height: 52px;
  border-radius: 999px;
  border: 2px solid #9ca3af;          /* gris azulado como el ejemplo */
  display: flex;
  align-items: center;
  justify-content: center;
  color: #4b5563;
}

.confirm-icon-mark {
  font-size: 1.4rem;
  line-height: 1;
}

/* Texto principal */
.confirm-text {
  margin: 0.5rem 0 0.9rem 0;
  text-align: center;
  font-size: 0.85rem;
  color: #374151;
}

/* Divider sobre los botones */
.confirm-divider {
  height: 1px;
  background: #e5e7eb;
}

/* Footer con botones a la derecha */
.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 0.35rem;
}

/* Botones relativamente pequeños y pill-shaped */
.confirm-actions :deep(.p-button) {
  font-size: 0.8rem;
  padding: 0.35rem 0.8rem;
}

/* Cancel → outlined blanco, como el screenshot */
.confirm-actions :deep(.p-button-outlined) {
  background: #ffffff;
  border-color: #d1d5db;
  color: #111827;
}

.confirm-actions :deep(.p-button-outlined:hover) {
  background: #f9fafb;
}

/* Verwerfen → texto gris (más discreto) */
.confirm-actions :deep(.p-button-text) {
  color: #6b7280;
}

.confirm-actions :deep(.p-button-text:hover) {
  background: rgba(148, 163, 184, 0.1);
}

/* Speichern → botón oscuro estilo "Save" */
.confirm-save-btn :deep(.p-button-label),
.confirm-save-btn :deep(.p-button-icon) {
  /* nada aquí, sólo para tener selector */
}

.confirm-save-btn {
  /* wrapper */
}

.confirm-actions :deep(.cancel-btn.p-button) {
  background: #737373;   /* casi negro, como el screenshot */
  border-color: #737373;
  color: #f9fafb;
}

.confirm-actions :deep(.discard-btn.p-button) {
  background: #A3535B;   /* casi negro, como el screenshot */
  border-color: #A3535B;
  color: #f9fafb;
}

.confirm-actions :deep(.confirm-save-btn.p-button) {
  background: #668C73;   /* casi negro, como el screenshot */
  border-color: #668C73;
  color: #f9fafb;
}

.confirm-actions :deep(.cancel-btn.p-button:hover) {
  background: #4F4F4F;
  border-color: #4F4F4F;
}

.confirm-actions :deep(.discard-btn.p-button:hover) {
  background: #8C474F;
  border-color: #8C474F;
}

.confirm-actions :deep(.confirm-save-btn.p-button:hover) {
  background: #557761;
  border-color: #557761;
}

/* === RESPONSIVE === */
@media (max-width: 1199px) {

	.filters-col,
	.content-col {
		grid-column: 1 / -1;
	}

	.chart-lg,
	.chart-sm {
		grid-column: 1 / -1;
	}
}

@media (max-width: 700px) {
	.table-card {
		height: clamp(200px, 35vh, 300px);
	}
}
</style>
