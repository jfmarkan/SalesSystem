<template>
	<div class="menu-wrapper">
		<div class="grid-wrapper">
			<!-- 3 columnas izquierdas (acciones directas): 2 por columna -->
			<div class="column" v-for="(col, ci) in leftColumns" :key="'left-' + ci">
				<div v-for="card in col" :key="card.key" class="menu-card action" @click="onCardClick(card)"
					:aria-label="card.label" role="button">
					<Card>
						<template #content>
							<div class="card-content">
								<div class="tile-head">
									<i :class="['pi', card.icon]"></i>
									<h4 class="title">{{ card.label }}</h4>
								</div>
								<div class="tile-description">
									<p class="desc">{{ card.desc }}</p>
								</div>
							</div>
						</template>
					</Card>
				</div>
			</div>

			<!-- 1 columna derecha (opciones): 3 en vertical -->
			<div class="column">
				<div v-for="card in rightColumn" :key="card.key" class="menu-card option" @click="onCardClick(card)"
					:aria-label="card.label" role="button">
					<Card>
						<template #content>
							<div class="card-content">
								<div class="tile-head">
									<i :class="['pi', card.icon]"></i>
									<h4 class="title">{{ card.label }}</h4>
								</div>
								<p class="desc">{{ card.desc }}</p>
							</div>
						</template>
					</Card>
				</div>
			</div>
		</div>
		<div v-if="runId" class="card" style="margin-top:16px;">
			<div class="tile-head" style="display:flex;justify-content:space-between;">
				<h4>Clients Update · Run #{{ runId }} · {{ runStatus }}</h4>
				<Button size="small" label="Refrescar" icon="pi pi-refresh" @click="startPollingRun" />
			</div>
			<pre
				style="white-space:pre-wrap; font-family:ui-monospace; font-size:12px; max-height:260px; overflow:auto;">
		{{ runLog }}
	</pre>
		</div>
	</div>

	<Dialog v-model:visible="showDialog" modal header="Forecast-Parameter für Typ C/D"
		:style="{ width: separateD ? '680px' : '420px' }" :closable="!loading">
		<!-- COLUMNAS -->
		<div class="columns">
			<!-- Columna izquierda (Typ C o C+D) -->
			<div class="col">
				<h4>{{ separateD ? 'Kunden Typ C' : 'Kunden Typ C und D' }}</h4>

				<label>Best Case (%)</label>
				<InputNumber v-model="bestC" :min="-100" :max="100" suffix=" %" showButtons />

				<label>Worst Case (%)</label>
				<InputNumber v-model="worstC" :min="-100" :max="100" suffix=" %" showButtons />
			</div>

			<!-- Columna derecha (Typ D) -->
			<div class="col" v-if="separateD">
				<h4>Kunden Typ D</h4>

				<label>Best Case (%)</label>
				<InputNumber v-model="bestD" :min="-100" :max="100" suffix=" %" showButtons />

				<label>Worst Case (%)</label>
				<InputNumber v-model="worstD" :min="-100" :max="100" suffix=" %" showButtons />
			</div>
		</div>

		<!-- Checkbox -->
		<div class="checkbox-row">
			<Checkbox v-model="separateD" :binary="true" inputId="sepD" />
			<label for="sepD" class="ml-2">Unterschiedlicher Wert für Typ D</label>
		</div>

		<!-- Footer -->
		<template #footer>
			<Button label="Abbrechen" icon="pi pi-times" class="p-button-text" @click="showDialog = false"
				:disabled="loading" />
			<Button label="Generieren" icon="pi pi-check" @click="submit" :loading="loading" />
		</template>
	</Dialog>

</template>

<script setup>
import { ref, computed, defineProps } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue'
import { ensureCsrf } from '@/plugins/csrf'
import api from '@/plugins/axios'

const toast = useToast()

const showDialog = ref(false)
const bestC = ref(0)
const worstC = ref(0)
const separateD = ref(false)
const bestD = ref(0)
const worstD = ref(0)
const loading = ref(false)

function openDialog() {
	showDialog.value = true
}

async function submit() {
	loading.value = true
	const payload = {
		best_case_c: bestC.value,
		worst_case_c: worstC.value,
		best_case_d: separateD.value ? bestD.value : bestC.value,
		worst_case_d: separateD.value ? worstD.value : worstC.value,
	}

	try {
		await api.post('/api/budgets/generate', payload, { withCredentials: true })
		toast.add({
			severity: 'success',
			summary: 'Erfolg',
			detail: '✅ Prozess abgeschlossen',
			life: 3000,
		})
	} catch (err) {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: err?.response?.data?.message || '❌ Fehler beim Erzeugen',
			life: 4000,
		})
	} finally {
		loading.value = false
		showDialog.value = false
	}
}


/* PROPS: listas y rutas */
const props = defineProps({
	actions: {
		type: Array,
		default: () => [
			{ key: 'budgeting_preview', label: 'Budgetierung Uberblick', icon: 'pi-refresh', desc: 'Leert und regeneriert den App-Cache.' },
			{ key: 'manage_users', label: 'Benutzer', icon: 'pi-user-edit', desc: 'Verwaltung von Benutzern und Rollen: Anlegen, Sperren/Aktivieren, Rollen- und Teamzuweisung, Kundenübertragungen und Zusatzkontingente.' },
			{ key: 'manage_clients', label: 'Kunden', icon: 'pi-building', desc: 'Importiert/aktualisiert Kundendaten.' },
			{ key: 'manage_profitcenters', label: 'Profit Center', icon: 'pi-database', desc: 'Reindiziert/verdichtet Verkaufsdaten.' },
			{ key: 'manage_company', label: 'Unternehmen', icon: 'pi-home', desc: 'Berechnet Kennzahlen neu.' },
			{ key: 'toggle_maintenance', label: 'Wartungsmodus', icon: 'pi-power-off', desc: 'Aktiviert/Deaktiviert Wartungsmodus.' },
		],
	},
	options: {
		type: Array,
		default: () => [
			{ key: 'update_clients', label: 'Kunden Synchronisierung', icon: 'pi-user-edit', desc: 'Synchronisiert Benutzer mit externer Quelle.' },
			{ key: 'generate_budget', label: 'Budget + Forecast', icon: 'pi-percentage', desc: 'Budgets & Forecasts generieren.' },
			{ key: 'client_explorer', label: 'Kunden-Explorer', icon: 'pi-search', desc: 'Kunden suchen, filtern und prüfen.' },
		],
	},
	routesByKey: {
		type: Object,
		default: () => ({
			// acciones
			update_clients: '/settings/tools/clients-update',
			// opciones
			manage_users: '/settings/users',
			manage_clients: '/settings/clients',
			manage_profitcenters: '/settings/profit-center',
			manage_company: '/settings/company',
			budgeting_preview: '/settings/budgeting',
			rebuild_sales: '/admin/sales/rebuild',
			regenerate_kpis: '/admin/kpis',
			toggle_maintenance: '/admin/maintenance',
			// por si acaso
		}),
	},
})

/* layout 2,2,2,3 */
function group2(list) { return [list.slice(0, 2), list.slice(2, 4), list.slice(4, 6)] }
const leftColumns = computed(() => group2(props.actions))
const rightColumn = computed(() => props.options)

/* Router push (no UI interna) */
const router = useRouter()

function onCardClick(card) {
	const to = props.routesByKey[card?.key]
	if (card.key === 'generate_budget') { openDialog(); return }
	if (card.key === 'update_clients') { startClientsUpdate(); return }
	if (to) router.push(to)
	else console.warn('No route mapped for key:', card?.key)
}

// Clients Update
const updatingClients = ref(false)
const runId = ref(null)
const runStatus = ref('')
const runLog = ref('')
let pollTimer = null

async function startClientsUpdate() {
	if (updatingClients.value) return
	updatingClients.value = true
	try {
		await ensureCsrf()
		const { data } = await api.post(
			'/api/settings/tools/clients-update',
			{ queued: true },
			{ withCredentials: true, headers: { Accept: 'application/json' } }
		)
		runId.value = data.run_id
		runStatus.value = 'queued'
		runLog.value = ''
		toast.add({ severity: 'info', summary: 'Clients Update', detail: `Run #${data.run_id}`, life: 1800 })
		startPollingRun()
	} catch (err) {
		toast.add({ severity: 'error', summary: 'Error', detail: err?.response?.data?.message || err.message, life: 4000 })
	} finally {
		updatingClients.value = false
	}
}

function startPollingRun() {
	clearInterval(pollTimer)
	if (!runId.value) return
	pollTimer = setInterval(async () => {
		try {
			const { data } = await api.get(`/api/settings/tools/runs/${runId.value}`, {
				withCredentials: true, headers: { Accept: 'application/json' }
			})
			runStatus.value = data.status
			runLog.value = data.log || ''
			if (data.status === 'ok' || data.status === 'failed') {
				clearInterval(pollTimer)
				const s = data?.stats || {}
				const msg = data.status === 'ok'
					? `OK · creados: ${Number(s.created ?? 0)} · existentes: ${Number(s.existing_skipped ?? 0)}`
					: 'Falló'
				toast.add({ severity: data.status === 'ok' ? 'success' : 'error', summary: 'Clients Update', detail: msg, life: 3000 })
			}
		} catch {
			clearInterval(pollTimer)
		}
	}, 1500)
}
</script>

<style scoped>
.menu-wrapper {
	--warm: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
	--cool: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
	--card-w: 300px;
	--card-h: 200px;
	--gap: 24px;

	display: flex;
	justify-content: center;
	align-items: start;
	height: 100%;
}

/* grid 4 cols con gap constante */
.grid-wrapper {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: var(--gap);
	width: 100%;
	max-width: 1400px;
	height: 100%;
}

.column {
	display: flex;
	flex-direction: column;
	gap: var(--gap);
	align-items: center
}

/* Card + glow */
.menu-card {
	position: relative;
	border-radius: 15px;
	cursor: pointer;
}

.menu-card::before {
	content: "";
	position: absolute;
	inset: -10px;
	border-radius: inherit;
	z-index: 0;
	opacity: 0;
	transform: scale(.98);
	filter: blur(15px);
	transition: opacity .16s ease, transform .16s ease, filter .16s ease;
	pointer-events: none;
}

/* SWAP: action -> COOL, option -> WARM */
.menu-card.action::before {
	background: var(--cool);
}

.menu-card.option::before {
	background: var(--warm);
}

.menu-card :deep(.p-card) {
	position: relative;
	z-index: 1;
	width: var(--card-w);
	height: var(--card-h);
	border-radius: 15px;
	background: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
	transition: transform .14s ease, box-shadow .14s ease;
}

/* Hover: leve, con glow visible */
.menu-card:hover::before {
	opacity: 1;
	transform: scale(.95);
	filter: blur(22px);
}

.menu-card:hover :deep(.p-card) {
	transform: scale(1.01);
	box-shadow: 0 8px 18px rgba(0, 0, 0, .10);
}

/* Contenido */
/* 1) Deja de centrar el Card */
.menu-card :deep(.p-card) {
  display: flex;
  flex-direction: column;
  /* quita estas si existen: align-items:center; justify-content:center; */
  align-items: stretch;
  justify-content: flex-start;
}

/* 2) Body ocupa todo el alto disponible */
.menu-card :deep(.p-card-body) {
  flex: 1;
  display: flex;
  flex-direction: column;
  padding: 0;            /* opcional: elimina padding extra */
}

/* 3) Content también estira */
.menu-card :deep(.p-card-content) {
  flex: 1;
  display: flex;
  min-height: 0;         /* evita overflow por flex en algunos navegadores */
}

/* 4) Tu contenedor interno reparte head arriba y desc abajo */
.card-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 10px;         /* conserva tu padding */
}

/* Asegura que la descripción corte bien si es larga */
.tile-description {
  margin-top: 8px;
  display: flex;
}
.desc {
  flex: 1;
  overflow: hidden;
}



.p-card-body :deep(.p-card){
	height: 100%;
}

.tile-head {
	display: flex;
	flex-direction: row;
	gap: 6px;
}

.tile-head i {
	font-size: 18px;
	color: #333;
}

.tile-head .title {
	margin: 0;
	font-size: .95rem;
	color: #1b1b1d;
	font-weight: 700;
}

.tile-description{
	display: flex;
	text-align: left;
}

.desc {
	margin: 0;
	font-size: .82rem;
	line-height: 1.2;
	color: #4b5563;
	display: -webkit-box;
	line-clamp: 3;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.grid {
	display: flex;
	gap: 24px;
}

.col-6 {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.mt-2 {
	margin-top: 0.5rem;
}

.mt-4 {
	margin-top: 1rem;
}

.ml-2 {
	margin-left: 0.5rem;
}

.columns {
	display: flex;
	gap: 24px;
	margin-bottom: 12px;
}

.col {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.checkbox-row {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8px;
	margin-top: 12px;
}

.ml-2 {
	margin-left: 0.5rem;
}


/* Responsive */
@media (max-width: 1100px) {
	.grid-wrapper {
		grid-template-columns: repeat(2, 1fr);
	}
}

@media (max-width: 640px) {
	.grid-wrapper {
		grid-template-columns: 1fr;
	}

	.menu-card :deep(.p-card) {
		width: 88vw;
		max-width: 360px;
		height: auto;
		aspect-ratio: 1/1;
	}
}
</style>
