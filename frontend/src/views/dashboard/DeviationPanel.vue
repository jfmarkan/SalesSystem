<template>
	<div class="deviation-grid">
		<Toast />

		<!-- 📄 Columna izquierda: Lista -->
		<aside class="deviation-sidebar">
			<Card class="sidebar-card">
				<template #content>
					<!-- Botones -->
					<div class="tab-buttons-row">
						<Button label="Offen" :outlined="tab !== 'open'" :severity="tab === 'open' ? 'primary' : null"
							size="large" class="w-1/2" @click="tab = 'open'" />
						<Button label="Begründet" :outlined="tab !== 'just'"
							:severity="tab === 'just' ? 'primary' : null" size="large" class="w-1/2"
							@click="tab = 'just'" />
					</div>

					<!-- Contenedor con layout controlado -->
					<div class="list-layout">
						<!-- Lista scrollable -->
						<div class="deviation-list">
							<div class="list-item" v-for="dev in currentList" :key="dev.id"
								:class="{ selected: selectedDeviation?.id === dev.id }" @click="selectDeviation(dev)">
								<div class="list-item-title">{{ dev.pcName }}</div>
								<div class="list-item-meta">
									{{ dev.year }}-{{ String(dev.month).padStart(2, '0') }} |
									{{ dev.type === 'forecast' ? 'Forecast' : 'Ist' }}
								</div>
							</div>
						</div>

						<!-- Footer fijo -->
						<div class="list-footer">
							Total: {{ currentList.length }}
						</div>
					</div>
				</template>
			</Card>
		</aside>

		<!-- 📊 Columna derecha: Detalle -->
		<main class="deviation-content">
			<Card class="topbar-card">
				<template #content>
					<div class="topbar-inner">
						<div class="eyebrow">Abweichung</div>
						<div class="title-line">
							<strong>{{ selectedDeviation?.pcName }}</strong>
						</div>
					</div>
				</template>
			</Card>

			<Card class="full-detail-card" v-if="selectedDeviation">
				<DeviationItem :dev="selectedDeviation" :saving="savingId === selectedDeviation.id"
					:readonly="selectedDeviation.justified" @save="onSave" />
			</Card>

			<div v-if="loading" class="local-loader mt-4">
				<div class="dots">
					<span class="dot g"></span><span class="dot r"></span><span class="dot b"></span>
				</div>
				<div class="caption">Wird geladen…</div>
			</div>
		</main>
	</div>
</template>

<script setup>
// (sin cambios respecto a tu versión anterior)
import { ref, computed, onMounted } from 'vue'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import DeviationItem from '@/components/elements/DeviationItem.vue'

const toast = useToast()
const deviations = ref([])
const tab = ref('open')
const loading = ref(false)
const savingId = ref(null)
const selectedDeviation = ref(null)

const openList = computed(() => deviations.value.filter((d) => !d.justified))
const closedList = computed(() => deviations.value.filter((d) => d.justified))
const currentList = computed(() => (tab.value === 'open' ? openList.value : closedList.value))

function selectDeviation(dev) {
	selectedDeviation.value = dev
}

function parseMaskedInt(v) {
	if (typeof v === 'number') return Math.round(v)
	if (typeof v !== 'string') return 0
	const noDots = v.replace(/\./g, '')
	const beforeComma = noDots.split(',')[0]
	const onlyDigits = beforeComma.replace(/[^\d-]/g, '')
	return onlyDigits === '' || onlyDigits === '-' ? 0 : parseInt(onlyDigits, 10)
}
const toNumArray = (arr) => Array.isArray(arr) ? arr.map(parseMaskedInt) : null

function normalizeDev(d) {
	return {
		id: d.id,
		type: String(d.type || 'sales').toLowerCase(),
		clientName: d.clientName || '',
		pcCode: d.pcCode || '',
		pcName: d.pcName || '',
		year: parseMaskedInt(d.year),
		month: parseMaskedInt(d.month),
		sales: parseMaskedInt(d.sales),
		budget: parseMaskedInt(d.budget),
		forecast: parseMaskedInt(d.forecast),
		deltaAbs: parseMaskedInt(d.deltaAbs),
		deltaPct: parseMaskedInt(d.deltaPct),
		comment: d.comment || '',
		plan: d.plan || null,
		actions: Array.isArray(d.actions) ? d.actions : [],
		justified: !!d.justified,
		months: Array.isArray(d.months) ? d.months : null,
		salesSeries: toNumArray(d.salesSeries),
		budgetSeries: toNumArray(d.budgetSeries),
		forecastSeries: toNumArray(d.forecastSeries),
	}
}

async function loadDeviations() {
	loading.value = true
	try {
		await ensureCsrf()
		const { data } = await api.get('/api/deviations')
		deviations.value = Array.isArray(data) ? data.map(normalizeDev) : []
		if (deviations.value.length > 0) {
			selectedDeviation.value = deviations.value[0]
		}
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Abweichungen konnten nicht geladen werden',
			life: 2500,
		})
	} finally {
		loading.value = false
	}
}

async function onSave(payload) {
	const { id, comment, plan, actions } = payload
	savingId.value = id
	try {
		await ensureCsrf()
		await api.put(`/api/deviations/${id}/justify`, { comment, plan, actions })
		const idx = deviations.value.findIndex((d) => d.id === id)
		if (idx >= 0) {
			deviations.value[idx] = {
				...deviations.value[idx],
				justified: true,
				comment,
				plan,
				actions,
			}
		}
		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Begründung gespeichert',
			life: 1600,
		})
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Begründung konnte nicht gespeichert werden',
			life: 2500,
		})
	} finally {
		savingId.value = null
	}
}

onMounted(loadDeviations)
</script>

<style scoped>
.deviation-grid {
	display: grid;
	grid-template-columns: 2fr 10fr;
	gap: 16px;
	height: 100%;
}

/* === Sidebar === */
.deviation-sidebar {
	display: flex;
	flex-direction: column;
	height: 100%;
	overflow: hidden;
}

.sidebar-card {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.tab-buttons-row {
	display: flex;
	gap: 8px;
	margin-bottom: 8px;
}

.tab-buttons-row :deep(.p-button) {
	flex: 1;
}

/* 🔧 NUEVO: layout que reparte espacio entre lista y footer */
.list-layout {
	display: flex;
	flex-direction: column;
	flex: 1;
	min-height: 0;
	max-height: 100%;
	overflow: hidden;
}

/* 🔧 NUEVO: lista que se adapta y scrollea si hace falta */
.deviation-list {
	flex: 1;
	min-height: 0;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding-right: 4px;
}

/* Footer fijo al final de la card */
.list-footer {
	font-size: 0.75rem;
	color: var(--text-muted);
	padding-top: 6px;
	text-align: right;
	border-top: 1px solid var(--surface-border);
	margin-top: 8px;
}


/* El resto de estilos los mantenés igual */
.list-item {
	padding: 8px;
	border-radius: 6px;
	cursor: pointer;
	font-size: 0.875rem;
	background: var(--surface-100);
	border: 1px solid transparent;
}

.list-item:hover {
	background: var(--surface-200);
}

.list-item.selected {
	border: 1px solid var(--primary);
	;
}

.list-item-title {
	font-weight: 600;
}

.list-item-meta {
	font-size: 0.75rem;
	color: var(--text-muted);
}

/* === Contenido === */
.deviation-content {
	display: flex;
	flex-direction: column;
	gap: 16px;
	overflow-y: auto;
	height: 100%;
}

.topbar-inner {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.eyebrow {
	font-size: 0.75rem;
	color: var(--text-muted);
	text-transform: uppercase;
}

.title-line {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}

.sep {
	opacity: 0.5;
}

.full-detail-card {
	flex: 1;
	display: flex;
	flex-direction: column;
}
</style>
