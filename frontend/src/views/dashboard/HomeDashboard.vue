<template>
	<div class="container-fluid dashboard-grid">
		<!-- GLOBAL LOADER -->
		<div v-if="loading" class="global-loader">
			<div class="glass"></div>
			<div class="loader-inner">
				<div class="balls">
					<span class="ball ball-blue"></span>
					<span class="ball ball-green"></span>
					<span class="ball ball-red"></span>
				</div>
				<div class="loader-text">Dashboard wird geladen…</div>
			</div>
		</div>

		<!-- IZQ 8/12 -->
		<section class="col-8 left-col">
			<!-- HEADER CARD -->
			<Card class="header-card">
				<template #content>
					<div class="header-inner">
						<!-- IZQUIERDA: títulos -->
						<div class="header-title-block">
							<div class="eyebrow">Sales Dashboard</div>
							<div class="title-main">Performanceübersicht</div>
							<div class="title-sub">{{ periodLabel }}</div>
						</div>

						<!-- DERECHA: periodo -> granularidad -> unidad -->
						<div class="header-controls">
							<!-- 1) Selector de periodo / año fiscal -->
							<div class="period-nav">
								<Button
									icon="pi pi-chevron-left"
									text
									rounded
									@click="shiftPeriod(-1)"
									aria-label="Voriger Zeitraum"
								/>
								<span class="period-pill">
									<i class="pi pi-calendar mr-2" /> {{ periodLabelShort }}
								</span>
								<Button
									icon="pi pi-chevron-right"
									text
									rounded
									@click="shiftPeriod(1)"
									aria-label="Nächster Zeitraum"
								/>
							</div>

							<!-- 2) Granularidad -->
							<div class="granularity-toggle">
								<SelectButton
									:modelValue="granularity"
									:options="granularityOptions"
									optionLabel="label"
									optionValue="value"
									:allowEmpty="false"
									@update:modelValue="onChangeGranularity"
								/>
							</div>

							<!-- 3) Unidad -->
							<div class="unit-toggle-wrap">
								<SelectButton
									:modelValue="unit"
									:options="unitOptions"
									:allowEmpty="false"
									@update:modelValue="changeUnit"
								/>
							</div>
						</div>
					</div>
				</template>
			</Card>

			<!-- KPIs -->
			<div class="kpi-row">
				<Card
					class="kpi-card"
					v-for="kpiId in kpiOrder"
					:key="kpiId"
				>
					<template #content>
						<KpiCard :modelValue="kpiId" :kpis="kpisById" :unit="unit" />
					</template>
				</Card>
			</div>

			<!-- Chart + Table -->
			<div class="main-row">
				<!-- Chart -->
				<Card class="col-8 main-card">
					<template #header>
						<div class="main-card-header">
							<span class="main-title">Portfolio · Übersicht</span>
							<span class="main-sub">{{ unitLabel }}</span>
						</div>
					</template>
					<template #content>
						<EmptyState
							v-if="!radarLabels.length && !loading"
							icon="pi pi-chart-line"
							text="Keine Daten"
						/>
						<ChartCard
							v-else
							class="widget chart-widget"
							:labels="radarLabels"
							:series="radarSeries"
							:unit="unit"
						/>
					</template>
				</Card>

				<!-- Table -->
				<Card class="col-4 main-card">
					<template #header>
						<div class="main-card-header">
							<span class="main-title">Profit-Center · Übersicht</span>
							<span class="main-sub">{{ unitLabel }}</span>
						</div>
					</template>
					<template #content>
						<EmptyState v-if="!tableRows.length && !loading" text="Keine Daten" />
						<ProfitCentersTable
							v-else
							class="widget"
							:rows="tableRows"
							:totals="tableTotals"
							:unit="unit"
							:selectedId="selectedPcId"
							@row-select="onSelectPc"
						/>
					</template>
				</Card>
			</div>
		</section>

		<!-- DER 4/12 -->
		<section class="col-4 right-col">
			<!-- Calendar -->
			<Card class="calendar-card">
				<template #content>
					<CalendarCard class="widget" @update-action="onUpdateAction" />
				</template>
			</Card>

			<!-- Extra Quotas -->
			<Card v-if="extraQuota.items.length" class="xq-wrapper">
				<template #content>
					<ExtraQuotaCard
						class="widget"
						:title="extraQuota.title"
						:unit="unit"
						:target="extraQuota.target"
						:achieved="extraQuota.achieved"
						:items="extraQuota.items"
						:mix="extraQuota.mix"
						scope="self"
						:currentUserId="me.id"
						:currentUserName="me.name"
						:pcDetail="pcDetail"
						:maxLegendItems="3"
					/>
				</template>
			</Card>
		</section>
	</div>
</template>

<script setup>
/* 👇 el script es el mismo que ya tenías en la última versión,
   con granularity / period / fiscalYear / fetchDashboard, etc. */
import { ref, computed, onMounted, watch, defineComponent } from 'vue'
import api from '@/plugins/axios'

import Card from 'primevue/card'
import SelectButton from 'primevue/selectbutton'
import Button from 'primevue/button'

import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/CalendarCard.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'
import ExtraQuotaCard from '@/components/widgets/ExtraQuotaCard.vue'

const unit = ref('VKEH')
const unitOptions = ['VKEH', 'M3', 'EUR']
const period = ref(new Date().toISOString().slice(0, 7))

const granularity = ref('month')
const granularityOptions = [
	{ label: 'Monat', value: 'month' },
	{ label: 'Quartal', value: 'quarter' },
	{ label: 'Halbjahr', value: 'semester' },
	{ label: 'Jahr', value: 'year' },
]

const me = ref({ id: null, name: '' })
const kpiItems = ref([])
const chartCodes = ref([])
const chartSeries = ref([])
const tableRowsRaw = ref([])
const tableTotalsRaw = ref({})
const extraQuota = ref({
	title: 'Zusatzquoten',
	target: 0,
	achieved: 0,
	items: [],
	mix: null,
})
const selectedPcId = ref(null)
const pcDetail = ref(null)
const loading = ref(false)

function parsePeriod(p) {
	if (typeof p === 'string' && /^\d{4}-\d{2}$/.test(p)) {
		const [y, m] = p.split('-').map(Number)
		return new Date(y, m - 1, 1)
	}
	return new Date()
}

function fiscalYearStartYear(date) {
	const m = date.getMonth() + 1
	const y = date.getFullYear()
	return m >= 4 ? y : y - 1
}
const fiscalYear = computed(() => fiscalYearStartYear(parsePeriod(period.value)))

function fiscalYearLabel(fyStartYear) {
	const nextYearShort = String(fyStartYear + 1).slice(-2)
	return `WJ${fyStartYear}/${nextYearShort}`
}

const unitLabel = computed(() => {
	const u = String(unit.value || '').toUpperCase()
	if (u === 'M3') return 'm³'
	if (u === 'EUR') return '€'
	return 'VK-EH'
})

const periodLabel = computed(() => {
	const d = parsePeriod(period.value)
	const fy = fiscalYearStartYear(d)
	const fyLbl = fiscalYearLabel(fy)
	const month = d.getMonth() + 1

	if (granularity.value === 'month') {
		const monthName = d.toLocaleString('de-DE', { month: 'long', year: 'numeric' })
		return `${monthName} · ${fyLbl}`
	}

	if (granularity.value === 'quarter') {
		let q
		if (month >= 4 && month <= 6) q = 1
		else if (month >= 7 && month <= 9) q = 2
		else if (month >= 10 && month <= 12) q = 3
		else q = 4
		return `Q${q} · ${fyLbl}`
	}

	if (granularity.value === 'semester') {
		let s
		if (month >= 4 && month <= 9) s = 1
		else s = 2
		return `${s}. Halbjahr · ${fyLbl}`
	}

	return fyLbl
})

const periodLabelShort = computed(() => {
	const d = parsePeriod(period.value)
	const month = d.getMonth() + 1
	const fyLbl = fiscalYearLabel(fiscalYearStartYear(d))

	if (granularity.value === 'month') {
		const monthName = d.toLocaleString('de-DE', { month: 'short' })
		return `${monthName} ${d.getFullYear()} · ${fyLbl}`
	}
	if (granularity.value === 'quarter') {
		let q
		if (month >= 4 && month <= 6) q = 1
		else if (month >= 7 && month <= 9) q = 2
		else if (month >= 10 && month <= 12) q = 3
		else q = 4
		return `Q${q} · ${fyLbl}`
	}
	if (granularity.value === 'semester') {
		let s
		if (month >= 4 && month <= 9) s = 1
		else s = 2
		return `${s}. Hj. · ${fyLbl}`
	}
	return fyLbl
})

function onChangeGranularity(next) {
	if (next && next !== granularity.value) granularity.value = next
}

function shiftPeriod(delta) {
	const d = parsePeriod(period.value)
	const stepMap = { month: 1, quarter: 3, semester: 6, year: 12 }
	const step = stepMap[granularity.value] || 1
	d.setMonth(d.getMonth() + delta * step)
	const y = d.getFullYear()
	const m = String(d.getMonth() + 1).padStart(2, '0')
	period.value = `${y}-${m}`
}

async function fetchDashboard() {
	loading.value = true
	try {
		const [dash, extra] = await Promise.all([
			api.get('/api/dashboard', {
				params: {
					unit: unit.value,
					period: period.value,
					granularity: granularity.value,
				},
			}),
			api.get('/api/extra/portfolio', {
				params: { unit: unit.value, fiscal_year: fiscalYear.value },
			}),
		])

		const d = dash.data || {}
		me.value = d.me || { id: null, name: '' }
		kpiItems.value = d?.kpis?.items ?? []
		chartCodes.value = d?.chart?.codes ?? d?.chart?.labels ?? []
		chartSeries.value = d?.chart?.series ?? []
		tableRowsRaw.value = d?.table?.rows ?? []
		tableTotalsRaw.value =
			d?.table?.totals ?? {
				ist: 0,
				prognose: 0,
				budget: 0,
				unit: unit.value,
			}

		const ex = extra.data || {}
		extraQuota.value = {
			title: ex.title ?? 'Zusatzquoten',
			target: Number(ex.target ?? 0),
			achieved: Number(ex.achieved ?? 0),
			items: Array.isArray(ex.items) ? ex.items : [],
			mix: ex.mix ?? null,
		}
	} catch (e) {
		console.error('Dashboard load error', e)
	} finally {
		loading.value = false
	}
}

onMounted(fetchDashboard)
watch(unit, fetchDashboard)
watch(period, fetchDashboard)
watch(granularity, fetchDashboard)

function changeUnit(next) {
	if (next !== unit.value) unit.value = next
}

/* KPIs principales que mostramos en fila */
const kpiOrder = ['ist_vs_prognose', 'ist_vs_budget', 'deviations_resolved_pct', 'umsatz_eur']

const kpisById = computed(() => {
	const by = Object.fromEntries(kpiItems.value.map((i) => [i.id, i]))
	return {
		ist_vs_prognose:
			by['ist_vs_prognose'] || ({ label: 'Ist vs Forecast', value: 0, unit: '%' }),
		ist_vs_budget:
			by['ist_vs_budget'] || ({ label: 'Ist vs Budget', value: 0, unit: '%' }),
		deviations_resolved_pct:
			by['deviations_resolved_pct'] || ({
				label: 'Abweichungen gelöst',
				value: 0,
				unit: '%',
			}),
		umsatz_eur: by['umsatz_eur'] || ({ label: 'Gesamtumsatz', value: 0, unit: 'EUR' }),
	}
})

const radarLabels = computed(() => chartCodes.value)
const radarSeries = computed(() => chartSeries.value)

const tableRows = computed(() =>
	(tableRowsRaw.value || []).map((r) => ({
		pcId: r.pc_code,
		pcName: r.pc_name,
		sales: r.ist ?? 0,
		forecast: r.prognose ?? 0,
		budget: r.budget ?? 0,
	})),
)

const tableTotals = computed(() => ({
	sales: tableTotalsRaw.value?.ist ?? 0,
	forecast: tableTotalsRaw.value?.prognose ?? 0,
	budget: tableTotalsRaw.value?.budget ?? 0,
}))

async function onUpdateAction() {}
async function onSelectPc() {}

const EmptyState = defineComponent({
	name: 'EmptyState',
	props: {
		icon: { type: String, default: 'pi pi-inbox' },
		text: { type: String, default: 'Keine Daten' },
	},
	template: `<div class="empt"><i :class="icon"></i><p>{{ text }}</p></div>`,
})
</script>

<style scoped>
.dashboard-grid {
	--gap: 16px;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	height: calc(100vh - 86px);
	min-height: 0;
	box-sizing: border-box;
	overflow: hidden;
}

/* Columnas principales */
.col-8 {
	grid-column: span 8;
	min-width: 0;
}

.col-4 {
	grid-column: span 4;
	min-width: 0;
}

/* LEFT COL */
.left-col {
	display: grid;
	grid-template-rows: auto auto minmax(0, 1fr);
	gap: var(--gap);
	min-height: 0;
	height: 100%;
}

/* HEADER */
.header-card {
	grid-column: 1 / -1;
}

.header-inner {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 1rem;
}

.header-title-block {
	display: flex;
	flex-direction: column;
	gap: 0.15rem;
	min-width: 0;
}

.eyebrow {
	font-size: 0.75rem;
	text-transform: uppercase;
	letter-spacing: 0.04em;
	color: #94a3b8;
}

.title-main {
	font-size: 1.1rem;
	font-weight: 700;
	color: #0f172a;
}

.title-sub {
	font-size: 0.85rem;
	color: #64748b;
}

/* Controles todos a la derecha en una fila */
.header-controls {
	display: flex;
	align-items: center;
	justify-content: flex-end;
	gap: 0.5rem;
	flex-wrap: nowrap;
}

.period-nav {
	display: inline-flex;
	align-items: center;
	gap: 0.35rem;
	white-space: nowrap;
}

.period-pill {
	display: inline-flex;
	align-items: center;
	gap: 0.35rem;
	padding: 4px 10px;
	border-radius: 999px;
	background: rgba(15, 23, 42, 0.06);
	font-size: 0.8rem;
	font-weight: 600;
	color: #0f172a;
	white-space: nowrap;
}

.granularity-toggle :deep(.p-selectbutton),
.unit-toggle-wrap :deep(.p-selectbutton) {
	font-size: 0.8rem;
}

/* KPI ROW */
.kpi-row {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
}

.kpi-card {
	grid-column: span 3;
	min-height: 110px;
	display: flex;
	flex-direction: column;
}

/* MAIN ROW */
.main-row {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	align-items: stretch;
	min-height: 0;
	height: 100%;
}

.main-row .col-8 {
	grid-column: span 6;
}

.main-row .col-4 {
	grid-column: span 6;
}

.main-card {
	min-height: 0;
	display: flex;
	flex-direction: column;
	height: 100%;
}

.main-card :deep(.p-card-body) {
	display: flex;
	flex-direction: column;
	min-height: 0;
	padding: 8px 10px !important;
}

.main-card-header {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 0.5rem;
	padding: 4px 6px 0;
}

.main-title {
	font-size: 0.9rem;
	font-weight: 600;
	color: #0f172a;
}

.main-sub {
	font-size: 0.8rem;
	color: #64748b;
}

.main-card :deep(.widget) {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.chart-widget {
	max-height: 100%;
	padding: 1rem 3rem;
}

:deep(.chart-area) {
	flex: 1;
	height: 100% !important;
	min-height: 0 !important;
	display: flex;
}

/* RIGHT COL */
.right-col {
	display: flex;
	flex-direction: column;
	gap: var(--gap);
	min-height: 0;
	height: 100%;
}

.calendar-card {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	flex-direction: column;
}

.calendar-card :deep(.p-card-body) {
	display: flex;
	flex-direction: column;
	min-height: 0;
	padding: 8px 10px !important;
}

.calendar-card :deep(.widget) {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.xq-wrapper {
	flex: 0 0 auto;
	max-height: 260px;
	display: flex;
	flex-direction: column;
	overflow: hidden;
}
.xq-wrapper :deep(.p-card-body) {
	padding: 8px 10px !important;
	display: flex;
	flex-direction: column;
	min-height: 0;
}
.xq-wrapper :deep(.widget) {
	flex: 1;
	min-height: 0;
	overflow: auto;
}

/* LOADER */
.global-loader {
	position: fixed;
	inset: 0;
	z-index: 999;
	display: grid;
	place-items: center;
}

.global-loader .glass {
	position: absolute;
	inset: 0;
	backdrop-filter: blur(6px);
	-webkit-backdrop-filter: blur(6px);
	background-color: rgba(15, 23, 42, 0.25);
	z-index: 1;
}

.loader-inner {
	position: relative;
	z-index: 2;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 0.5rem;
	background: transparent;
	box-shadow: none;
	padding: 0;
}

.balls {
	display: flex;
	align-items: flex-end;
	gap: 0.75rem;
}

.ball {
	width: 35px;
	height: 35px;
	border-radius: 50%;
	animation: bounce 0.8s infinite ease-in-out;
}

.ball-blue {
	background: #3b82f6;
	animation-delay: 0s;
}
.ball-green {
	background: #22c55e;
	animation-delay: 0.15s;
}
.ball-red {
	background: #ef4444;
	animation-delay: 0.3s;
}

@keyframes bounce {
	0%,
	100% {
		transform: translateY(0);
	}
	50% {
		transform: translateY(-16px);
	}
}

.loader-text {
	margin-top: 0.25rem;
	font-weight: 600;
	font-size: 0.9rem;
	color: #e5e7eb;
	text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

/* EMPTY */
.empt {
	height: 100%;
	display: grid;
	place-items: center;
	color: var(--text-weak, #7a8a9a);
	gap: 0.25rem;
	text-align: center;
}

.empt i {
	font-size: 1.3rem;
}

/* RESPONSIVE */
@media (max-width: 1199px) {
	.dashboard-grid {
		height: auto;
		min-height: 100vh;
		overflow: auto;
	}

	.col-8,
	.col-4 {
		grid-column: 1 / -1;
	}

	.left-col {
		grid-template-rows: auto auto auto;
		height: auto;
	}

	.kpi-card {
		grid-column: span 6;
	}

	.main-row .col-8,
	.main-row .col-4 {
		grid-column: span 12;
	}

	.chart-widget {
		max-height: none;
	}

	.right-col {
		height: auto;
	}

	/* permitir que al achicarse todo, los controles salten de línea sin romper */
	.header-inner {
		flex-direction: column;
		align-items: flex-start;
	}
	.header-controls {
		justify-content: flex-start;
		flex-wrap: wrap;
	}
}

@media (max-width: 700px) {
	.kpi-card {
		grid-column: span 12;
	}
}
</style>
