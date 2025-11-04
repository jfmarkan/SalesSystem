<template>
	<!-- Toast -->
	<Toast />

	<!-- Confirmación de cambios sin guardar -->
	<Dialog
		v-model:visible="confirmVisible"
		modal
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
				@click="((confirmVisible = false), (pendingChange = null))"
			/>
			<Button
				label="Verwerfen"
				severity="danger"
				icon="pi pi-trash"
				@click="discardAndApply"
			/>
			<Button label="Speichern" icon="pi pi-save" @click="saveAndApply" />
		</div>
	</Dialog>

	<!-- Das ist Forecast (vor Erstellung) -->
	<Dialog
		v-model:visible="preCreateConflictVisible"
		modal
		:draggable="false"
		header="Das ist Forecast"
		:style="{ width: '520px' }"
	>
		<p>
			Der ausgewählte Kunde ist bereits mit dem gewählten Profitcenter verknüpft. Das ist
			<b>Forecast</b> – neue Chance nicht erlaubt.
		</p>
		<div class="mt-3 flex justify-content-end gap-2">
			<Button label="Nein" severity="danger" @click="cancelCreateAndReset" />
			<Button label="Ja" icon="pi pi-check" @click="acceptForecastAndAbort" />
		</div>
	</Dialog>

	<!-- Modal: Chance gewonnen -->
	<WonChanceModal
		v-model:visible="wonDialogVisible"
		:lookupClientByNumber="lookupClientByNumber"
		:checkClientPcExists="checkClientPcExists"
		:initialClientNumber="opForm.client_group_number"
		:initialClientName="opForm.potential_client_name"
		:initialClassificationId="null"
		:profitCenterCode="opForm.profit_center_code"
		:fiscalYear="opForm.fiscal_year"
		@finalize="onModalFinalize"
		@merge-forecast="onModalMerge"
	/>

	<!-- Dialog: Kunden auswählen -->
	<Dialog
		v-model:visible="clientSearchVisible"
		modal
		:draggable="false"
		header="Kunden auswählen"
		:style="{ width: '720px' }"
	>
		<div class="mb-2">
			<InputText
				v-model="clientSearchQuery"
				class="w-full"
				placeholder="Suche nach Name oder Nummer…"
			/>
		</div>

		<div class="client-list-wrap">
			<div
				v-for="c in filteredClients"
				:key="c.client_number"
				class="client-row"
				@click="selectClient(c)"
			>
				<div class="cr-name" v-html="renderClientRow(c)"></div>
			</div>
			<div v-if="!clientLoading && !filteredClients.length">Keine Ergebnisse…</div>
			<div v-if="clientLoading">Laden…</div>
		</div>

		<div class="mt-3 flex justify-content-end">
			<Button label="Schließen" severity="secondary" @click="clientSearchVisible = false" />
		</div>
	</Dialog>

	<!-- ===== Grid 2 / 10 ===== -->
	<div class="eqp-grid">
		<!-- Sidebar (2 columnas) -->
		<aside class="eqp-aside">
			<Card class="aside-card">
				<template #content>
					<div class="status-filter">
						<Button
							label="Offen"
							size="small"
							:severity="statusFilter === 'open' ? 'primary' : 'secondary'"
							@click="setStatusFilter('open')"
						/>
						<Button
							label="Gewonnen"
							size="small"
							:severity="statusFilter === 'won' ? 'primary' : 'secondary'"
							@click="setStatusFilter('won')"
						/>
						<Button
							label="Verloren"
							size="small"
							:severity="statusFilter === 'lost' ? 'primary' : 'secondary'"
							@click="setStatusFilter('lost')"
						/>
					</div>

					<div v-if="listLoading" class="local-loader">
						<div class="caption">Wird geladen…</div>
					</div>

					<template v-else>
						<div class="listbox-flex">
							<Listbox
								v-if="listOptions.length"
								v-model="selectedGroupId"
								:options="listOptions"
								optionLabel="label"
								optionValue="value"
								@change="(e) => onSelectGroup(e.value)"
							>
								<template #option="slotProps">
									<div class="row-item">
										<div class="top">
											<div class="pc">{{ slotProps.option.pcName }}</div>
										</div>
										<div class="mid">
											{{ slotProps.option.client || '—' }}
										</div>
										<div class="bot">
											<span class="amt">{{
												fmtInt(slotProps.option.amount)
											}}</span>
											<span class="pct">{{ slotProps.option.pct }}%</span>
										</div>
									</div>
								</template>
							</Listbox>

							<div v-else>Keine Chancen vorhanden.</div>
						</div>
					</template>
				</template>
			</Card>
		</aside>

		<!-- Main (10 columnas) -->
		<main class="eqp-main">
			<!-- Header -->
			<Card>
				<template #content>
					<div class="topbar">
						<div class="title-left">
							<div class="eyebrow">Verkaufschance</div>
							<div class="title-line">
								<strong class="kunde">{{ headerClient || 'Kunde' }}</strong>
								<span class="sep" aria-hidden="true"> | </span>
								<span class="pc">{{ headerPc || '-' }}</span>
							</div>
						</div>
						<div class="actions">
							<Button
								icon="pi pi-plus"
								label="Neue Chance"
								@click="startCreateMode"
							/>
						</div>
					</div>
				</template>
			</Card>

			<!-- Fila: Form (10) + Versiones (2) -->
			<div class="row-form-extras">
				<!-- Formulario (10) -->
				<Card class="form-card">
					<template #content>
						<template v-if="createMode || selectedGroupId">
							<div class="form-grid">
								<!-- Top row -->
								<div class="top-row">
									<div class="field">
										<label class="lbl">Potentieller Kunde</label>
										<div class="inline">
											<InputText
												v-model="opForm.potential_client_name"
												class="client-input"
												:disabled="isReadOnly"
											/>
											<Button
												label="Kunde wählen"
												class="p-button-text p-button-sm"
												:disabled="isReadOnly"
												@click="pickExistingClient"
											/>
										</div>
									</div>

									<div class="field">
										<label class="lbl">Status</label>
										<Select
											v-model="opForm.status"
											:options="statusOpts"
											optionLabel="label"
											optionValue="value"
											class="w-full status-select"
											:disabled="isStatusMenuDisabled"
										/>
									</div>
								</div>

								<!-- Two-cols -->
								<div class="two-cols">
									<div class="left">
										<div class="field">
											<label class="lbl">Profitcenter</label>
											<Select
												v-model="opForm.profit_center_code"
												:options="pcOptionsForSelection"
												optionLabel="label"
												optionValue="value"
												placeholder="Profitcenter…"
												class="w-full pc-select"
												@change="updateAvailabilityForPc"
												:disabled="isReadOnly"
											/>
											<small v-if="pcFilteredWarning" class="text-danger">{{
												pcFilteredWarning
											}}</small>
										</div>

										<div class="field">
											<label class="lbl">Volumen</label>
											<div class="vol-inline">
												<InputNumber
													v-model="opForm.volume"
													:min="0"
													:step="1"
													:useGrouping="true"
													locale="de-DE"
													:minFractionDigits="0"
													:maxFractionDigits="0"
													inputClass="w-full"
													:disabled="isReadOnly"
												/>
												<span class="assigned"
													>/ {{ fmtInt(availableForSelected) }}</span
												>
											</div>
										</div>

										<div class="field">
											<label class="lbl">Start (Monat/Jahr)</label>
											<DatePicker
												v-model="opMonthModel"
												view="month"
												dateFormat="mm/yy"
												:manualInput="false"
												showIcon
												class="w-full"
												@update:modelValue="syncMonthYear"
												:disabled="isReadOnly"
											/>
										</div>

										<div class="field">
											<label class="lbl">Wahrscheinlichkeit</label>
											<div class="prob-wrap">
												<Slider
													v-model="opForm.probability_pct"
													:min="0"
													:max="100"
													:step="10"
													class="prob-slider"
													@slideend="snapProb"
													@change="snapProb"
													:disabled="isReadOnly"
												/>
												<span class="pct"
													>{{ opForm.probability_pct }}%</span
												>
											</div>
											<div class="tickbar" aria-hidden="true"></div>
										</div>
									</div>

									<div class="right">
										<div class="field grow">
											<label class="lbl">Kommentare</label>
											<Textarea
												v-model="opForm.comments"
												rows="8"
												autoResize
												class="w-full comment-box"
												:disabled="isReadOnly"
											/>
										</div>

										<div class="actions-right">
											<Button
												v-if="createMode"
												label="Budget erstellen"
												icon="pi pi-table"
												class="p-button-outlined"
												:disabled="isReadOnly || !canCreateBudget"
												@click="onGenerateBudget"
											/>
											<Button
												v-else
												label="Aktualisieren"
												icon="pi pi-save"
												class="p-button-outlined"
												:disabled="isReadOnly || !opDirty"
												@click="saveNewVersion"
											/>
										</div>
									</div>
								</div>
							</div>
						</template>
						<div v-else>Bitte Chance auswählen oder „Neue Chance“ drücken…</div>
					</template>
				</Card>

				<!-- Versiones (2) -->
				<Card class="versions-card">
					<template #content>
						<div class="eyebrow mb-2">Versionen</div>
						<template v-if="selectedGroupId">
							<Listbox
								v-if="versionOptions.length > 1"
								v-model="selectedVersion"
								:options="versionOptions"
								optionLabel="label"
								optionValue="value"
								class="w-full dark-list"
								@change="(e) => onSelectVersion(e.value)"
							/>
							<div v-else>—</div>
							<div class="versions-meta">
								<div>
									Aktuelle Version: <b>v{{ selectedVersion || '—' }}</b>
								</div>
								<div>Letztes Update: {{ latestMeta.updated_at || '—' }}</div>
							</div>
						</template>
						<div v-else>Keine Auswahl.</div>
					</template>
				</Card>
			</div>

			<!-- Tabla (12) -->
			<Card class="table-card">
				<template #content>
					<template v-if="createMode || selectedGroupId">
						<div v-if="tableLoading" class="local-loader">
							<div class="caption">Wird geladen…</div>
						</div>
						<template v-else>
							<div class="ctbl-wrap" :class="{ locked: isReadOnly }">
								<ComponentTable
									:months="months"
									:ventas="sales"
									:budget="budget"
									:forecast="forecast"
									@edit-forecast="onEditForecastInt"
								/>
							</div>
							<div class="flex justify-end mt-3">
								<Button
									label="Forecast speichern"
									icon="pi pi-check"
									:disabled="isReadOnly || changedForecastCount === 0"
									@click="saveForecast()"
								/>
							</div>
						</template>
					</template>
					<div v-else>Keine Tabelle.</div>
				</template>
			</Card>
		</main>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useToast } from 'primevue/usetoast'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Slider from 'primevue/slider'

import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import ComponentTable from '@/components/tables/ComponentTable.vue'
import WonChanceModal from '@/components/modals/WonChanceModal.vue'

const toast = useToast()

/* Helpers */
const confirmVisible = ref(false)
const pendingChange = ref(null)
const cloneDeep = (v) => JSON.parse(JSON.stringify(v))
function fmtInt(v) {
	return Number(v || 0).toLocaleString('de-DE', { maximumFractionDigits: 0 })
}
function normStatus(s) {
	const x = String(s || '').toLowerCase()
	if (x === 'draft') return 'open'
	if (x.includes('won')) return 'won'
	if (x.includes('lost')) return 'lost'
	return x
}

/* List + filter */
const listLoading = ref(false)
const allRows = ref([])
const listOptions = ref([])
const statusFilter = ref('open')
const selectedGroupId = ref(null)
const selectedVersion = ref(null)
const latestMeta = ref({})

function applyListFilter() {
	const want = statusFilter.value
	const rows = Array.isArray(allRows.value) ? allRows.value : []
	listOptions.value = rows
		.filter((r) => normStatus(r.status) === want)
		.map((r) => {
			const codeNum = Number(r.profit_center_code || 0)
			const pcName =
				r.profit_center_name ??
				r.pc_name ??
				r.name ?? // por si el backend manda "name"
				(codeNum ? `PC ${codeNum}` : 'Profit Center')

			const client = r.client_name ?? r.potential_client_name ?? r.name ?? '—'

			return {
				value: Number(r.opportunity_group_id),
				label: client, // no se usa para render pero queda coherente
				pc: String(codeNum), // por si lo necesitás en otro lado
				pcName,
				client,
				version: Number(r.version || 1),
				amount: Number(r.volume || 0),
				pct: Number(r.probability_pct || 0),
				statusLabel:
					normStatus(r.status) === 'won'
						? 'Gewonnen'
						: normStatus(r.status) === 'lost'
							? 'Verloren'
							: 'Offen',
			}
		})
}

function setStatusFilter(s) {
	statusFilter.value = s
	selectedGroupId.value = null
	selectedVersion.value = null
	createMode.value = false
	showBudgetTable.value = false
	loadList()
}
watch(statusFilter, () => loadList())

async function loadList() {
	listLoading.value = true
	try {
		await ensureCsrf()
		const { data } = await api.get('/api/extra-quota/opportunities', {
			params: { status: statusFilter.value },
		})
		allRows.value = Array.isArray(data) ? data : []
		applyListFilter()
	} finally {
		listLoading.value = false
	}
}
function onSelectGroup(gid) {
	if (dirtyAny()) {
		confirmVisible.value = true
		pendingChange.value = { kind: 'group', value: gid }
		return
	}
	applyChange('group', gid)
}
function onSelectVersion(v) {
	if (dirtyAny()) {
		confirmVisible.value = true
		pendingChange.value = { kind: 'version', value: v }
		return
	}
	applyChange('version', v)
}

/* Create mode */
const createMode = ref(false)
const showBudgetTable = ref(false)

function startCreateMode() {
	if (dirtyAny()) {
		confirmVisible.value = true
		pendingChange.value = { kind: 'new' }
		return
	}
	enterCreateMode()
}
async function enterCreateMode() {
	createMode.value = true
	selectedGroupId.value = null
	selectedVersion.value = null
	versionOptions.value = []
	latestMeta.value = {}
	showBudgetTable.value = false

	opForm.value = {
		user_id: null,
		fiscal_year: new Date().getFullYear(),
		profit_center_code: null,
		volume: 0,
		probability_pct: 0,
		estimated_start_date: null,
		comments: '',
		potential_client_name: '',
		client_group_number: '',
		status: 'open',
	}
	opBaseline.value = cloneDeep(opForm.value)
	opMonthModel.value = null
	availableForSelected.value = 0

	await loadAssignedPcs()
	clientTakenPcs.value = []
	initBlankTable()
}

/* PCs del usuario + disponible */
const assignedPcOptions = ref([])
const availableForSelected = ref(0)
async function loadAssignedPcs() {
	await ensureCsrf()
	const fy = opForm.value?.fiscal_year || new Date().getFullYear()
	const { data } = await api.get('/api/extra-quota/assignments/my-profit-centers', {
		params: { fiscal_year: fy },
	})
	const rows = Array.isArray(data) ? data : []
	assignedPcOptions.value = rows
		.map((r) => {
			const code = Number(r.profit_center_code ?? 0)
			if (!code) return null
			const name = r.profit_center_name ?? r.pc_name ?? r.name ?? r.label ?? null
			return { label: name ? String(name) : `PC ${code}`, value: code }
		})
		.filter(Boolean)
}

function onlyPcName(label = '') {
	let s = String(label).trim()
	// si vino "123 — Nombre" o "123 - Nombre", quedate con el nombre
	const m = s.match(/^\s*\d+\s*[—-]\s*(.+)$/)
	if (m) s = m[1].trim()
	// si es un fallback tipo "PC 123", no es nombre real
	if (/^PC\s*\d+$/i.test(s)) return ''
	return s
}

function pcNameFromOptions(code) {
	const opt = assignedPcOptions.value.find((o) => Number(o.value) === Number(code))
	return onlyPcName(opt?.label || '')
}

const headerClient = computed(() => {
	if (selectedGroupId.value) {
		return latestMeta.value?.client_name || latestMeta.value?.potential_client_name || ''
	}
	if (createMode.value) return opForm.value.potential_client_name || ''
	return ''
})

const headerPc = computed(() => {
	const code = Number(
		opForm.value?.profit_center_code || latestMeta.value?.profit_center_code || 0,
	)
	// 1) preferimos el nombre que ya está en el dropdown del usuario
	const fromOptions = pcNameFromOptions(code)
	if (fromOptions) return fromOptions

	// 2) fallback a lo que venga del backend en meta
	const fromMeta = onlyPcName(
		latestMeta.value?.profit_center_name || latestMeta.value?.pc_name || '',
	)
	return fromMeta
})
async function updateAvailabilityForPc() {
	const code = Number(opForm.value.profit_center_code)
	const fy = opForm.value.fiscal_year
	availableForSelected.value = 0
	if (!code || !fy) return
	await ensureCsrf()
	const { data } = await api.get('/api/extra-quota/assignments/my-availability', {
		params: { profit_center_code: code, fiscal_year: fy },
	})
	availableForSelected.value = Number(data?.available || 0)
}

/* Kunde hat schon PCs → filtern */
const clientTakenPcs = ref([])
const pcFilteredWarning = ref('')
const pcOptionsForSelection = computed(() => {
	if (!clientTakenPcs.value.length) return assignedPcOptions.value
	const takenSet = new Set(clientTakenPcs.value.map((v) => String(v)))
	const out = assignedPcOptions.value.filter((o) => !takenSet.has(String(o.value)))
	pcFilteredWarning.value =
		out.length !== assignedPcOptions.value.length
			? 'Profitcenter, die der Kunde bereits hat, wurden ausgeblendet.'
			: ''
	if (opForm.value.profit_center_code && takenSet.has(String(opForm.value.profit_center_code))) {
		opForm.value.profit_center_code = null
	}
	return out
})
async function fetchClientTakenPcsIfPossible() {
	const num = parseInt(opForm.value.client_group_number || 0, 10)
	if (num >= 10000 && num <= 19999) {
		await ensureCsrf()
		const { data } = await api.get(`/api/extra-quota/clients/${num}/profit-centers`)
		clientTakenPcs.value = Array.isArray(data) ? data : []
	} else {
		clientTakenPcs.value = []
	}
}

/* Form */
const statusOpts = ref([
	{ label: 'Entwurf', value: 'draft' },
	{ label: 'Offen', value: 'open' },
	{ label: 'Gewonnen', value: 'won' },
	{ label: 'Verloren', value: 'lost' },
])

const wonDialogVisible = ref(false)
const finalizing = ref(false)

/* Cliente picker */
const clientSearchVisible = ref(false)
const clientSearchQuery = ref('')
const clientLoading = ref(false)
const allClients = ref([])
const filteredClients = computed(() => {
	const q = clientSearchQuery.value.trim().toLowerCase()
	if (!q) return allClients.value
	return allClients.value.filter((c) => {
		const name = String(c.name || '').toLowerCase()
		const num = String(c.client_number || '').toLowerCase()
		return name.includes(q) || num.includes(q)
	})
})
function normalizeClient(row) {
	const name = row?.name ?? row?.client_name ?? row?.company_name ?? ''
	const num = row?.client_number ?? row?.client_group_number ?? row?.cgn ?? row?.id ?? ''
	return {
		id: row?.id ?? num,
		name: String(name || ''),
		client_number: String(num || ''),
		classification_id: row?.classification_id ?? null,
	}
}
function escapeHtml(s) {
	return String(s).replace(
		/[&<>"']/g,
		(m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m],
	)
}
function renderClientRow(c) {
	const q = clientSearchQuery.value.trim()
	const base = `(${escapeHtml(c.client_number)}) ${escapeHtml(c.name)}`
	if (!q) return base
	const re = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi')
	return base.replace(re, (m) => `<mark>${escapeHtml(m)}</mark>`)
}
let clientSearchTimer = null
watch(clientSearchQuery, (q) => {
	if (clientSearchTimer) clearTimeout(clientSearchTimer)
	clientSearchTimer = setTimeout(() => fetchClientsRemote(q), 250)
})
async function fetchClientsRemote(q = '') {
	clientLoading.value = true
	try {
		await ensureCsrf()
		let data = []
		try {
			const res = await api.get('/api/extra-quota/clients', { params: { q } })
			data = Array.isArray(res.data) ? res.data : []
		} catch {
			try {
				const res2 = await api.get('/api/me/clients')
				data = Array.isArray(res2.data) ? res2.data : []
			} catch {
				data = []
			}
		}
		allClients.value = data.map(normalizeClient).filter((c) => c.client_number)
	} finally {
		clientLoading.value = false
	}
}
async function openClientPicker() {
	clientSearchQuery.value = ''
	await fetchClientsRemote('')
	clientSearchVisible.value = true
}
function pickExistingClient() {
	openClientPicker()
}

async function selectClient(client) {
	// Picker → existierender Kunde
	opForm.value.potential_client_name = client.name || ''
	opForm.value.client_group_number = client.client_number || ''
	clientSearchVisible.value = false
	await fetchClientTakenPcsIfPossible()
}

const opMonthModel = ref(null)
const suppressStatusWatch = ref(false)

const opForm = ref({
	user_id: null,
	fiscal_year: new Date().getFullYear(),
	profit_center_code: null,
	volume: 0,
	probability_pct: 0,
	estimated_start_date: null,
	comments: '',
	potential_client_name: '',
	client_group_number: '',
	status: 'open',
})
const opBaseline = ref(cloneDeep(opForm.value))
const opDirty = computed(() => {
	const active = !!selectedGroupId.value || !!createMode.value
	if (!active || !opBaseline.value) return false
	return JSON.stringify(opForm.value) !== JSON.stringify(opBaseline.value)
})
const canCreateBudget = computed(
	() =>
		!!opForm.value.potential_client_name &&
		Number(opForm.value.profit_center_code) > 0 &&
		Number(opForm.value.volume) > 0 &&
		!!opForm.value.estimated_start_date &&
		Number(opForm.value.probability_pct) > 0,
)
function syncMonthYear(d) {
	if (!d) {
		opForm.value.estimated_start_date = null
		return
	}
	const dt = new Date(d)
	const y = dt.getFullYear(),
		m = dt.getMonth() + 1
	opForm.value.estimated_start_date = `${y}-${String(m).padStart(2, '0')}-01`
	opForm.value.fiscal_year = m < 4 ? y - 1 : y
	loadAssignedPcs().then(updateAvailabilityForPc)
}
function snapProb() {
	const v = Number(opForm.value.probability_pct || 0)
	opForm.value.probability_pct = Math.min(100, Math.max(0, Math.round(v / 10) * 10))
}

/* Version guards */
const statusNormalized = computed(() => normStatus(opForm.value.status))
const maxVersion = ref(null)
const isLatestVersion = computed(() => {
	if (!selectedVersion.value || !maxVersion.value) return true
	return Number(selectedVersion.value) === Number(maxVersion.value)
})
const isReadOnly = computed(
	() =>
		statusNormalized.value === 'won' ||
		statusNormalized.value === 'lost' ||
		!isLatestVersion.value,
)
const isStatusMenuDisabled = computed(
	() =>
		!isLatestVersion.value ||
		statusNormalized.value === 'won' ||
		statusNormalized.value === 'lost',
)

/* Status change → open won modal */
watch(
	() => opForm.value.status,
	async (st, prev) => {
		if (suppressStatusWatch.value) return
		if (!st || st === prev) return
		if (!selectedGroupId.value && !createMode.value) return
		if (st === 'won') {
			opForm.value.probability_pct = 100
			await rebuildBudgetFromForm()
			wonDialogVisible.value = true
			return
		}
		if (st === 'lost') {
			const ok = window.confirm(
				'Diese Chance als „verloren“ schließen? (Menge wird freigegeben)',
			)
			if (!ok) {
				opForm.value.status = prev
				return
			}
			await finalizeLost()
		}
	},
)

/* Table & helpers */
const months = ref([])
const sales = ref(Array(12).fill(0))
const budget = ref([])
const forecast = ref([])
const baseBudget = ref([])
const baseForecast = ref([])
const tableLoading = ref(false)

function fiscalIndexFromCalMonth(calM) {
	const map = { 4: 1, 5: 2, 6: 3, 7: 4, 8: 5, 9: 6, 10: 7, 11: 8, 12: 9, 1: 10, 2: 11, 3: 12 }
	return map[calM] || 1
}
function calMonthFromFiscalIndex(idx) {
	return [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3][idx - 1] || 4
}
function ym(y, m) {
	return `${y}-${String(m).padStart(2, '0')}`
}
function fiscalMonths(fy) {
	return Array.from({ length: 12 }, (_, i) => {
		const m = calMonthFromFiscalIndex(i + 1)
		const y = m >= 4 ? fy : fy + 1
		return ym(y, m)
	})
}
function num0(v) {
	return Number(v || 0)
}
function isPastYM(ymStr) {
	if (!ymStr) return false
	const [yS, mS] = ymStr.split('-')
	const y = +yS,
		m = +mS
	const now = new Date()
	return new Date(y, m - 1, 1) < new Date(now.getFullYear(), now.getMonth(), 1)
}
function initBlankTable() {
	const fy = opForm.value.fiscal_year || new Date().getFullYear()
	months.value = fiscalMonths(fy)
	budget.value = Array(12).fill(0)
	forecast.value = Array(12).fill(0)
	baseBudget.value = [...budget.value]
	baseForecast.value = [...forecast.value]
}
const changedBudgetCount = computed(() =>
	budget.value.reduce((n, v, i) => n + (v !== baseBudget.value[i] ? 1 : 0), 0),
)
const changedForecastCount = computed(() =>
	forecast.value.reduce((n, v, i) => n + (v !== baseForecast.value[i] ? 1 : 0), 0),
)

/* Seasonality */
async function getSeasonalityForPc(code, fy) {
	const toNum = (x) => {
		if (x == null) return 0
		const s = String(x).trim().replace(',', '.')
		const n = Number(s)
		return isNaN(n) ? 0 : n
	}
	const parsePayload = (p) => {
		if (Array.isArray(p) && p.length === 12) return p.map(toNum)
		if (p && Array.isArray(p.weights) && p.weights.length === 12) return p.weights.map(toNum)
		if (Array.isArray(p)) {
			const out = Array(12).fill(0)
			for (const r of p) {
				const m = Number(r?.month ?? r?.m ?? 0)
				const v = toNum(r?.weight ?? r?.value ?? r?.v ?? 0)
				if (m >= 1 && m <= 12) out[m - 1] = v
			}
			return out
		}
		if (p && typeof p === 'object') {
			const out = Array(12).fill(0)
			const map = {
				apr: 1,
				may: 2,
				jun: 3,
				jul: 4,
				aug: 5,
				sep: 6,
				oct: 7,
				nov: 8,
				dec: 9,
				jan: 10,
				feb: 11,
				mar: 12,
			}
			for (const [k, idx] of Object.entries(map)) out[idx - 1] = toNum(p[k])
			return out
		}
		return Array(12).fill(1)
	}
	try {
		await ensureCsrf()
		const { data } = await api.get('/api/extra-quota/profit-centers/seasonality', {
			params: { profit_center_code: Number(code), fiscal_year: fy },
		})
		const arr = parsePayload(data)
		if (arr.some((v) => v > 0)) return arr
	} catch {
		// ignore
	}
	return Array(12).fill(1)
}

/* Budget recompute helper */
async function rebuildBudgetFromForm() {
	const amt = Math.max(0, Math.round(num0(opForm.value.volume)))
	const pct = Math.max(0, Math.round(num0(opForm.value.probability_pct)))
	const expected = Math.round(amt * (pct / 100))
	const fy = Number(opForm.value.fiscal_year || new Date().getFullYear())
	months.value = fiscalMonths(fy)
	const seasonal12 = await getSeasonalityForPc(Number(opForm.value.profit_center_code), fy)

	const [, mS] = String(opForm.value.estimated_start_date || '').split('-')
	const calStart = Number(mS || '0')
	const startIdx = fiscalIndexFromCalMonth(calStart)
	const indices = []
	for (let i = startIdx; i <= 12; i++) indices.push(i)

	const w = indices.map((fi) => Math.max(0, Number(seasonal12[fi - 1] || 0)))
	const sumW = w.reduce((a, b) => a + b, 0)

	const newBudget = Array(12).fill(0)
	if (!sumW) {
		const base = indices.length ? Math.floor(expected / indices.length) : 0
		let rest = expected - base * indices.length
		indices.forEach((fi) => {
			newBudget[fi - 1] = base
		})
		for (let k = 0; k < indices.length && rest > 0; k++, rest--) newBudget[indices[k] - 1] += 1
	} else {
		const raw = w.map((val) => expected * (val / sumW))
		const base = raw.map(Math.floor)
		let rest = Math.round(raw.reduce((a, b) => a + b, 0)) - base.reduce((a, b) => a + b, 0)
		const order = raw.map((v, i) => ({ i, frac: v - base[i] })).sort((a, b) => b.frac - a.frac)
		for (let k = 0; k < order.length && rest > 0; k++, rest--) base[order[k].i] += 1
		indices.forEach((fi, k) => {
			newBudget[fi - 1] = base[k]
		})
	}
	budget.value = newBudget
	baseBudget.value = newBudget.slice()
}

/* Scale forecast */
function scaleForecastByRatio() {
	const oldAmt = Math.max(0, Math.round(num0(opBaseline.value.volume)))
	const oldPct = Math.max(0, Math.round(num0(opBaseline.value.probability_pct)))
	const newAmt = Math.max(0, Math.round(num0(opForm.value.volume)))
	const newPct = Math.max(0, Math.round(num0(opForm.value.probability_pct)))
	const oldExp = oldAmt * (oldPct / 100)
	const newExp = newAmt * (newPct / 100)
	if (oldExp <= 0) {
		forecast.value = forecast.value.map((v, i) =>
			isPastYM(months.value[i]) ? v : budget.value[i],
		)
		return
	}
	const r = newExp / oldExp
	const raw = forecast.value.map((v) => v * r)
	const base = raw.map(Math.floor)
	let rest = Math.round(raw.reduce((a, b) => a + b, 0)) - base.reduce((a, b) => a + b, 0)
	const order = raw.map((v, i) => ({ i, frac: v - base[i] })).sort((a, b) => b.frac - a.frac)
	for (let k = 0; k < order.length && rest > 0; k++, rest--) base[order[k].i] += 1
	forecast.value = base
}

/* Create (pre-check Kunde+PC → Forecast) */
const preCreateConflictVisible = ref(false)
async function onGenerateBudget() {
	if (!canCreateBudget.value) return

	const cgNum = parseInt(opForm.value.client_group_number || 0, 10)
	const pc = Number(opForm.value.profit_center_code)
	if (cgNum >= 10000 && cgNum <= 19999 && pc) {
		await ensureCsrf()
		const { data } = await api.get('/api/extra-quota/clients/exists-in-pc', {
			params: { client_group_number: cgNum, profit_center_code: pc },
		})
		if (data?.exists) {
			preCreateConflictVisible.value = true
			return
		}
	}

	await rebuildBudgetFromForm()
	forecast.value = Array(12).fill(0)
	baseForecast.value = forecast.value.slice()
	showBudgetTable.value = true

	if (createMode.value && !selectedGroupId.value) {
		await ensureCsrf()
		const payload = {
			fiscal_year: opForm.value.fiscal_year,
			profit_center_code: Number(opForm.value.profit_center_code),
			volume: Math.max(0, Math.round(num0(opForm.value.volume))),
			probability_pct: Math.max(0, Math.round(num0(opForm.value.probability_pct))),
			estimated_start_date: opForm.value.estimated_start_date,
			comments: opForm.value.comments,
			potential_client_name: opForm.value.potential_client_name,
			client_group_number: opForm.value.client_group_number,
			status: opForm.value.status || 'open',
		}
		const { data } = await api.post('/api/extra-quota/opportunities', payload)
		selectedGroupId.value = Number(data?.opportunity_group_id)
		selectedVersion.value = Number(data?.version || 1)
		createMode.value = false
		await saveBudget({ silent: true })
		await loadList()
		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Chance erstellt (v1)',
			life: 1400,
		})
		opBaseline.value = cloneDeep(opForm.value)
	}
}
function cancelCreateAndReset() {
	preCreateConflictVisible.value = false
	enterCreateMode()
	toast.add({
		severity: 'warn',
		summary: 'Abgebrochen',
		detail: 'Dies ist Forecast. Vorgang abgebrochen.',
		life: 1800,
	})
}
function acceptForecastAndAbort() {
	preCreateConflictVisible.value = false
	toast.add({
		severity: 'info',
		summary: 'Info',
		detail: 'Nutze den vorhandenen Forecast für diesen Kunden/PC.',
		life: 1800,
	})
}

/* Backend meta/series */
const versionOptions = ref([])
async function loadGroupMeta() {
	if (!selectedGroupId.value) return
	await ensureCsrf()

	suppressStatusWatch.value = true
	try {
		const { data } = await api.get(`/api/extra-quota/opportunities/${selectedGroupId.value}`)
		const latest = data?.latest || {}
		latestMeta.value = latest

		const vers = Array.isArray(data?.versions)
			? data.versions.map((v) => Number(v.version)).sort((a, b) => a - b)
			: []
		versionOptions.value = vers.map((v) => ({ value: v, label: `v${v}` }))
		selectedVersion.value = vers.length ? vers[vers.length - 1] : 1
		maxVersion.value = vers.length ? vers[vers.length - 1] : selectedVersion.value

		opForm.value = {
			user_id: latest.user_id ?? null,
			fiscal_year: latest.fiscal_year ?? new Date().getFullYear(),
			profit_center_code:
				latest.profit_center_code != null ? Number(latest.profit_center_code) : null,
			volume: Math.round(num0(latest.volume)),
			probability_pct: Math.round(num0(latest.probability_pct)),
			estimated_start_date: latest.estimated_start_date ?? null,
			comments: latest.comments ?? '',
			potential_client_name: latest.potential_client_name ?? '',
			client_group_number: latest.client_group_number ?? '',
			status: latest.status || 'open',
		}
		opBaseline.value = cloneDeep(opForm.value)
		opMonthModel.value = opForm.value.estimated_start_date
			? new Date(opForm.value.estimated_start_date)
			: null

		await loadAssignedPcs()
		await updateAvailabilityForPc()
		await fetchClientTakenPcsIfPossible()
		await loadSeries()
	} finally {
		await nextTick()
		suppressStatusWatch.value = false
	}
}
async function loadSeries() {
	if (!selectedGroupId.value || !selectedVersion.value) {
		initBlankTable()
		return
	}
	tableLoading.value = true
	try {
		await ensureCsrf()
		const [bRes, fRes] = await Promise.all([
			api.get(`/api/extra-quota/budget/${selectedGroupId.value}/${selectedVersion.value}`, {
				params: { fiscal_year: opForm.value.fiscal_year },
			}),
			api.get(`/api/extra-quota/forecast/${selectedGroupId.value}/${selectedVersion.value}`, {
				params: { fiscal_year: opForm.value.fiscal_year },
			}),
		])
		const b = Array.isArray(bRes.data) ? bRes.data : []
		const f = Array.isArray(fRes.data) ? fRes.data : []
		months.value = b.length
			? b.map((r) => ym(r.fiscal_year, r.month))
			: fiscalMonths(opForm.value.fiscal_year)
		budget.value = b.length ? b.map((r) => Math.round(num0(r.volume))) : Array(12).fill(0)
		forecast.value = f.length ? f.map((r) => Math.round(num0(r.volume))) : Array(12).fill(0)
		baseBudget.value = [...budget.value]
		baseForecast.value = [...forecast.value]
	} finally {
		tableLoading.value = false
	}
}

/* Edits */
function onEditForecastInt({ index, value }) {
	if (isReadOnly.value) return
	const n = Math.max(0, Math.round(Number(value) || 0))
	forecast.value[index] = n
}

/* Save flows */
async function saveNewVersion() {
	if (!selectedGroupId.value) return

	const mustRebuild =
		opBaseline.value.volume !== opForm.value.volume ||
		opBaseline.value.probability_pct !== opForm.value.probability_pct ||
		opBaseline.value.estimated_start_date !== opForm.value.estimated_start_date

	if (mustRebuild) {
		await rebuildBudgetFromForm()
		scaleForecastByRatio()
	}

	await ensureCsrf()
	const payload = {
		fiscal_year: opForm.value.fiscal_year,
		profit_center_code: Number(opForm.value.profit_center_code),
		volume: Math.max(0, Math.round(num0(opForm.value.volume))),
		probability_pct: Math.max(0, Math.round(num0(opForm.value.probability_pct))),
		estimated_start_date: opForm.value.estimated_start_date,
		comments: opForm.value.comments,
		potential_client_name: opForm.value.potential_client_name,
		client_group_number: opForm.value.client_group_number,
		status: opForm.value.status || 'open',
	}
	const { data } = await api.post(
		`/api/extra-quota/opportunities/${selectedGroupId.value}/version`,
		payload,
	)
	selectedVersion.value = Number(data?.version || selectedVersion.value || 1)
	maxVersion.value = selectedVersion.value
	opBaseline.value = cloneDeep(opForm.value)

	await saveBudget({ silent: true })
	if (mustRebuild) await saveForecast({ silent: true })

	toast.add({ severity: 'success', summary: 'Gespeichert', detail: 'Aktualisiert', life: 1400 })
	await loadGroupMeta()
}
async function saveBudget(opts = {}) {
	if (!selectedGroupId.value) return
	await ensureCsrf()
	const items = months.value.map((ymStr, i) => {
		const [y, m] = ymStr.split('-').map((n) => parseInt(n, 10))
		return { month: m, fiscal_year: y, volume: Number(budget.value[i] || 0) }
	})
	await api.post(
		`/api/extra-quota/budget/${selectedGroupId.value}/${selectedVersion.value}/save`,
		{ items },
	)
	baseBudget.value = [...budget.value]
	if (!opts.silent)
		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Budget gespeichert',
			life: 1400,
		})
}
async function saveForecast(opts = {}) {
	if (!selectedGroupId.value) return
	await ensureCsrf()
	const items = months.value.map((ymStr, i) => {
		const [y, m] = ymStr.split('-').map((n) => parseInt(n, 10))
		return { month: m, fiscal_year: y, volume: Number(forecast.value[i] || 0) }
	})
	await api.post(
		`/api/extra-quota/forecast/${selectedGroupId.value}/${selectedVersion.value}/save`,
		{ items },
	)
	baseForecast.value = [...forecast.value]
	if (!opts.silent)
		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Forecast gespeichert',
			life: 1400,
		})
}

/* WON: funciones usadas por el modal */
async function lookupClientByNumber(num) {
	try {
		await ensureCsrf()
		const { data } = await api.get(`/api/extra-quota/clients/by-number/${num}`)
		if (!data) return null
		const name = data?.name ?? data?.client_name ?? ''
		const number = data?.client_number ?? data?.client_group_number ?? num
		return {
			id: data.id,
			name,
			client_number: number,
			classification_id: data.classification_id,
		}
	} catch {
		return null
	}
}
async function checkClientPcExists(cgNum, pc) {
	try {
		await ensureCsrf()
		const { data } = await api.get('/api/extra-quota/clients/exists-in-pc', {
			params: { client_group_number: cgNum, profit_center_code: Number(pc) },
		})
		return !!data?.exists
	} catch {
		return false
	}
}
function scaleForecastForWinning() {
	const oldAmt = Math.max(0, Math.round(num0(opBaseline.value?.volume ?? opForm.value.volume)))
	const oldPct = Math.max(0, Math.round(num0(opBaseline.value?.probability_pct ?? 0)))
	const newAmt = Math.max(0, Math.round(num0(opForm.value.volume)))
	const newPct = 100
	const oldExp = oldAmt * (oldPct / 100)
	const newExp = newAmt * (newPct / 100)

	if (oldExp <= 0) {
		forecast.value = forecast.value.map((v, i) =>
			isPastYM(months.value[i]) ? v : budget.value[i],
		)
		return
	}
	const r = newExp / oldExp
	const raw = forecast.value.map((v, i) => (isPastYM(months.value[i]) ? v : v * r))
	const base = raw.map((v) => Math.floor(v))
	let rest = Math.round(raw.reduce((a, b) => a + b, 0)) - base.reduce((a, b) => a + b, 0)
	const order = raw
		.map((v, i) => ({ i, frac: v - base[i] }))
		.filter((o) => !isPastYM(months.value[o.i]))
		.sort((a, b) => b.frac - a.frac)
	for (let k = 0; k < order.length && rest > 0; k++, rest--) base[order[k].i] += 1
	forecast.value = base
}

/* Handlers de eventos del modal */
async function onModalFinalize({ client_group_number, client_name, classification_id }) {
	if (!selectedGroupId.value || !selectedVersion.value) {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Gruppe/Version fehlt.',
			life: 1800,
		})
		return
	}
	finalizing.value = true
	try {
		// Escalar forecast a 100% y versionar si corresponde
		const mustVersion =
			opBaseline.value.volume !== opForm.value.volume ||
			100 !== opBaseline.value.probability_pct ||
			opBaseline.value.estimated_start_date !== opForm.value.estimated_start_date

		scaleForecastForWinning()

		if (mustVersion) {
			await ensureCsrf()
			const payload = {
				fiscal_year: opForm.value.fiscal_year,
				profit_center_code: Number(opForm.value.profit_center_code),
				volume: Math.max(0, Math.round(Number(opForm.value.volume) || 0)),
				probability_pct: 100,
				estimated_start_date: opForm.value.estimated_start_date,
				comments: opForm.value.comments,
				potential_client_name: opForm.value.potential_client_name,
				client_group_number: opForm.value.client_group_number,
				status: opBaseline.value.status || 'open',
			}
			const { data } = await api.post(
				`/api/extra-quota/opportunities/${selectedGroupId.value}/version`,
				payload,
			)
			selectedVersion.value = Number(data?.version || selectedVersion.value || 1)
			maxVersion.value = selectedVersion.value
			await saveBudget({ silent: true })
			await saveForecast({ silent: true })
			opBaseline.value = JSON.parse(JSON.stringify({ ...opForm.value, probability_pct: 100 }))
		}

		// Finaliza como WON
		await ensureCsrf()
		await api.post(
			`/api/extra-quota/opportunities/${selectedGroupId.value}/${selectedVersion.value}/finalize`,
			{
				status: 'won',
				client_group_number,
				client_name,
				classification_id,
			},
		)

		// Refrescos UI
		suppressStatusWatch.value = true
		opForm.value.status = 'won'
		await nextTick()
		suppressStatusWatch.value = false

		wonDialogVisible.value = false
		opBaseline.value = cloneDeep(opForm.value)
		baseBudget.value = [...budget.value]
		baseForecast.value = [...forecast.value]

		toast.add({
			severity: 'success',
			summary: 'Überführt',
			detail: 'In Stamm-Budget übernommen',
			life: 1600,
		})
		await loadList()
		selectedGroupId.value = null
		selectedVersion.value = null
		enterCreateMode()
	} catch (e) {
		const msg =
			e?.response?.data?.message || e?.message || 'Fehler beim Finalisieren (gewonnen)'
		toast.add({ severity: 'error', summary: 'Fehler', detail: msg, life: 2200 })
	} finally {
		finalizing.value = false
	}
}

async function onModalMerge({ client_group_number }) {
	try {
		const cgNum = Number(client_group_number)
		const pc = Number(opForm.value.profit_center_code)
		const fy = Number(opForm.value.fiscal_year)

		const items = months.value
			.map((ymStr, i) => {
				const [y, m] = ymStr.split('-').map((n) => parseInt(n, 10))
				return { month: m, fiscal_year: y, volume: Number(forecast.value[i] || 0) }
			})
			.filter((r) => !isPastYM(ym(r.fiscal_year, r.month)) && r.volume > 0)

		if (!items.length) throw new Error('Kein zukünftiger Forecast zum Addieren.')

		await ensureCsrf()
		await api.post('/api/extra-quota/forecast/merge', {
			client_group_number: cgNum,
			profit_center_code: pc,
			fiscal_year: fy,
			items,
		})

		toast.add({
			severity: 'success',
			summary: 'Forecast',
			detail: 'Forecast zum bestehenden Kunden hinzugefügt.',
			life: 1600,
		})
		wonDialogVisible.value = false
		suppressStatusWatch.value = true
		opForm.value.status = 'open'
		await nextTick()
		suppressStatusWatch.value = false
	} catch (e) {
		const msg =
			e?.response?.data?.message || e?.message || 'Fehler beim Hinzufügen des Forecasts'
		toast.add({ severity: 'error', summary: 'Fehler', detail: msg, life: 2200 })
	}
}

/* Lost flow */
async function finalizeLost() {
	if (!selectedGroupId.value || !selectedVersion.value) return
	try {
		const mustVersion =
			opBaseline.value.volume !== opForm.value.volume ||
			opBaseline.value.probability_pct !== opForm.value.probability_pct ||
			opBaseline.value.estimated_start_date !== opForm.value.estimated_start_date

		if (mustVersion) {
			await rebuildBudgetFromForm()
			await ensureCsrf()
			const payload = {
				fiscal_year: opForm.value.fiscal_year,
				profit_center_code: Number(opForm.value.profit_center_code),
				volume: Math.max(0, Math.round(Number(opForm.value.volume) || 0)),
				probability_pct: Math.max(0, Math.round(Number(opForm.value.probability_pct) || 0)),
				estimated_start_date: opForm.value.estimated_start_date,
				comments: opForm.value.comments,
				potential_client_name: opForm.value.potential_client_name,
				client_group_number: opForm.value.client_group_number,
				status: opBaseline.value.status || 'open',
			}
			const { data } = await api.post(
				`/api/extra-quota/opportunities/${selectedGroupId.value}/version`,
				payload,
			)
			selectedVersion.value = Number(data?.version || selectedVersion.value || 1)
			maxVersion.value = selectedVersion.value
			await saveBudget({ silent: true })
			if (changedForecastCount.value > 0) await saveForecast({ silent: true })
			opBaseline.value = JSON.parse(JSON.stringify(opForm.value))
		}

		await ensureCsrf()
		await api.post(
			`/api/extra-quota/opportunities/${selectedGroupId.value}/${selectedVersion.value}/finalize`,
			{ status: 'lost' },
		)

		suppressStatusWatch.value = true
		opForm.value.status = 'lost'
		await nextTick()
		suppressStatusWatch.value = false

		opBaseline.value = cloneDeep(opForm.value)
		baseBudget.value = [...budget.value]
		baseForecast.value = [...forecast.value]

		toast.add({
			severity: 'success',
			summary: 'Geschlossen',
			detail: 'Menge freigegeben',
			life: 1400,
		})
		await loadList()
		selectedGroupId.value = null
		selectedVersion.value = null
		enterCreateMode()
	} catch (e) {
		const msg =
			e?.response?.data?.message || e?.message || 'Fehler beim Finalisieren (verloren)'
		toast.add({ severity: 'error', summary: 'Fehler', detail: msg, life: 2200 })
	}
}

/* Guards */
function dirtyAny() {
	const active = !!selectedGroupId.value || !!createMode.value
	if (!active) return false
	if (isReadOnly.value) return false
	return opDirty.value || changedBudgetCount.value > 0 || changedForecastCount.value > 0
}
async function saveAndApply() {
	try {
		if (selectedGroupId.value && opDirty.value) await saveNewVersion()
		if (selectedGroupId.value && changedForecastCount.value > 0) await saveForecast()
	} finally {
		confirmVisible.value = false
		if (pendingChange.value) {
			const { kind, value } = pendingChange.value
			applyChange(kind, value)
		}
		pendingChange.value = null
	}
}
function discardAndApply() {
	if (opBaseline.value) opForm.value = cloneDeep(opBaseline.value)
	budget.value = [...baseBudget.value]
	forecast.value = [...baseForecast.value]
	confirmVisible.value = false
	if (pendingChange.value) {
		const { kind, value } = pendingChange.value
		applyChange(kind, value)
	}
	pendingChange.value = null
}
function applyChange(kind, value) {
	if (kind === 'new') {
		enterCreateMode()
		return
	}
	if (kind === 'group') {
		selectedGroupId.value = Number(value) || null
		selectedVersion.value = null
		createMode.value = false
		showBudgetTable.value = false
		loadGroupMeta()
		return
	}
	if (kind === 'version') {
		selectedVersion.value = Number(value) || null
		loadSeries().then(() => {
			showBudgetTable.value = true
		})
		return
	}
}

/* Reaktionen */
watch(
	() => opForm.value.profit_center_code,
	() => {
		updateAvailabilityForPc()
	},
)
watch(
	() => opForm.value.client_group_number,
	async () => {
		await fetchClientTakenPcsIfPossible()
	},
)

/* Mount */
onMounted(async () => {
	await loadAssignedPcs()
	await loadList()
})
</script>

<style scoped>
.eqp-grid {
	--gap: 16px;
	display: grid;
	grid-template-columns: 2fr 10fr;
	gap: var(--gap);
	height: 100%;
	box-sizing: border-box;
	overflow: hidden;
}

/* Sidebar */
.eqp-aside {
	display: flex;
	min-height: 0;
	height: 100%; /* crítico para que los hijos puedan scrollear */
}

.eqp-aside :deep(.p-card) {
	flex: 1 1 auto;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.eqp-aside :deep(.p-card-body),
.eqp-aside :deep(.p-card-content) {
	flex: 1 1 auto;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

/* El header de filtros no crece */
.status-filter {
	--sf-gap: 8px; /* un único valor para todo */
	display: grid;
	grid-template-columns: repeat(3, 1fr); /* 3 columnas iguales */
	gap: var(--sf-gap); /* separación entre botones */
	margin-bottom: var(--sf-gap); /* misma separación con la lista */
}

.status-filter :deep(.p-button) {
	width: 100%; /* que cada botón llene su columna */
}

/* Contenedor que ocupa el resto y scrollea */
.eqp-aside .listbox-flex {
	flex: 1 1 auto;
	min-height: 0;
	overflow: auto; /* scroll acá */
}

/* Asegurar que la Listbox use el alto del contenedor y su UL scrollee bien */
.eqp-aside .listbox-flex :deep(.p-listbox) {
	height: 100%;
}

.eqp-aside .listbox-flex :deep(.p-listbox-list-wrapper) {
	height: 100%;
	max-height: none; /* evita quedar atado a 65vh */
	overflow: auto;
}

/* Main */
.eqp-main {
	display: grid;
	grid-template-rows: auto 1fr auto;
	grid-template-columns: 12fr;
	gap: var(--gap);
	min-height: 0;
}

/* Header */
.topbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.title-left {
	display: flex;
	flex-direction: column;
}
.title-line {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}
.eyebrow {
	font-size: 0.8rem;
	color: var(--text-muted);
	text-transform: uppercase;
	margin-bottom: 4px;
}

.title-line .sep {
	opacity: 0.5;
}

/* Fila: form (10) + versiones (2) */
.row-form-extras {
	display: grid;
	grid-template-columns: 10fr 2fr;
	gap: var(--gap);
	min-height: 0;
}

/* Form structure */
.form-card {
	min-height: 0;
}
.form-grid {
	display: flex;
	flex-direction: column;
	gap: 12px;
	height: 100%;
	min-height: 0;
}
.top-row {
	display: grid;
	grid-template-columns: minmax(0, 2fr) minmax(220px, 1fr);
	gap: 12px;
	border-bottom: 1px solid var(--surface-border);
	padding-bottom: 8px;
}
.field {
	display: flex;
	flex-direction: column;
	gap: 6px;
}
.lbl {
	font-weight: 600;
	color: var(--text-color);
}
.inline {
	display: flex;
	align-items: center;
	gap: 8px;
}

/* Two columns */
.two-cols {
	display: grid;
	grid-template-columns: 34% 1fr;
	gap: 12px;
	min-height: 0;
}
.left {
	display: flex;
	flex-direction: column;
	gap: 10px;
	min-height: 0;
}
.right {
	display: grid;
	grid-template-rows: 1fr auto;
	gap: 10px;
	min-height: 0;
}
.field.grow {
	min-height: 0;
}
.comment-box {
	min-height: 180px;
}
.actions-right {
	justify-self: end;
}

/* Profitcenter availability */
.vol-inline {
	display: flex;
	align-items: center;
	gap: 8px;
}
.assigned {
	white-space: nowrap;
	color: var(--text-muted);
}

/* Slider ticks */
.prob-wrap {
	display: flex;
	align-items: center;
	width: 100%;
	gap: 8px;
}

.prob-slider {
	width: 100%;
}

.tickbar {
	width: 100%;
	height: 6px;
	margin-top: 4px;
	position: relative;
}

.tickbar::before {
	content: '';
	position: absolute;
	inset: 0;
	background-image: repeating-linear-gradient(
		to right,
		color-mix(in oklab, var(--text-color) 35%, transparent) 0,
		color-mix(in oklab, var(--text-color) 35%, transparent) 1px,
		transparent 1px,
		transparent 10%
	);
	opacity: 0.5;
}

/* Versiones */
.versions-card {
	min-height: 0;
	display: flex;
	flex-direction: column;
}
.versions-meta {
	border-top: 1px solid var(--surface-border);
	padding-top: 6px;
	margin-top: 6px;
	font-size: 0.875rem;
	color: var(--text-muted);
}

/* Tabla */
.table-card {
	grid-column: 1 / -1;
	height: clamp(240px, 25vh, 360px);
	overflow: hidden;
	display: flex;
	flex-direction: column;
}
.table-card :deep(table) {
	table-layout: fixed;
	width: 100%;
}
.ctbl-wrap.locked {
	pointer-events: none;
	opacity: 0.6;
}

/* Lista izquierda */
.status-filter {
	display: flex;
	gap: 6px;
}
.row-item {
	display: flex;
	flex-direction: column;
	border-radius: 8px;
	cursor: pointer;
}
.row-item:hover {
	background: rgba(255, 255, 255, 0.06);
}

.top {
	display: flex;
	justify-content: space-between;
	font-size: 12px;
	color: #000;
}

.mid {
	font-weight: 600;
	color: #000;
}

.bot {
	display: flex;
	justify-content: space-between;
	font-size: 12px;
	color: #000;
}

/* Cliente picker */
.client-list-wrap {
	max-height: 420px;
	overflow: auto;
}
.client-row {
	padding: 12px 10px;
	cursor: pointer;
	border-radius: 10px;
	margin-bottom: 6px;
	border: 1px solid rgba(0, 0, 0, 0.08);
}
.client-row:hover {
	background: rgba(0, 0, 0, 0.04);
}
.cr-name {
	font-size: 16px;
	font-weight: 600;
}

/* Loader muy simple */
.local-loader {
	padding: 12px;
	text-align: center;
	color: var(--text-muted);
}

/* Responsive */
@media (max-width: 1199px) {
	.eqp-grid {
		grid-template-columns: 1fr;
	}
	.eqp-aside {
		grid-row: auto;
	}
	.row-form-extras {
		grid-template-columns: 1fr;
	}
}
</style>
