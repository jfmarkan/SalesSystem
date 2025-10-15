<template>
	<div class="dash-wrapper">
		<grid-layout
			v-model:layout="layout"
			:col-num="12"
			:row-height="30"
			:is-draggable="isEditable"
			:is-resizable="isEditable"
			:margin="[10, 10]"
			:use-css-transforms="true"
		>
			<grid-item
				v-for="item in layout"
				:key="item.i"
				:x="item.x"
				:y="item.y"
				:w="item.w"
				:h="item.h"
				:i="item.i"
			>
				<GlassCard
					:title="item.type === 'kpi' || item.type === 'extra' ? '' : getTitle(item)"
				>
					<template #header-extra v-if="item.type === 'chart' || item.type === 'table'">
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
						v-if="getWidgetComponent(item.type)"
						:is="getWidgetComponent(item.type)"
						v-bind="getPropsForType(item)"
						class="grid-widget"
						v-on="getListenersForType(item.type)"
					/>
					<div v-else class="grid-placeholder">Widget {{ item.i }}</div>
				</GlassCard>
			</grid-item>
		</grid-layout>

		<div v-if="errorMsg" class="err">{{ errorMsg }}</div>
	</div>
</template>

<script setup>
// UI in German; code in English.
import { ref, computed, onMounted, watch } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import api from '@/plugins/axios'

import GlassCard from '@/components/ui/GlassCard.vue'
import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/Calendar.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'
import ExtraQuotaCard from '@/components/widgets/ExtraQuotaCard.vue'

const isEditable = ref(false)
const unit = ref('VKEH')
const period = ref(new Date().toISOString().slice(0, 7)) // YYYY-MM
const fiscalYear = computed(
	() => Number((period.value || '').slice(0, 4)) || new Date().getFullYear(),
)

// backend state
const me = ref({ id: null, name: '' })
const kpiItems = ref([])
const chartCodes = ref([])
const chartSeries = ref([])
const tableRowsRaw = ref([])
const tableTotalsRaw = ref({})
const calendarEvents = ref([])
const extraQuota = ref({ title: 'Zusatzquoten', target: 0, achieved: 0, items: [], mix: null })

// selection
const selectedPcId = ref(null)
const pcDetail = ref(null)

const loading = ref(false)
const errorMsg = ref('')

// helpers
function toDate(val) {
	if (!val) return null
	if (val instanceof Date) return val
	if (typeof val === 'string' && /^\d{4}-\d{2}-\d{2}/.test(val))
		return new Date(val + 'T00:00:00')
	return new Date(val)
}

// data loader
async function fetchDashboard() {
	loading.value = true
	errorMsg.value = ''
	try {
		const paramsDash = { unit: unit.value, period: period.value }
		const paramsExtra = { unit: unit.value, fiscal_year: fiscalYear.value }

		const [dash, extra] = await Promise.all([
			api.get('/api/dashboard', { params: paramsDash }),
			api.get('/api/extra/portfolio', { params: paramsExtra }),
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
		console.error('Dashboard load failed', e)
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

// KPI map
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

// chart & table
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

// layout
const layout = ref([
	{ i: '0', x: 0, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'ist_vs_prognose' },
	{ i: '1', x: 2, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'ist_vs_budget' },
	{ i: '2', x: 4, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'diff_ist_budget_m3' },
	{ i: '3', x: 6, y: 0, w: 2, h: 4, type: 'kpi', kpiId: 'umsatz_eur' },
	{ i: '4', x: 8, y: 0, w: 4, h: 14, type: 'calendar' },
	{ i: '7', x: 0, y: 4, w: 5, h: 17, type: 'chart' },
	{ i: '8', x: 5, y: 4, w: 3, h: 17, type: 'table' },
	{ i: '9', x: 8, y: 12, w: 4, h: 7, type: 'extra' },
])

// titles (UI German)
function displayUnit(u) {
	if (!u) return ''
	const U = String(u).toUpperCase()
	if (U === 'M3') return 'm³'
	if (U === 'EUR') return '€'
	if (U === 'VKEH') return 'VK-EH'
	if (u === '%') return '%'
	return u
}
function getTitle(item) {
	if (item.type === 'kpi') {
		const k = kpisById.value[item.kpiId] ?? { label: 'KPI', unit: '' }
		const u = k.unit ? ` (${displayUnit(k.unit)})` : ''
		return `${k.label}${u}`
	}
	return (
		{ calendar: 'Kalender', chart: 'Diagramm', table: 'Profit-Center', extra: 'Zusatzquoten' }[
			item.type
		] || 'Widget'
	)
}

// registry
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

// props per widget
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

	if (item.type === 'extra') {
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
	}
	return {}
}

// listeners
function getListenersForType(type) {
	if (type === 'calendar') return { 'update-action': onUpdateAction }
	if (type === 'table') return { 'row-select': onSelectPc }
	return {}
}

// persist + optimistic update (calendar)
async function onUpdateAction({ id, due_date, status }) {
	const list = calendarEvents.value || []
	const idx = list.findIndex((x) => String(x.id) === String(id))
	if (idx !== -1) {
		const cur = { ...list[idx] }
		if (due_date) cur.due_date = due_date
		if (status) {
			cur.status = status
			cur.is_completed = status === 'completed'
		}
		list.splice(idx, 1, cur)
		calendarEvents.value = [...list]
	}
	try {
		await api.patch(`/api/action-items/${id}`, {
			due_date,
			status,
			is_completed: status === 'completed',
		})
	} catch (e) {
		console.error('Update failed', e)
		errorMsg.value = 'Änderung konnte nicht gespeichert werden.'
	}
}

// select PC -> load portfolio indicator
async function onSelectPc(row) {
	selectedPcId.value = row?.pcId ?? null
	pcDetail.value = null
	if (!selectedPcId.value) return
	try {
		const { data } = await api.get(
			`/api/profit-centers/${selectedPcId.value}/extra-portfolio`,
			{
				params: { unit: unit.value, fiscal_year: fiscalYear.value },
			},
		)
		pcDetail.value = {
			pcId: selectedPcId.value,
			pcName: row?.pcName ?? String(selectedPcId.value),
			allocated: Number(data?.allocated ?? 0),
			won: Number(data?.won ?? 0),
			lost: Number(data?.lost ?? 0),
			open: Number(data?.open ?? 0),
		}
	} catch (e) {
		console.error('PC detail load failed', e)
		pcDetail.value = {
			pcId: selectedPcId.value,
			pcName: row?.pcName ?? String(selectedPcId.value),
			allocated: 0,
			won: 0,
			lost: 0,
			open: 0,
		}
	}
}
</script>

<style scoped>
.dash-wrapper {
	width: 100%;
}
.grid-widget {
	height: 100%;
	width: 100%;
	box-sizing: border-box;
	background: transparent;
	border: 0;
	border-radius: 0;
}
.grid-placeholder {
	height: 100%;
	width: 100%;
	background: transparent;
	color: #111827;
	display: flex;
	align-items: center;
	justify-content: center;
}
.err {
	margin-top: 8px;
	padding: 6px 10px;
	border-radius: 8px;
	background: rgba(239, 68, 68, 0.08);
	color: #7f1d1d;
	border: 1px solid rgba(239, 68, 68, 0.35);
}
.unit-toggle {
	display: flex;
	gap: 6px;
	background: rgba(255, 255, 255, 0.35);
	border: 1px solid rgba(0, 0, 0, 0.08);
	border-radius: 8px;
	padding: 2px;
}
.u-btn {
	border: 0;
	background: transparent;
	padding: 0.25rem 0.5rem;
	font-size: 0.8rem;
	cursor: pointer;
	border-radius: 6px;
}
.u-btn.active {
	background: rgba(31, 86, 115, 0.8);
	color: #fff;
	font-weight: 700;
}
</style>
