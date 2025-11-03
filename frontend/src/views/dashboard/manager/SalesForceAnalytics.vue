<template>
	<Toast />

	<!-- GRID PRINCIPAL -->
	<div class="sales-force-analysis-grid">
		<!-- ASIDE: Lista de vendedores -->
		<aside class="filters-col">
			<Card class="filters-card">
				<template #content>
					<Listbox v-model="selectedSeller" :options="sellerItems" optionValue="id"
						optionLabel="__displayName" dataKey="id" class="seller-listbox"  listStyle="max-height:100%">
						<template #option="{ option, selected }">
							<div :class="['seller-row', { selected }]">
								<div :class="['avatar-ring', teamClass(option)]">
									<Avatar v-if="option.profile_picture" :image="option.profile_picture" shape="circle"
										size="large" />
									<Avatar v-else :label="initials(option.__displayName)" shape="circle" size="large"
										class="avatar-initials" />
								</div>
								<span class="seller-name">{{ option.__displayName }}</span>
							</div>
						</template>
						<template #empty>
							<div class="empty">Keine Einträge.</div>
						</template>
					</Listbox>
				</template>
			</Card>
		</aside>


		<!-- CONTENIDO PRINCIPAL -->
		<main class="content-col">
			<!-- TOPBAR -->
			<Card class="topbar-card">
				<template #content>
					<div class="topbar-inner">
						<h3 class="m-0">Übersicht</h3>
						<div class="flex align-items-center gap-2">
							<Button icon="pi pi-chevron-left" text rounded @click="shiftPeriod(-1)" />
							<span class="pill">
								<i class="pi pi-calendar mr-2" />{{ periodLabel }}
							</span>
							<Button icon="pi pi-chevron-right" text rounded @click="shiftPeriod(1)" />
						</div>
					</div>
				</template>
			</Card>

			<!-- MAIN ROW (8 + 4 columnas) -->
			<div class="charts-row">
				<!-- IZQ: Tabla de desvíos -->
				<Card class="chart-card chart-lg">
					<template #header>
						<div class="flex align-items-center justify-content-between flex-wrap">
							<h3 class="m-0">Abweichungsbegründungen</h3>
							<div class="flex align-items-center gap-2">
								<Tag severity="success" value="Fristgerecht" rounded />
								<strong>{{ kpiInTerm }}</strong>
								<span class="text-500">|</span>
								<Tag severity="warning" value="Verspätet" rounded />
								<strong>{{ kpiOutTerm }}</strong>
							</div>
						</div>
					</template>
					<template #content>
						<DataTable :value="deviationsSorted" responsiveLayout="scroll" :rows="10" paginator
							paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
							currentPageReportTemplate="{first}–{last} von {totalRecords}" :loading="isLoadingDevs"
							@row-click="onDeviationRowClick">
							<Column header="Typ" style="width: 140px">
								<template #body="{ data: r }">
									<Tag :severity="String(r.type).toLowerCase() === 'forecast' ? 'warning' : 'info'"
										:value="String(r.type).toLowerCase() === 'forecast' ? 'Forecast' : 'Ist'"
										rounded />
								</template>
							</Column>

							<Column header="Profitcenter" field="pcName" style="width: 280px" />

							<Column header="Delta" style="width: 200px">
								<template #body="{ data: r }">
									<span :class="Number(r.deltaAbs || 0) >= 0 ? 'text-green-500' : 'text-red-500'">
										{{ fmtAmount(Number(r.deltaAbs || 0), unitOf(r.pcCode)) }}
									</span>
								</template>
							</Column>

							<Column header="Plan" style="width: 130px">
								<template #body="{ data: r }">
									<Tag :severity="hasPlan(r) ? 'success' : 'secondary'"
										:value="hasPlan(r) ? 'Plan' : 'Kein Plan'" rounded />
								</template>
							</Column>

							<Column header="Status" style="width: 160px">
								<template #body="{ data: r }">
									<Tag :severity="isOverdue(r)
										? 'danger'
										: r.justified
											? 'success'
											: 'warning'" :value="isOverdue(r)
												? 'Überfällig'
												: r.justified
													? 'Begründet'
													: 'Offen'" rounded />
								</template>
							</Column>

							<Column header="" style="width: 120px">
								<template #body="{ data: r }">
									<Button label="Ansehen" text size="small" @click.stop="openDeviation(r)" />
								</template>
							</Column>

							<template #empty>
								<div class="text-center text-500 p-3">Keine Daten für den Zeitraum.</div>
							</template>
						</DataTable>
					</template>
				</Card>

				<!-- DER: Profitcenter + ExtraQuota -->
				<div class="chart-card chart-sm flex flex-column gap-3">
					<Card>
						<template #header>Profitcenter</template>
						<template #content>
							<PcOverviewManager :userId="selectedSeller" unit="M3" :period="null" />
						</template>
					</Card>

					<Card>
						<template #header>
							<div class="flex justify-content-end">
								<Button label="Anzeigen" text size="small" @click="showXQAnalysis = true" />
							</div>
						</template>
						<template #content>
							<ExtraQuotaCard title="Zusatzquote" unit="m³" :target="xq.target" :achieved="xq.achieved"
								:mix="xq.mix" :items="xq.items" :pcDetail="xq.pcDetail" :currentUserId="selectedSeller"
								:showMoreButton="false" />
						</template>
					</Card>
				</div>
			</div>
		</main>
	</div>

	<!-- DIALOG: Desvío Detalle -->
	<Dialog v-model:visible="showDeviationModal" modal appendTo="body" :draggable="false" :blockScroll="true"
		header="Abweichung · Detail" style="width: min(900px, 96vw)">
		<template v-if="activeDeviation">
			<div class="flex justify-content-between align-items-center mb-3 flex-wrap">
				<div class="font-bold flex align-items-center flex-wrap gap-2">
					<i class="pi pi-sliders-h" />
					<span>{{ activeDeviation.pcName || '—' }}</span>
					<span class="text-500">·</span>
					<span>{{ typeLabel(activeDeviation.type) }}</span>
					<span class="text-500">·</span>
					<span>{{ periodText(activeDeviation.year, activeDeviation.month) }}</span>
				</div>
				<Tag :severity="isOverdue(activeDeviation)
					? 'danger'
					: activeDeviation.justified
						? 'success'
						: 'warning'" :value="isOverdue(activeDeviation)
							? 'Überfällig'
							: activeDeviation.justified
								? 'Begründet'
								: 'Offen'" rounded />
			</div>

			<div class="grid">
				<!-- IZQ -->
				<div class="col-12 md:col-6">
					<Card class="mb-3">
						<template #header>Kennzahlen</template>
						<template #content>
							<ul class="list-none p-0 m-0 flex flex-column gap-2">
								<li class="flex justify-content-between">
									<span>Umsatz</span>
									<strong>{{ fmtAmount(activeDeviation.sales, unitOf(activeDeviation.pcCode))
										}}</strong>
								</li>
								<li class="flex justify-content-between">
									<span>Budget</span>
									<strong>{{ fmtAmount(activeDeviation.budget, unitOf(activeDeviation.pcCode))
										}}</strong>
								</li>
								<li class="flex justify-content-between">
									<span>Forecast</span>
									<strong>{{ fmtAmount(activeDeviation.forecast, unitOf(activeDeviation.pcCode))
										}}</strong>
								</li>
								<li class="flex justify-content-between">
									<span>Delta</span>
									<strong
										:class="(activeDeviation.deltaAbs ?? 0) >= 0 ? 'text-green-500' : 'text-red-500'">
										{{ fmtAmount(activeDeviation.deltaAbs, unitOf(activeDeviation.pcCode)) }}
										<small>({{ Math.round(activeDeviation.deltaPct ?? 0) }}%)</small>
									</strong>
								</li>
							</ul>
						</template>
					</Card>

					<Card>
						<template #header>Begründung</template>
						<template #content>
							<p v-if="activeDeviation.comment?.trim().length" class="font-mono white-space-pre-line">
								{{ activeDeviation.comment }}
							</p>
							<p v-else class="text-500">Keine Begründung angegeben.</p>
							<div class="text-500 text-sm mt-2"
								v-if="activeDeviation.justAuthor || activeDeviation.justDate">
								<i class="pi pi-user mr-1" v-if="activeDeviation.justAuthor" />{{
									activeDeviation.justAuthor || '' }}
								<span v-if="activeDeviation.justAuthor && activeDeviation.justDate"
									class="mx-1">·</span>
								<i class="pi pi-calendar mr-1" v-if="activeDeviation.justDate" />{{
									activeDeviation.justDate ? fmtDate(activeDeviation.justDate) : '' }}
							</div>
						</template>
					</Card>
				</div>

				<!-- DER -->
				<div class="col-12 md:col-6">
					<Card>
						<template #header>Verlauf</template>
						<template #content>
							<MiniDeviationChart :months="activeDeviation.months"
								:sales="activeDeviation.salesSeries ?? activeDeviation.sales"
								:budget="activeDeviation.budgetSeries ?? activeDeviation.budget"
								:forecast="activeDeviation.forecastSeries ?? activeDeviation.forecast" :height="420" />
						</template>
					</Card>
				</div>

				<!-- Abajo -->
				<div class="col-12">
					<Card>
						<template #header>Aktionsplan</template>
						<template #content>
							<p v-if="activeDeviation.plan?.trim().length" class="font-mono">
								<i class="pi pi-flag mr-2" />{{ activeDeviation.plan }}
							</p>
							<ul v-if="Array.isArray(activeDeviation.actions) && activeDeviation.actions.length"
								class="list-none p-0 m-0 flex flex-column gap-2">
								<li v-for="(a, i) in activeDeviation.actions" :key="i">
									<i :class="['pi', a.done ? 'pi-check-circle text-green-500' : 'pi-circle']" />
									<span class="ml-2">{{ a.title || '—' }}</span>
									<span class="text-500 ml-2" v-if="a.due">· Fällig: {{ fmtDate(a.due) }}</span>
									<span class="text-500 ml-2" v-if="a.desc">· {{ a.desc }}</span>
								</li>
							</ul>
							<p v-else class="text-500">Kein Aktionsplan vorhanden.</p>
						</template>
					</Card>
				</div>
			</div>
		</template>
	</Dialog>

	<!-- DIALOG: Extra Quota Analysis -->
	<Dialog v-model:visible="showXQAnalysis" modal appendTo="body" :draggable="false" :blockScroll="true"
		header="Zusatzquoten · Analyse" style="width: min(1100px, 98vw)">
		<ExtraQuotaAnalysis embedded :userId="selectedSeller" @close="showXQAnalysis = false" />
	</Dialog>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import Listbox from 'primevue/listbox'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import ExtraQuotaCard from '@/components/analytics/ExtraQuotaCardManager.vue'
import ExtraQuotaAnalysis from '@/views/dashboard/ExtraQuotaAnalysis.vue'
import PcOverviewManager from '@/components/analytics/PcOverviewManager.vue'
import MiniDeviationChart from '@/components/charts/MiniDeviationChart.vue'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

/* ========= STATE ========= */
const sellers = ref([])
const selectedSeller = ref(null)

const showDeviationModal = ref(false)
const activeDeviation = ref(null)
const isLoadingDevs = ref(false)
const deviations = ref([])

const showXQAnalysis = ref(false)
const xq = ref({ target: 0, achieved: 0, mix: [], items: [], pcDetail: null })

const periodOffset = ref(0)
const periodLabel = computed(() => {
	const [s] = monthRangeFromOffset(periodOffset.value)
	return `${labelMonth(s)} ${s.getFullYear()}`
})

/* ========= Units by PC for Delta + Kennzahlen ========= */
const unitsByPc = ref({})
function unitOf(pcCode) {
	return unitsByPc.value[String(pcCode || '')] || 'VKEH'
}
function fmtNumberDE(n) {
	return Number(n || 0).toLocaleString('de-DE', { maximumFractionDigits: 0 })
}
function fmtCurrency(n) {
	return Number(n || 0).toLocaleString('de-DE', {
		style: 'currency',
		currency: 'EUR',
		maximumFractionDigits: 0,
	})
}
function fmtAmount(n, unit) {
	const u = String(unit || 'VKEH').toUpperCase()
	if (u === 'EUR') return fmtCurrency(n)
	return `${fmtNumberDE(n)} ${u}`
}
async function loadUnitsForDeviations() {
	const codes = Array.from(
		new Set(
			(Array.isArray(deviations.value) ? deviations.value : [])
				.map((r) => String(r?.pcCode || ''))
				.filter(Boolean),
		),
	)
	if (!codes.length) {
		unitsByPc.value = {}
		return
	}
	try {
		const { data } = await api.get('/api/unit-conversions/by-pc', {
			params: { codes: codes.join(',') },
		})
		unitsByPc.value = data || {}
	} catch {
		const m = {}
		for (const c of codes) m[c] = 'VKEH'
		unitsByPc.value = m
	}
}

/* ========= CHART (MiniDeviationChart) ========= */
const chartLoading = ref(false)
const chartMonths = ref(null)
const chartSales = ref([])
const chartBudget = ref([])
const chartForecast = ref([])

function toNum(x) {
	if (typeof x === 'number' && Number.isFinite(x)) return x
	if (x == null) return 0
	const n = Number(String(x).replace(/\./g, '').replace(',', '.'))
	return Number.isFinite(n) ? n : 0
}
function fiscalYearStartForYm(year, month) {
	return month >= 4 ? year : year - 1
}
function resetChart() {
	chartMonths.value = null
	chartSales.value = []
	chartBudget.value = []
	chartForecast.value = []
}
async function loadDeviationChart(row) {
	resetChart()
	if (!row || !row.pcCode) return
	chartLoading.value = true
	try {
		const fy = fiscalYearStartForYm(Number(row.year), Number(row.month))
		const { data } = await api.get('/api/company/pc-overview', {
			params: { profit_center_code: String(row.pcCode), fiscal_year: fy },
		})

		// series en m³
		const months = Array.isArray(data?.months) ? data.months : []
		const s = Array.isArray(data?.raw?.monthly?.sales?.m3) ? data.raw.monthly.sales.m3 : []
		const b = Array.isArray(data?.raw?.monthly?.budgets?.m3) ? data.raw.monthly.budgets.m3 : []
		const f = Array.isArray(data?.raw?.monthly?.forecast?.m3)
			? data.raw.monthly.forecast.m3
			: []

		const salesArr = s.map(toNum)
		const budArr = b.map(toNum)
		const fcArr = f.map(toNum)

		if (String(row.type).toLowerCase() === 'forecast') {
			// vista serie: 3 líneas
			chartMonths.value = months
			chartSales.value = salesArr
			chartBudget.value = budArr
			chartForecast.value = fcArr
		} else {
			// vista barras: último punto de Ist vs Budget
			const last = (arr) => (arr.length ? arr[arr.length - 1] : 0)
			chartMonths.value = null
			chartSales.value = [last(salesArr)]
			chartBudget.value = [last(budArr)]
			chartForecast.value = []
		}
	} catch {
		resetChart()
	} finally {
		chartLoading.value = false
	}
}

/* ========= KPIs ========= */
const kpiInTerm = computed(() => {
	const a = Array.isArray(deviations.value) ? deviations.value : []
	let inTerm = 0
	for (const d of a) if (d?.justified) inTerm++
	return inTerm
})
const kpiOutTerm = computed(() => {
	const a = Array.isArray(deviations.value) ? deviations.value : []
	let outTerm = 0
	for (const d of a) if (!d?.justified) outTerm++
	return outTerm
})

/* ========= Helpers ========= */
function labelMonth(d) {
	return d.toLocaleString('de-DE', { month: 'long' }).replace(/^./, (m) => m.toUpperCase())
}
function monthRangeFromOffset(off = 0) {
	const now = new Date()
	const base = new Date(now.getFullYear(), now.getMonth() + off, 1)
	return [
		new Date(base.getFullYear(), base.getMonth(), 1),
		new Date(base.getFullYear(), base.getMonth() + 1, 0, 23, 59, 59, 999),
	]
}
function fiscalYearStartFor(date = new Date()) {
	return date.getMonth() >= 3 ? date.getFullYear() : date.getFullYear() - 1
}
function fmtDate(iso) {
	const d = new Date(String(iso))
	return isNaN(d) ? '—' : d.toLocaleDateString('de-DE')
}
function initials(n = '') {
	return n
		.split(' ')
		.filter(Boolean)
		.map((w) => w[0]?.toUpperCase())
		.slice(0, 2)
		.join('')
}
function periodText(y, m) {
	const d = new Date(Number(y), Number(m) - 1, 1)
	return `${labelMonth(d)} ${d.getFullYear()}`
}
function typeLabel(type) {
	return String(type || '').toLowerCase() === 'forecast' ? 'Forecast' : 'Ist'
}
function hasPlan(r) {
	return !!r?.plan || (Array.isArray(r?.actions) && r.actions.length > 0)
}
function isOverdue(row) {
	const j = !!row?.justified,
		now = new Date(),
		y = now.getFullYear(),
		m = now.getMonth() + 1,
		day = now.getDate()
	return !j && Number(row?.year) === y && Number(row?.month) === m && day > 10
}

/* ========= TEAM mapping (UI avatar) ========= */
const TEAM_OVERRIDES = Object.freeze({ 4: 1, 7: 1, 8: 1, 9: 1, 15: 1, 18: 1, 6: 2 })
const NO_TEAM = new Set([1, 2, 3])
function getTeamId(u) {
	const c = [
		u?.team_id,
		u?.teamId,
		u?.team?.id,
		Array.isArray(u?.teams) ? u.teams[0]?.id : undefined,
		Array.isArray(u?.team_members) ? u.team_members[0]?.team_id : undefined,
		Array.isArray(u?.memberships) ? u.memberships[0]?.team_id : undefined,
	].filter((v) => v != null)
	for (const x of c) {
		const n = Number(x)
		if (Number.isFinite(n) && n > 0) return n
	}
	return NaN
}
function resolveTeamId(u) {
	if (!u) return null
	const id = Number(u.id || 0)
	if (NO_TEAM.has(id)) return null
	if (TEAM_OVERRIDES[id]) return TEAM_OVERRIDES[id]
	const t = Number(u.__teamId ?? getTeamId(u))
	return Number.isFinite(t) && t > 0 ? t : null
}
function teamClass(u) {
	const id = Number(u?.id ?? 0)
	if (NO_TEAM.has(id)) return 'team-none'
	const o = TEAM_OVERRIDES[id]
	if (o === 1) return 'team-alpha'
	if (o === 2) return 'team-bravo'
	const t = Number(u?.__teamId ?? getTeamId(u))
	if (t === 1) return 'team-alpha'
	if (t === 2) return 'team-bravo'
	return id % 2 ? 'team-alpha' : 'team-bravo'
}
function displayNameFromApiUser(u = {}) {
	const name =
		u.name ||
		[u.first_name ?? u.firstName, u.last_name ?? u.lastName].filter(Boolean).join(' ').trim()
	const p = String(name || '')
		.trim()
		.split(/\s+/)
		.filter(Boolean)
	if (p.length <= 1) return p[0] || ''
	const last = p[p.length - 1],
		first = p.slice(0, -1).join(' ')
	return `${last}, ${first}`
}
const sellerItems = computed(() => {
	const arr = Array.isArray(sellers.value) ? sellers.value.slice() : []
	for (const u of arr) {
		u.__displayName = displayNameFromApiUser(u)
		u.__photo = u.avatar_url ?? u.photo_url ?? u.profile_photo_url ?? u.photo ?? null
		u.__teamId = getTeamId(u)
	}
	arr.sort((a, b) =>
		String(a.__displayName).localeCompare(String(b.__displayName), 'de', {
			sensitivity: 'base',
		}),
	)
	return arr
})

/* ========= TABLE ORDER ========= */
const deviationsSorted = computed(() => {
	return [...(deviations.value || [])].sort((a, b) =>
		String(a.pcCode || '').localeCompare(String(b.pcCode || ''), 'de', { sensitivity: 'base' }),
	)
})

/* ========= UI Actions ========= */
function shiftPeriod(delta) {
	periodOffset.value += delta
}
function onDeviationRowClick(e) {
	openDeviation(e?.data)
}
function openDeviation(row) {
	if (!row) return
	activeDeviation.value = { ...row }
	showDeviationModal.value = true
}

/* ========= API ========= */
async function loadSellers() {
	try {
		await ensureCsrf()
		const { data } = await api.get('/api/sales-force/users', { params: { per_page: 1000 } })
		const rows = Array.isArray(data) ? data : []
		const filtered = rows.filter((u) => Number(u.role_id) === 4 && Number(u.disabled) === 0)
		sellers.value = filtered
		if (!selectedSeller.value && filtered.length) selectedSeller.value = filtered[0].id
	} catch {
		sellers.value = []
		selectedSeller.value = null
	}
}
async function loadDeviationsForMonth() {
	const uid = selectedSeller.value
	if (!uid) {
		deviations.value = []
		return
	}
	const [start] = monthRangeFromOffset(periodOffset.value)
	const year = start.getFullYear(),
		month = start.getMonth() + 1
	isLoadingDevs.value = true
	try {
		await ensureCsrf()
		const { data } = await api.get('/api/deviations/by-user-month', {
			params: { user_id: uid, year, month },
		})
		deviations.value = Array.isArray(data) ? data : []
	} catch {
		deviations.value = []
	} finally {
		isLoadingDevs.value = false
	}
}

/* Extra Quota */
function mapXQFromAnalysisSummary(data = {}) {
	const totals = data?.totals || {}
	const assigned = Number(totals.assigned_m3 ?? 0) || 0
	const converted = Number(totals.converted_m3 ?? 0) || 0
	const inProgress = Number(totals.in_progress_m3 ?? 0) || 0
	const achieved = Math.min(assigned, Math.max(0, converted + inProgress))
	return { target: assigned, achieved, items: [], mix: null, pcDetail: null }
}
async function loadExtraQuotaForSelected() {
	const uid = Number(selectedSeller.value)
	if (!uid) {
		xq.value = { target: 0, achieved: 0, mix: [], items: [], pcDetail: null }
		return
	}
	try {
		await ensureCsrf()
		const fiscal_year = fiscalYearStartFor(new Date())
		let data
		try {
			const r = await api.get('/api/extra-quota/analysis/summary-by-user', {
				params: { user_id: uid, fiscal_year },
			})
			data = r.data
		} catch {
			const r2 = await api.get('/api/extra-quota/analysis/summary', {
				params: { user_id: uid, fiscal_year },
			})
			data = r2.data
		}
		xq.value = mapXQFromAnalysisSummary(data || {})
	} catch {
		xq.value = { target: 0, achieved: 0, mix: [], items: [], pcDetail: null }
	}
}

/* ========= WIRES ========= */
onMounted(async () => {
	await loadSellers()
	await loadDeviationsForMonth()
	await loadUnitsForDeviations()
	await loadExtraQuotaForSelected()
})
watch([selectedSeller, periodOffset], async () => {
	await loadDeviationsForMonth()
	await loadUnitsForDeviations()
})
watch(selectedSeller, () => {
	loadExtraQuotaForSelected()
})
</script>

<style scoped>
.sales-force-analysis-grid {
	--gap: 16px;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	height: 100%;
	box-sizing: border-box;
}

.filters-col {
	grid-column: span 2;
	display: flex;
	min-width: 0;
	height: 100%;
}

.filters-card {
	flex: 1;
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 0;
}

.seller-listbox {
	flex: 1;
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 0;
	box-sizing: border-box;
}

.seller-listbox :deep(.p-listbox-list-wrapper) {
	flex: 1;
	min-height: 0;
	height: 100%;
	overflow-y: auto;
}

.seller-row {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 6px 8px;
	border-radius: 8px;
	cursor: pointer;
	transition: background 0.15s ease;
}

.seller-row:hover {
	background: var(--surface-100);
}

.seller-row.selected {
	background: var(--primary-50);
}

.avatar-ring {
	width: 40px;
	height: 40px;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 2px;
	flex-shrink: 0;
}

.avatar-initials {
	background: transparent !important;
	color: #fff !important;
	font-weight: 600;
	font-size: 1rem;
}

.seller-name {
	flex: 1;
	font-weight: 500;
	font-size: 0.9rem;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}


.content-col {
	grid-column: span 10;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	grid-template-rows: auto 1fr;
	gap: var(--gap);
	min-width: 0;
	min-height: 0;
	height: 100%;
}

.topbar-card {
	grid-column: 1 / -1;
}

.topbar-inner {
	display: flex;
	align-items: center;
	justify-content: space-between;
	flex-wrap: wrap;
	max-width: 70%;
}

.pill {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 6px 12px;
	border-radius: 999px;
	background-color: color-mix(in srgb, var(--surface-ground) 85%, var(--primary-color));
	font-weight: 600;
}

.charts-row {
	display: contents;
	height: 100%;
}

.chart-card {
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.chart-lg {
	grid-column: span 8;
}

.chart-sm {
	grid-column: span 4;
	display: flex;
	flex-direction: column;
	height: 100%;
}

.chart-sm .p-card {
	flex: 1 1 auto;
	overflow: hidden;
}

.chart-sm .p-card:nth-child(1) {
	flex: 1 1 60%;
	max-height: 60%;
	overflow: auto;
}

.chart-sm .p-card:nth-child(2) {
	flex: 1 1 40%;
	max-height: 40%;
}

@media (max-width: 1199px) {
	.filters-col {
		grid-column: 1 / -1;
	}

	.content-col {
		grid-column: 1 / -1;
	}

	.chart-lg,
	.chart-sm {
		grid-column: 1 / -1;
	}
}

.avatar-ring {
	width: 42px;
	height: 42px;
	border-radius: 999px;
	padding: 2px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.team-alpha {
	background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
}

.team-bravo {
	background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
}

.team-none {
	border: 1px dashed var(--surface-border);
}

.font-mono {
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.white-space-pre-line {
	white-space: pre-line;
}
</style>
