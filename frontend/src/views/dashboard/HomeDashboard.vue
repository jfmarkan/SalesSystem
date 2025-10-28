<template>
	<div class="dashboard-grid container-fluid">
		<div
			v-for="item in layout"
			:key="item.i"
			class="grid-item"
			:style="{
				'--col-start': item.x + 1,
				'--col-span': item.w,
				'--row-start': item.y + 1,
				'--row-span': item.h,
			}"
		>
			<GlassCard :title="''" :divider="false" class="fill">
				<template #actions v-if="item.type === 'chart' || item.type === 'table'">
					<div class="unit-toggle">
						<button
							:class="['u-btn', unit === 'VKEH' && 'active']"
							@click="changeUnit('VKEH')"
						>
							VK-EH
						</button>
						<button
							:class="['u-btn', unit === 'M3' && 'active']"
							@click="changeUnit('M3')"
						>
							m³
						</button>
						<button
							:class="['u-btn', unit === 'EUR' && 'active']"
							@click="changeUnit('EUR')"
						>
							€
						</button>
					</div>
				</template>

				<component
					:is="getWidgetComponent(item.type)"
					v-bind="getPropsForType(item)"
					v-on="getListenersForType(item.type)"
					class="grid-widget"
				/>
			</GlassCard>
		</div>

		<div v-if="errorMsg" class="err">{{ errorMsg }}</div>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/plugins/axios'

import GlassCard from '@/components/ui/GlassCard.vue'
import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/Calendar.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'
import ExtraQuotaCard from '@/components/widgets/ExtraQuotaCard.vue'

const unit = ref('VKEH')
const period = ref(new Date().toISOString().slice(0, 7))
const fiscalYear = computed(
	() => Number((period.value || '').slice(0, 4)) || new Date().getFullYear(),
)

const me = ref({ id: null, name: '' })
const kpiItems = ref([])
const chartCodes = ref([])
const chartSeries = ref([])
const tableRowsRaw = ref([])
const tableTotalsRaw = ref({})
const calendarEvents = ref([])
const extraQuota = ref({ title: 'Zusatzquoten', target: 0, achieved: 0, items: [], mix: null })
const selectedPcId = ref(null)
const pcDetail = ref(null)
const errorMsg = ref('')
const loading = ref(false)

async function fetchDashboard() {
	loading.value = true
	errorMsg.value = ''
	try {
		const [dash, extra] = await Promise.all([
			api.get('/api/dashboard', { params: { unit: unit.value, period: period.value } }),
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
		tableTotalsRaw.value = d?.table?.totals ?? {
			ist: 0,
			prognose: 0,
			budget: 0,
			unit: unit.value,
		}
		calendarEvents.value = d?.calendar?.events ?? []
		const ex = extra.data || {}
		extraQuota.value = {
			title: ex.title ?? 'Zusatzquoten',
			target: Number(ex.target ?? 0),
			achieved: Number(ex.achieved ?? 0),
			items: Array.isArray(ex.items) ? ex.items : [],
			mix: ex.mix ?? null,
		}
	} catch (e) {
		console.error(e)
		errorMsg.value = 'Fehler beim Laden.'
	} finally {
		loading.value = false
	}
}
onMounted(fetchDashboard)
watch(unit, fetchDashboard)

function changeUnit(next) {
	if (next !== unit.value) unit.value = next
}

const kpisById = computed(() => {
	const by = Object.fromEntries(kpiItems.value.map((i) => [i.id, i]))
	return {
		ist_vs_prognose: by['ist_vs_prognose'] || { label: 'Ist vs Forecast', value: 0, unit: '%' },
		ist_vs_budget: by['ist_vs_budget'] || { label: 'Ist vs Budget', value: 0, unit: '%' },
		diff_ist_budget_m3: by['diff_ist_budget_m3'] || {
			label: 'Differenz Ist – Budget',
			value: 0,
			unit: 'M3',
		},
		umsatz_eur: by['umsatz_eur'] || { label: 'Gesamtumsatz', value: 0, unit: 'EUR' },
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

/* MISMAS DIMENSIONES QUE ANTES (x,y,w,h) */
const layout = ref([
	{ i: '0', x: 0, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'ist_vs_prognose' },
	{ i: '1', x: 2, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'ist_vs_budget' },
	{ i: '2', x: 4, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'diff_ist_budget_m3' },
	{ i: '3', x: 6, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'umsatz_eur' },
	{ i: '4', x: 8, y: 0, w: 4, h: 14, type: 'calendar' },
	{ i: '7', x: 0, y: 4, w: 5, h: 17, type: 'chart' },
	{ i: '8', x: 5, y: 4, w: 3, h: 17, type: 'table' },
	{ i: '9', x: 8, y: 14, w: 4, h: 7, type: 'extra' },
])

function getWidgetComponent(type) {
	return (
		{
			kpi: KpiCard,
			calendar: CalendarCard,
			chart: ChartCard,
			table: ProfitCentersTable,
			extra: ExtraQuotaCard,
		}[type] || null
	)
}
function getPropsForType(item) {
	if (item.type === 'kpi')
		return { modelValue: item.kpiId, kpis: kpisById.value, unit: unit.value }
	if (item.type === 'chart')
		return { labels: radarLabels.value, series: radarSeries.value, unit: unit.value }
	if (item.type === 'table')
		return {
			rows: tableRows.value,
			totals: tableTotals.value,
			unit: unit.value,
			selectedId: selectedPcId.value,
		}
	if (item.type === 'extra')
		return {
			title: extraQuota.value.title,
			unit: unit.value,
			target: extraQuota.value.target,
			achieved: extraQuota.value.achieved,
			items: extraQuota.value.items,
			mix: extraQuota.value.mix,
			scope: 'self',
			currentUserId: me.value.id,
			currentUserName: me.value.name,
			pcDetail: pcDetail.value,
		}
	return {}
}
function getListenersForType(type) {
	if (type === 'calendar') return { 'update-action': onUpdateAction }
	if (type === 'table') return { 'row-select': onSelectPc }
	return {}
}

async function onUpdateAction() {
	/* noop visual */
}
async function onSelectPc() {
	/* noop visual */
}
</script>

<style scoped>
/* Grid con mismas reglas que vue3-grid-layout: 12 cols, row 30px, gap 10px */
.dashboard-grid {
	--gap: 10px;
	--row-h: 30px;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	grid-auto-rows: var(--row-h);
	grid-auto-flow: dense;
	gap: var(--gap);
	width: 100%;
}

/* Posicionamiento exacto por x,y,w,h */
.grid-item {
	grid-column: var(--col-start) / span var(--col-span);
	grid-row: var(--row-start) / span var(--row-span);
	min-width: 0;
	min-height: 0;
}

/* La tarjeta llena la celda y su contenido estira */
.fill {
	height: 100%;
	display: flex;
	flex-direction: column;
}
.fill :deep(.card-content) {
	flex: 1;
	min-height: 0;
}

/* Widget ocupa todo */
.grid-widget {
	height: 100%;
	width: 100%;
	background: transparent;
	border: 0;
	border-radius: 0;
}

/* Errores */
.err {
	grid-column: 1 / -1;
	margin-top: 8px;
	padding: 6px 10px;
	border-radius: 8px;
	background: rgba(239, 68, 68, 0.08);
	color: #7f1d1d;
	border: 1px solid rgba(239, 68, 68, 0.35);
}

/* Toggle unidad */
.unit-toggle {
	display: flex;
	gap: 6px;
	padding: 2px;
	border-radius: 8px;
	background: var(--input-bg);
	border: 1px solid var(--input-border);
}
.u-btn {
	border: 0;
	background: transparent;
	padding: 0.25rem 0.5rem;
	font-size: 0.8rem;
	cursor: pointer;
	border-radius: 6px;
	color: var(--text);
}
.u-btn.active {
	background: var(--primary);
	color: #001018;
	font-weight: 700;
}

/* Stack en móviles (opcional) */
@media (max-width: 992px) {
	.grid-item {
		grid-column: 1 / -1;
		grid-row: auto;
	}
}
</style>
