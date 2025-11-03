<template>
	<div class="container-fluid dashboard-grid">
		<!-- GLOBAL LOADER -->
		<div v-if="loading" class="global-loader">
			<div class="glass"></div>
			<div class="spinner-content">
				<ProgressSpinner style="width: 48px; height: 48px" strokeWidth="4" />
				<span class="loader-text">Lädt…</span>
			</div>
		</div>

		<!-- IZQ 8/12 -->
		<section class="col-8 left-col">
			<!-- KPIs 4x (3/12) -->
			<div class="row12 kpi-row">
				<Card class="kpi"
					v-for="kpiId in ['ist_vs_prognose', 'ist_vs_budget', 'diff_ist_budget_m3', 'umsatz_eur']"
					:key="kpiId">
					<template #content>
						<KpiCard :modelValue="kpiId" :kpis="kpisById" :unit="unit" />
					</template>
				</Card>
			</div>

			<!-- Chart + Table -->
			<div class="row12 main-row">
				<!-- Chart -->
				<Card class="fill col-8">
					<template #header>
						<SelectButton :modelValue="unit" :options="unitOptions" @update:modelValue="changeUnit"
							:allowEmpty="false" class="unit-toggle" />
					</template>
					<template #content>
						<EmptyState v-if="!radarLabels.length && !loading" icon="pi pi-chart-line" text="Sin series" />
						<ChartCard v-else class="widget" :labels="radarLabels" :series="radarSeries" :unit="unit" />
					</template>
				</Card>

				<!-- Table -->
				<Card class="col-4">
					<template #header>
						<div class="flex align-items-center gap-2">
							<SelectButton :modelValue="unit" :options="unitOptions" @update:modelValue="changeUnit"
								:allowEmpty="false" class="unit-toggle" />
						</div>
					</template>
					<template #content>
						<EmptyState v-if="!tableRows.length && !loading" text="Sin filas" />
						<ProfitCentersTable v-else class="widget" :rows="tableRows" :totals="tableTotals" :unit="unit"
							:selectedId="selectedPcId" @row-select="onSelectPc" />
					</template>
				</Card>
			</div>
		</section>

		<!-- DER 4/12 -->
		<section class="col-4 right-col">
			<!-- Calendar -->
			<Card>
				<template #content>
					<EmptyState v-if="!calendarEvents.length && !loading" icon="pi pi-calendar" text="Sin eventos" />
					<CalendarCard v-else class="widget" @update-action="onUpdateAction" />
				</template>
			</Card>

			<!-- Extra Quotas -->
			<Card v-if="extraQuota.items.length">
				<template #content>
					<ExtraQuotaCard class="widget" :title="extraQuota.title" :unit="unit" :target="extraQuota.target"
						:achieved="extraQuota.achieved" :items="extraQuota.items" :mix="extraQuota.mix" scope="self"
						:currentUserId="me.id" :currentUserName="me.name" :pcDetail="pcDetail" />
				</template>
			</Card>
		</section>

		<!-- Error -->
		<div v-if="errorMsg" class="err" aria-live="polite">{{ errorMsg }}</div>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, watch, defineComponent } from 'vue'
import api from '@/plugins/axios'

import Card from 'primevue/card'
import SelectButton from 'primevue/selectbutton'
import ProgressSpinner from 'primevue/progressspinner'

import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/CalendarCard.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'
import ExtraQuotaCard from '@/components/widgets/ExtraQuotaCard.vue'

const unit = ref('VKEH')
const unitOptions = ['VKEH', 'M3', 'EUR']
const period = ref(new Date().toISOString().slice(0, 7))
const fiscalYear = computed(() => Number((period.value || '').slice(0, 4)) || new Date().getFullYear())

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

async function onUpdateAction() { }
async function onSelectPc() { }

const EmptyState = defineComponent({
	name: 'EmptyState',
	props: {
		icon: { type: String, default: 'pi pi-inbox' },
		text: { type: String, default: 'Sin datos' },
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
	padding: var(--pad-y) var(--pad-x);
	min-height: 100%;
	box-sizing: border-box;
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

/* KPIs */
.left-col {
	display: grid;
	grid-template-rows: auto auto;
	gap: var(--gap);
	min-height: 0;
}

.kpi-row {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
}

.kpi-row>.kpi {
	grid-column: span 3;
}

/* Main Row (Chart + Table) */
.main-row {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
}

.main-row .col-8 {
	grid-column: span 7;
}

.main-row .col-4 {
	grid-column: span 5;
}

.main-row .col-8>.p-card,
.main-row .col-4>.p-card {
	max-height: 320px;
	overflow: hidden;
}

.main-row .p-card .widget {
	height: auto !important;
	min-height: 0 !important;
}

/* Right Column */
.right-col {
	display: grid;
	grid-auto-rows: auto;
	gap: var(--gap);
}

/* Loader global */
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
	backdrop-filter: blur(12px);
	background-color: rgba(255, 255, 255, 0.4);
	z-index: 1;
}

.spinner-content {
	position: relative;
	z-index: 2;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.loader-text {
	margin-top: 0.75rem;
	font-weight: 600;
	font-size: 16px;
	color: black;
}

/* Empty */
.empt {
	height: 100%;
	display: grid;
	place-items: center;
	color: var(--text-weak, #7a8a9a);
	gap: 0.25rem;
}

.err {
	grid-column: 1/-1;
	padding: 8px 12px;
	border-radius: 8px;
}

@media (max-width: 1199px) {

	.col-8,
	.col-4 {
		grid-column: 1 / -1;
	}

	.kpi-row>.kpi {
		grid-column: span 6;
	}

	.main-row .col-8,
	.main-row .col-4 {
		grid-column: span 12;
	}
}

@media (max-width: 700px) {
	.kpi-row>.kpi {
		grid-column: span 12;
	}
}
</style>
