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
				<GlassCard
					:class="{ 'no-strip': item.type === 'title' || item.type === 'table' }"
					:title="cardTitle(item)"
				>
					<!-- Filtros -->
					<div v-if="item.type === 'filters'" class="h-full p-3">
						<ForecastFilters
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
						<div class="mt-3 text-500 text-sm" v-if="loading">Lädt…</div>
					</div>

					<!-- Título + acciones -->
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
								:disabled="changedCount === 0"
								@click="saveForecast"
							/>
						</div>
					</div>

					<!-- Chart principal -->
					<div v-else-if="item.type === 'chart'" class="h-full">
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
					<div v-else-if="item.type === 'chart-versions'" class="h-full">
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
					<div v-else-if="item.type === 'table'" class="h-full flex flex-column">
						<template v-if="hasSelection">
							<div
								class="nav-bar flex align-items-center justify-content-end gap-2 mb-2"
							>
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
					</div>
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

const toast = useToast()
const API = '/api'

/* ---------- Fiscal helpers (FY = Apr..Mar) ---------- */
function fyOf(date = new Date()) {
	const y = date.getFullYear()
	return date.getMonth() + 1 >= 4 ? y : y - 1
}
function genFrom(startYear, startMonth1Based, count) {
	const out = []
	const base = new Date(startYear, startMonth1Based - 1, 1)
	for (let i = 0; i < count; i++) {
		const d = new Date(base.getFullYear(), base.getMonth() + i, 1)
		out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`)
	}
	return out
}
function computeTableMonthsFiscal(now = new Date()) {
	const m = now.getMonth() + 1,
		fy = fyOf(now)
	const extended = m >= 10 || m <= 3
	return genFrom(fy, 4, extended ? 18 : 12)
}
function computeInitialViewportStart(now = new Date()) {
	const m = now.getMonth() + 1
	if (m >= 10) return m - 9
	if (m <= 3) return m + 3
	return 0
}
function fmtMonthShortDE(ym) {
	const [y, m] = ym.split('-').map((n) => parseInt(n, 10))
	const map = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
	return `${map[m - 1]} ${String(y).slice(2)}`
}
/* Para títulos: Diagramm (WJ 2025/26), Versionen (Okt 25) */
function fyTitleSpan(now = new Date()) {
	const fy = fyOf(now)
	const nextYY = String(fy + 1).slice(2)
	return `${fy}/${nextYY}`
}
function monthTitleDE(d = new Date()) {
	const m = d.getMonth() + 1,
		y = d.getFullYear()
	const map = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
	return `${map[m - 1]} ${String(y).slice(2)}`
}

/* ---- Filters + options ---- */
const clients = ref([]),
	profitCenters = ref([]),
	mapClientToPC = ref({}),
	mapPCToClient = ref({})
const mode = ref('') // '' | 'client' | 'pc'
const primaryId = ref(null)
const secondaryId = ref(null)
const loading = ref(false)

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
		return profitCenters.value.map((p) => ({ label: `${p.code} — ${p.name}`, value: p.id }))
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

/* Titles (selection) */
const selectedClienteName = computed(() => {
	const id = mode.value === 'client' ? primaryId.value : secondaryId.value
	const c = clients.value.find((x) => x.id === id)
	return c ? c.name : ''
})
const selectedPCName = computed(() => {
	const pcId = mode.value === 'client' ? secondaryId.value : primaryId.value
	const p = profitCenters.value.find((x) => x.id === pcId)
	return p ? `${p.code} — ${p.name}` : ''
})

/* Selection guard */
const hasSelection = computed(
	() => !!mode.value && primaryId.value != null && secondaryId.value != null,
)

/* Series (tabla) */
const months = ref(computeTableMonthsFiscal())
const sales = ref(Array(months.value.length).fill(0))
const budget = ref(Array(months.value.length).fill(0))
const forecast = ref(Array(months.value.length).fill(0))
const orders = ref(Array(months.value.length).fill(0))

/* Viewport (12 visibles exactos) */
const viewportStart = ref(computeInitialViewportStart())
const viewportSize = 12
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

/* Baseline + diffs */
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

/* Edit rule: next month editable until the 15th inclusive */
function isEditableYM(ym) {
	if (!ym) return false
	const now = new Date()
	const cur = new Date(now.getFullYear(), now.getMonth(), 1)
	const next = new Date(now.getFullYear(), now.getMonth() + 1, 1)
	const [yS, mS] = String(ym).split('-')
	const y = +yS,
		m = +mS
	const target = new Date(y, m - 1, 1)
	if (target <= cur) return false
	if (target.getTime() === next.getTime()) return now.getDate() <= 15
	return true
}

/* API master */
async function loadMaster() {
	try {
		await ensureCsrf()
		const [resClients, resPCs, resMap] = await Promise.all([
			api.get(API + '/me/clients'),
			api.get(API + '/me/profit-centers'),
			api.get(API + '/me/assignments'),
		])
		clients.value = Array.isArray(resClients.data) ? resClients.data : []
		profitCenters.value = Array.isArray(resPCs.data) ? resPCs.data : []
		mapClientToPC.value = resMap.data?.clientToPc ?? {}
		mapPCToClient.value = resMap.data?.pcToClient ?? {}
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Stammdaten nicht verfügbar',
			life: 5000,
		})
	}
}

/* API: tabla 12/18 */
async function loadSeriesTable() {
	if (!hasSelection.value) return
	loading.value = true
	try {
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

		viewportStart.value = Math.min(
			computeInitialViewportStart(),
			Math.max(0, months.value.length - viewportSize),
		)
		originalForecast.value = forecast.value.slice()

		await loadCurrentMonthVersions()
		await nextTick()
		tableRef.value?.scrollToIndex?.(viewportStart.value)
	} finally {
		loading.value = false
	}
}

/* API: gráfico FY (usa /forecast/series existente) */
const chartCumData = ref(null)
async function loadSeriesChart() {
	if (!hasSelection.value) {
		chartCumData.value = null
		return
	}
	try {
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
	} catch {
		chartCumData.value = null
	}
}
const liveCumData = computed(() => chartCumData.value)

/* Versiones (lateral) */
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

/* Guardar (12/18) */
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
		const { data } = await api.put(API + '/forecast/series-table', body)
		const saved = Number(data?.changed_count ?? data?.saved ?? 0)
		originalForecast.value = forecast.value.slice()
		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: `${saved} Änderung${saved === 1 ? '' : 'en'} gespeichert`,
			life: 2200,
		})
		await Promise.all([loadSeriesTable(), loadSeriesChart()])
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Speichern fehlgeschlagen',
			life: 2500,
		})
	}
}

/* Guarded filter changes */
const confirmVisible = ref(false)
const pendingChange = ref(null)
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
	} else {
		applyChange(kind, value)
	}
}
async function saveAndApply() {
	try {
		await saveForecast()
	} finally {
		confirmVisible.value = false
		if (pendingChange.value) {
			applyChange(pendingChange.value.kind, pendingChange.value.value)
		}
		pendingChange.value = null
	}
}
function discardAndApply() {
	for (let i = 0; i < forecast.value.length; i++) {
		forecast.value[i] = Number(originalForecast.value[i] ?? 0)
	}
	confirmVisible.value = false
	if (pendingChange.value) {
		applyChange(pendingChange.value.kind, pendingChange.value.value)
	}
	pendingChange.value = null
}

/* Utils */
function clearSeries() {
	const mlist = computeTableMonthsFiscal()
	months.value = mlist
	sales.value = Array(mlist.length).fill(0)
	budget.value = Array(mlist.length).fill(0)
	forecast.value = Array(mlist.length).fill(0)
	orders.value = Array(mlist.length).fill(0)
	originalForecast.value = forecast.value.slice()
	viewportStart.value = Math.min(
		computeInitialViewportStart(),
		Math.max(0, mlist.length - viewportSize),
	)
}

/* Títulos por tarjeta (usa prop :title) */
function cardTitle(item) {
	if (item.type === 'filters') return 'Filter'
	if (item.type === 'chart') return `Diagramm (WJ ${fyTitleSpan()})`
	if (item.type === 'chart-versions') return `Versionen (${monthTitleDE()})`
	if (item.type === 'title' || item.type === 'table') return '' // sin header
	return ''
}

/* Next (secondary) */
function handleNext() {
	const list = secondaryOptions.value
	if (!list?.length) return
	const idx = list.findIndex((o) => o.value === secondaryId.value)
	const n = (idx >= 0 ? idx + 1 : 0) % list.length
	guardedChange('secondary', list[n].value)
}

/* Reactividad */
watch(secondaryId, async () => {
	await Promise.all([loadSeriesTable(), loadSeriesChart()])
})

/* Mount */
onMounted(() => {
	loadMaster()
})

/* Grid */
const layout = ref([
	{ i: 'filters', x: 0, y: 0, w: 2, h: 47, static: true, type: 'filters' },
	{ i: 'title', x: 2, y: 0, w: 10, h: 4, static: true, type: 'title' },
	{ i: 'chart-main', x: 2, y: 4, w: 7, h: 26, static: true, type: 'chart' },
	{ i: 'chart-versions', x: 9, y: 4, w: 3, h: 26, static: true, type: 'chart-versions' },
	{ i: 'table', x: 2, y: 30, w: 10, h: 17, static: true, type: 'table' },
])

/* IDs para charts */
const currentClientId = computed(() =>
	mode.value === 'client' ? primaryId.value : secondaryId.value,
)
const currentPcId = computed(() => (mode.value === 'client' ? secondaryId.value : primaryId.value))

/* Ref tabla para scroll programático */
const tableRef = ref(null)
</script>

<style scoped>
.forecast-wrapper {
	height: 100vh;
	width: 100%;
	overflow: hidden;
}

/* ocultar header en title/table */
.no-strip :deep(.card-header),
.no-strip :deep(.glass-title),
.no-strip :deep(.p-card-header) {
	display: none !important;
}

.nav-bar {
	padding: 2px 6px;
}
.range-label {
	font-weight: 600;
	letter-spacing: 0.2px;
}
</style>
