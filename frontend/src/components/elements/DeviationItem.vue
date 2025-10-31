<template>
	<div class="deviation-item-grid">
		<!-- LEFT -->
		<section class="left-col">
			<div class="metrics-row">
				<Card class="metric-card">
					<template #content>
						<div class="eyebrow">Ist</div>
						<div class="value">{{ fmtNumber(dev.sales) }}</div>
					</template>
				</Card>
				<Card class="metric-card">
					<template #content>
						<div class="eyebrow">Budget</div>
						<div class="value">{{ fmtNumber(dev.budget) }}</div>
					</template>
				</Card>
				<Card class="metric-card">
					<template #content>
						<div class="eyebrow">Forecast</div>
						<div class="value">{{ fmtNumber(dev.forecast) }}</div>
					</template>
				</Card>
				<Card class="metric-card delta-card" :class="deltaSeverityClass">
					<template #content>
						<div class="eyebrow">Delta</div>
						<div class="value">
							{{ fmtNumber(dev.deltaAbs) }} ({{ fmtPct(dev.deltaPct) }})
						</div>
					</template>
				</Card>
			</div>

			<Card class="chart-card">
				<template #content>
					<div class="chart-inner">
						<MiniDeviationChart :months="dev.months" :sales="dev.salesSeries ?? dev.sales"
							:budget="dev.budgetSeries ?? dev.budget" :forecast="dev.forecastSeries ?? dev.forecast"
							:height="'100%'" :value-formatter="fmtNumber" />
					</div>
				</template>
			</Card>
		</section>

		<!-- RIGHT -->
		<section class="right-col">
			<Card class="form-card">
				<template #content>
					<div class="eyebrow">Begründung</div>
					<Textarea v-model="comment" autoResize rows="5" class="w-full mb-4"
						placeholder="Begründung eingeben…" />

					<div v-if="dev.type === 'forecast'" class="eyebrow mt-2">Aktionsplan (Zielbeschreibung)</div>

					<Textarea v-if="dev.type === 'forecast'" v-model="plan" autoResize rows="5" class="w-full mb-3"
						placeholder="Plan kurz beschreiben…" />

					<div class="flex justify-end mt-3">
						<Button :label="saving ? 'Speichern…' : 'Speichern'" icon="pi pi-save" :disabled="!canSave"
							:loading="saving" @click="doSave" />
					</div>
				</template>
			</Card>

			<Card v-if="dev.type === 'forecast'" class="actions-card">
				<template #content>
					<div class="eyebrow">Aktionsplan</div>

					<div v-if="dev.deltaPct < 0 && actions.length === 0" class="empty-actions">
						<Button label="Plan erstellen" icon="pi pi-flag" @click="createPlan" />
					</div>

					<div v-else class="actions-list">
						<div v-for="(a, idx) in actions" :key="idx" class="action-edit-row">
							<InputText v-model="a.title" placeholder="Titel" />
							<InputText v-model="a.desc" placeholder="Beschreibung" />
							<div class="row-inline">
								<Calendar v-model="a.due" dateFormat="yy-mm-dd" :manualInput="true" :showIcon="true" />
								<Button icon="pi pi-trash" severity="danger" outlined @click="removeAction(idx)" />
							</div>
						</div>
						<div class="flex justify-center mt-3">
							<Button icon="pi pi-plus" rounded text @click="addAction" />
						</div>
					</div>
				</template>
			</Card>
		</section>
	</div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import Calendar from 'primevue/calendar'
import MiniDeviationChart from '../charts/MiniDeviationChart.vue'

const props = defineProps({
	dev: Object,
	saving: Boolean,
	readonly: Boolean,
})
const emit = defineEmits(['save', 'dirty-change', 'can-save'])

const comment = ref(props.dev.comment || '')
const plan = ref(props.dev.plan || '')
const actions = ref([...props.dev.actions] || [])
const isDirty = ref(false)

watch([comment, plan, actions], () => {
	isDirty.value = true
	emit('dirty-change', true)
})

// helpers
function toInt(v) {
	if (typeof v === 'number' && Number.isFinite(v)) return Math.round(v)
	const s = String(v ?? '').replace(/\./g, '').split(',')[0].replace(/[^\d-]/g, '')
	return s === '' || s === '-' ? 0 : parseInt(s, 10)
}
const fmtNumber = (x) => new Intl.NumberFormat('de-DE').format(toInt(x))
const fmtPct = (p) => {
	const n = toInt(p)
	return Number.isFinite(n) ? `${n}%` : '—'
}

const deltaSeverityClass = computed(() => {
	const d = toInt(props.dev.deltaPct)
	const ad = Math.abs(d)
	if (ad > 10) return 'sev-red'
	if (ad > 5) return 'sev-orange'
	if (ad > 2) return 'sev-yellow'
	return 'sev-green'
})

const canSave = computed(() => {
	const type = props.dev.type
	const delta = Number(props.dev.deltaPct) || 0
	const hasComment = comment.value.trim().length > 0
	const hasActions = actions.value.length > 0

	if (type === 'ist') return hasComment
	if (type === 'forecast' && delta < 0) return hasComment && hasActions
	if (type === 'forecast' && delta >= 0) return hasComment
	return false
})

watch(canSave, (v) => emit('can-save', v))

function addAction() {
	actions.value.push({ title: '', desc: '', due: null })
}
function removeAction(i) {
	actions.value.splice(i, 1)
}
function createPlan() {
	addAction()
}
function doSave() {
	if (!canSave.value) return
	emit('save', {
		id: props.dev.id,
		comment: comment.value,
		plan: plan.value,
		actions: actions.value,
	})
	isDirty.value = false
	emit('dirty-change', false)
}
</script>

<style scoped>
.deviation-item-grid {
	display: grid;
	grid-template-columns: 7fr 5fr;
	gap: 16px;
	width: 100%;
	height: 100%;
	box-sizing: border-box;
}

.left-col {
	display: grid;
	grid-template-rows: auto 1fr;
	gap: 16px;
	min-height: 0;
	width: 100%;
}

.metrics-row {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 16px;
}

.metric-card {
	display: flex;
	flex-direction: column;
	justify-content: center;
	padding: .15rem .3rem;
	min-height: 100px;
}

.chart-card {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-height: 0;
}

/* Mantiene la estructura flex del card */
.chart-card :deep(.p-card-body),
.chart-card :deep(.p-card-content) {
	display: flex;
	flex: 1;
	flex-direction: column;
	min-height: 0;
	padding: 0; /* la card no agrega padding */
}

/* 👇 padding interno solo para el gráfico */
.chart-inner {
	flex: 1;
	display: flex;
	align-items: stretch;
	justify-content: center;
	width: calc(100% - 2rem);
	max-height: calc(100% - 2rem);

	/* 👇 este es el aire visual dentro del gráfico */
	padding: 2rem 2rem;
	box-sizing: border-box;
}


.eyebrow {
	font-size: 0.8rem;
	color: var(--text-muted);
	text-transform: uppercase;
	margin-bottom: 6px;
}

.value {
	font-size: 1.4rem;
	font-weight: 600;
}

.delta-card.sev-green {
	border-left: 4px solid #2ecc71;
}

.delta-card.sev-yellow {
	border-left: 4px solid #e6b729;
}

.delta-card.sev-orange {
	border-left: 4px solid #e88d1e;
}

.delta-card.sev-red {
	border-left: 4px solid #b01513;
}

.right-col {
	display: flex;
	flex-direction: column;
	gap: 20px;
	min-height: 0;
}

.form-card,
.actions-card {
	flex: 0 0 auto;
	display: flex;
	flex-direction: column;
	padding: 16px;
}

.actions-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.action-edit-row {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding-bottom: 10px;
	border-bottom: 1px dashed var(--surface-border);
}

.row-inline {
	display: flex;
	align-items: center;
	gap: 8px;
}

.empty-actions {
	padding: 16px 0;
	text-align: center;
}
</style>
