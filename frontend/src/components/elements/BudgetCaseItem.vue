<template>
	<div class="panel">
		<!-- Checkbox nuevo -->
		<div class="field-checkbox">
			<Checkbox inputId="skip" v-model="skipBudget" :binary="true" />
			<label for="skip" class="ml-2">Kunde - ProfitCenter nicht mehr planen</label>
		</div>

		<!-- Inputs -->
		<div class="cols">
			<div class="col">
				<label for="best" class="lbl best">Best Case</label>
				<InputNumber inputId="best" v-model="bestCase" :min="-100" :max="100" :step="1" suffix=" %"
					placeholder="0 %" fluid class="tall best-input" :disabled="skipBudget" />
			</div>
			<div class="col">
				<label for="worst" class="lbl worst">Worst Case</label>
				<InputNumber inputId="worst" v-model="worstCase" :min="-100" :max="100" :step="1" suffix=" %"
					placeholder="0 %" fluid class="tall worst-input" :disabled="skipBudget" />
			</div>
		</div>

		<!-- Acción -->
		<div class="actions">
			<Button :disabled="!canRun || loading || !canSimulate || skipBudget" label="Simulieren" icon="pi pi-play"
				@click="simulate" />
		</div>

		<div class="sep"></div>

		<!-- Simulador -->
		<div v-if="loading" class="loading">Wird berechnet…</div>
		<template v-else>
			<div v-if="summary" class="flex">
				<div class="summary">
					<div class="kpi"><span class="k">Sales CYTLFM (Basis)</span><span class="v">{{
						fmt(summary.totalSalesYTD) }}</span></div>
					<div class="kpi"><span class="k">Saison-Anteil CYTLFM</span><span class="v">{{ fmt(summary.ytdPct)
							}} %</span></div>
					<div class="kpi"><span class="k">Basis-Prognose</span><span class="v">{{ fmt(summary.baseForecast)
							}}</span></div>
					<div class="kpi"><span class="k">Best Case (gesamt)</span><span class="v good">{{
						fmt(summary.totalBest) }}</span></div>
					<div class="kpi"><span class="k">Worst Case (gesamt)</span><span class="v bad">{{
						fmt(summary.totalWorst) }}</span></div>
				</div>
				<div class="legend">
					<div>CYTLFM = Kalenderjahr bis zum letzten vollen Monat</div>
					<div>BYTLFM = Wirtschaftsjahr bis zum letzten vollen Monat</div>
				</div>
			</div>
			<div v-else class="placeholder"><em>Wähle Client & Profit Center und klicke „Simulieren“.</em></div>
		</template>

		<p v-if="error" class="err">Fehler: {{ error }}</p>
	</div>
</template>

<script setup>
import { ref, computed, watch, onMounted, defineExpose } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Checkbox from 'primevue/checkbox'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const props = defineProps({
	clientGroupNumber: { type: Number, default: null },
	profitCenterCode: { type: Number, default: null }
})

const emit = defineEmits(['dirty-change', 'simulated', 'values-change'])

const toast = useToast()
const loading = ref(false)
const error = ref('')

// Valores
const bestCase = ref(null)
const worstCase = ref(null)
const skipBudget = ref(false)

const origBest = ref(0)
const origWorst = ref(0)
const origSkip = ref(false)

const touchedBest = ref(false)
watch(bestCase, () => { touchedBest.value = true })

const canRun = computed(() =>
	Number.isFinite(props.clientGroupNumber) && Number.isFinite(props.profitCenterCode)
)
const canSimulate = computed(() =>
	touchedBest.value && bestCase.value !== null && !Number.isNaN(Number(bestCase.value))
)

const dirty = computed(() =>
	Number(bestCase.value ?? 0) !== Number(origBest.value) ||
	Number(worstCase.value ?? 0) !== Number(origWorst.value) ||
	Boolean(skipBudget.value) !== Boolean(origSkip.value)
)
watch(dirty, v => emit('dirty-change', v))

const summary = ref(null)
function fmt(n) { return (Number(n) || 0).toLocaleString('de-DE') }

function currentFY() { const now = new Date(); return (now.getMonth() + 1) < 4 ? now.getFullYear() - 1 : now.getFullYear() }
function nextFY() { return currentFY() + 1 }

async function loadSavedCase() {
	if (!canRun.value) return
	loading.value = true; error.value = ''
	try {
		await ensureCsrf()
		const params = {
			client_group_number: Number(props.clientGroupNumber),
			profit_center_code: Number(props.profitCenterCode),
			fiscal_year: nextFY()
		}
		const { data } = await api.get('/api/budget-cases', { params, withCredentials: true })
		const row = data?.data || null
		if (row) {
			bestCase.value = Number(row.best_case ?? 0)
			worstCase.value = Number(row.worst_case ?? 0)
			skipBudget.value = !!row.skip_budget
			origBest.value = bestCase.value
			origWorst.value = worstCase.value
			origSkip.value = skipBudget.value
			emit('dirty-change', false)
			emit('values-change', {
				best_case: bestCase.value,
				worst_case: worstCase.value,
				skip_budget: skipBudget.value
			})
		} else {
			bestCase.value = null
			worstCase.value = null
			skipBudget.value = false
			origBest.value = 0
			origWorst.value = 0
			origSkip.value = false
			emit('dirty-change', false)
			emit('values-change', { best_case: 0, worst_case: 0, skip_budget: false })
		}
		summary.value = null
	} catch (e) {
		bestCase.value = null; worstCase.value = null
		origBest.value = 0; origWorst.value = 0
		skipBudget.value = false; origSkip.value = false
	} finally { loading.value = false }
}

async function simulate() {
	if (!canRun.value || !canSimulate.value || skipBudget.value) return
	loading.value = true; error.value = ''
	try {
		await ensureCsrf()
		const payload = {
			client_group_number: Number(props.clientGroupNumber),
			profit_center_code: Number(props.profitCenterCode),
			best_case: Number(bestCase.value ?? 0),
			worst_case: Number(worstCase.value ?? 0),
			compare_current: true
		}
		const { data } = await api.post('/api/budget-cases/simulate', payload, { withCredentials: true })
		const b = data?.basis || {}
		summary.value = {
			totalSalesYTD: b.totalSalesYTD ?? 0,
			ytdPct: b.ytdPct ?? 0,
			baseForecast: b.baseForecast ?? 0,
			totalBest: b.totalBest ?? 0,
			totalWorst: b.totalWorst ?? 0
		}
		emit('dirty-change', dirty.value)
		emit('simulated', { seriesTarget: data?.seriesTarget || [] })
	} catch (e) {
		summary.value = null
		error.value = e?.response?.data?.message || 'Fehler bei der Vorschau'
		toast.add({ severity: 'error', summary: 'Fehler', detail: error.value, life: 3000 })
	} finally { loading.value = false }
}

function toNumSafe(v) { const n = Number(v); return Number.isFinite(n) ? n : 0 }

function getValues() {
	return {
		best_case: toNumSafe(bestCase.value),
		worst_case: toNumSafe(worstCase.value),
		skip_budget: !!skipBudget.value
	}
}

function markSaved() {
	origBest.value = toNumSafe(bestCase.value)
	origWorst.value = toNumSafe(worstCase.value)
	origSkip.value = !!skipBudget.value
	emit('dirty-change', false)
}

function hardReset() {
	bestCase.value = null
	worstCase.value = null
	skipBudget.value = false
	origBest.value = 0
	origWorst.value = 0
	origSkip.value = false
	summary.value = null
	error.value = ''
	emit('dirty-change', false)
	emit('values-change', { best_case: 0, worst_case: 0, skip_budget: false })
}

watch([bestCase, worstCase, skipBudget], ([b, w, s]) => {
	emit('dirty-change', true)
	emit('values-change', { best_case: toNumSafe(b), worst_case: toNumSafe(w), skip_budget: !!s })
})

onMounted(loadSavedCase)
watch(() => [props.clientGroupNumber, props.profitCenterCode], loadSavedCase)

defineExpose({ getValues, markSaved, hardReset })
</script>

<style scoped>
.panel {
	display: flex;
	flex-direction: column;
	gap: 12px;
	height: 100%;
}

.field-checkbox {
	display: flex;
	align-items: center;
	gap: .5rem;
	margin-bottom: .5rem;
}

.cols {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 12px;
}

.col {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.tall :deep(input) {
	height: 56px;
	font-size: 1.05rem;
}

.lbl.best {
	color: #16a34a;
	font-weight: 600;
}

.lbl.worst {
	color: #ef4444;
	font-weight: 600;
}

.best-input :deep(.p-inputtext) {
	border-color: #16a34a !important;
}

.worst-input :deep(.p-inputtext) {
	border-color: #ef4444 !important;
}

.actions {
	display: flex;
	justify-content: flex-end;
}

.sep {
	height: 1px;
	background: rgba(0, 0, 0, .08);
	margin: 4px 0;
}

.loading {
	color: #475569;
}

.flex {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	height: 100%;
}

.summary {
	display: grid;
	gap: 8px;
}

.kpi {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 6px 8px;
	background: rgba(255, 255, 255, .3);
	border: solid 1px;
	border-radius: 8px;
}

.kpi .k {
	color: #334155;
}

.kpi .v {
	font-weight: 700;
}

.kpi .v.good {
	color: #16a34a;
}

.kpi .v.bad {
	color: #dc2626;
}

.placeholder {
	color: #6b7280;
}

.err {
	color: #dc2626;
}

.legend {
	font-size: .75rem;
	font-weight: 300;
}
</style>
