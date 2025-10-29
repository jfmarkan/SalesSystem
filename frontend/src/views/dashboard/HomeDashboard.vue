<!-- src/views/Dashboard.vue -->
<template>
	<div class="container-fluid dashboard-grid">
		<!-- IZQ 8/12 -->
		<section class="col-8 left-col">
			<!-- KPIs 4x (3/12) alto fijo -->
			<div class="row12 kpi-row">
				<GlassCard class="kpi">
					<KpiCard modelValue="ist_vs_prognose" :kpis="kpisById" :unit="unit" />
				</GlassCard>
				<GlassCard class="kpi">
					<KpiCard modelValue="ist_vs_budget" :kpis="kpisById" :unit="unit" />
				</GlassCard>
				<GlassCard class="kpi">
					<KpiCard modelValue="diff_ist_budget_m3" :kpis="kpisById" :unit="unit" />
				</GlassCard>
				<GlassCard class="kpi">
					<KpiCard modelValue="umsatz_eur" :kpis="kpisById" :unit="unit" />
				</GlassCard>
			</div>

			<!-- Chart 8/12 + Table 4/12 -->
			<div class="row12 main-row">
				<GlassCard class="fill col-8">
					<template #actions>
						<SelectButton
							:modelValue="unit"
							:options="unitOptions"
							@update:modelValue="changeUnit"
							:allowEmpty="false"
							class="unit-toggle"
						/>
					</template>
					<Skeleton v-if="loading" class="skel" borderRadius="12px" />
					<EmptyState
						v-else-if="!radarLabels.length"
						icon="pi pi-chart-line"
						text="Sin series"
					/>
					<ChartCard
						v-else
						class="widget"
						:labels="radarLabels"
						:series="radarSeries"
						:unit="unit"
					/>
				</GlassCard>

				<GlassCard class="fill col-4">
					<template #actions>
						<i class="pi pi-table" />
						<SelectButton
							:modelValue="unit"
							:options="unitOptions"
							@update:modelValue="changeUnit"
							:allowEmpty="false"
							class="unit-toggle"
						/>
					</template>
					<Skeleton v-if="loading" class="skel" borderRadius="12px" />
					<EmptyState v-else-if="!tableRows.length" icon="pi pi-table" text="Sin filas" />
					<ProfitCentersTable
						v-else
						class="widget"
						:rows="tableRows"
						:totals="tableTotals"
						:unit="unit"
						:selectedId="selectedPcId"
						@row-select="onSelectPc"
					/>
				</GlassCard>
			</div>
		</section>

		<!-- DER 4/12 -->
		<section class="col-4 right-col">
			<GlassCard class="fill" :divider="false">
				<div class="widget no-header">
					<Skeleton v-if="loading" class="skel" borderRadius="12px" />
					<EmptyState
						v-else-if="!calendarEvents.length"
						icon="pi pi-calendar"
						text="Sin eventos"
					/>
					<CalendarCard v-else class="widget" @update-action="onUpdateAction" />
				</div>
			</GlassCard>

			<GlassCard class="fill" :divider="false">
				<div class="widget no-header">
					<Skeleton v-if="loading" class="skel" borderRadius="12px" />
					<EmptyState
						v-else-if="!extraQuota.items.length"
						icon="pi pi-inbox"
						text="Sin datos"
					/>
				</div>
				<ExtraQuotaCard
					v-if="extraQuota.items.length"
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
				/>
			</GlassCard>
		</section>

		<div v-if="errorMsg" class="err" aria-live="polite">{{ errorMsg }}</div>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, watch, defineComponent } from 'vue'
import api from '@/plugins/axios'

import SelectButton from 'primevue/selectbutton'
import Skeleton from 'primevue/skeleton'

import GlassCard from '@/components/ui/GlassCard.vue'
import KpiCard from '@/components/widgets/KpiCard.vue'
import CalendarCard from '@/components/widgets/Calendar.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import ProfitCentersTable from '@/components/widgets/ProfitCentersTable.vue'
import ExtraQuotaCard from '@/components/widgets/ExtraQuotaCard.vue'

const unit = ref('VKEH')
const unitOptions = ['VKEH', 'M3', 'EUR']
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

async function onUpdateAction() {}
async function onSelectPc() {}

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
/* ====== 12 cols. Paddings y altura. Sin solapamientos. ====== */
.dashboard-grid {
	/* Paddings: elige valores. Mantengo 16px vertical y 16px horizontal por defecto */
	--pad-y: 16px; /* usa .25rem o .5rem si prefieres */
	--pad-x: 16px; /* usa .5rem o 1rem si prefieres */
	--gap: 16px; /* separación uniforme */
	--navbar-h: var(--navbar-h, 64px);

	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	padding: var(--pad-y) var(--pad-x);

	/* clave: la página puede crecer si el contenido excede */
	min-height: calc(100svh - var(--navbar-h) - (var(--pad-y) * 2));
	box-sizing: border-box;
}

/* columnas maestras */
.col-8 {
	grid-column: span 8;
	min-width: 0;
}
.col-4 {
	grid-column: span 4;
	min-width: 0;
}

/* IZQ: KPIs fijas + zona flexible que NO solapa */
.left-col {
	display: grid;
	grid-template-rows: var(--kpi-h, 112px) auto; /* auto permite crecer */
	gap: var(--gap);
	min-height: 0;
}

/* KPIs 4x 3/12 */
.kpi-row {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	min-height: 0;
}
.kpi-row > .kpi {
	grid-column: span 3;
	height: 100%;
	min-height: 0;
}

/* Fila principal: chart 8/12 y tabla 4/12. Sin alturas forzadas */
.main-row {
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	min-height: 0;
}
.main-row .col-8 {
	grid-column: span 8;
	min-height: 0;
}
.main-row .col-4 {
	grid-column: span 4;
	min-height: 0;
}

/* DERECHA: dos tarjetas apiladas. Dejan crecer la página si hace falta */
.right-col {
	display: grid;
	grid-auto-rows: minmax(200px, 1fr);
	gap: var(--gap);
	min-height: 0;
}

/* ====== Cards ====== */
/* no animación. no forzar fondos para respetar main.scss */
.fill,
.kpi {
	border-radius: 12px;
	border: 1px solid var(--card-border, color-mix(in oklab, #000, transparent 85%));
}
.fill {
	display: flex;
	flex-direction: column;
	min-height: 0;
}
.fill :deep(.card-header) {
	padding-top: 6px;
	padding-bottom: 6px;
}
.fill :deep(.card-content) {
	flex: 1;
	min-height: 0;
	display: flex;
	flex-direction: column;
}

/* calendario y extra sin header */
.no-header {
	height: 100%;
}

/* widget ocupa el espacio disponible dentro de la card */
.widget {
	width: 100%;
	height: 100%;
	min-height: 0;
}

/* SelectButton pill */
.unit-toggle :deep(.p-button) {
	background: transparent;
	border: 0;
}
.unit-toggle :deep(.p-button.p-highlight) {
	font-weight: 700;
}
.unit-toggle :deep(.p-buttonset) {
	padding: 2px;
	border-radius: 999px;
}

/* Empty, Skeleton, Error */
.skel {
	height: 100%;
	border-radius: 12px;
}
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

/* ====== Responsive ====== */
@media (max-width: 1199px) {
	.col-8,
	.col-4 {
		grid-column: 1 / -1;
	}
	.kpi-row > .kpi {
		grid-column: span 6;
	}
	.main-row .col-8,
	.main-row .col-4 {
		grid-column: span 12;
	}
}
@media (max-width: 700px) {
	.kpi-row > .kpi {
		grid-column: span 12;
	}
}
</style>
