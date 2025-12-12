<template>
	<Toast />

	<!-- GRID PRINCIPAL -->
	<div class="sales-force-analysis-grid">
		<!-- ASIDE: Lista de vendedores (left pane estilo Forecast) -->
		<aside class="filters-col">
			<Card class="filters-card">
				<template #content>
					<Listbox v-model="selectedSeller" :options="sellerItems" optionValue="id"
						optionLabel="__displayName" dataKey="id" class="seller-listbox" listStyle="max-height:100%">
						<template #option="{ option, selected }">
							<div :class="['seller-row', { selected }]">
								<!-- Anillo + avatar -->
								<div :class="[
									'avatar-ring',
									teamClass(option),
									option.profile_picture || option.__photo ? 'avatar-has-img' : 'avatar-no-img',
								]">

									<Avatar v-if="option.profile_picture || option.__photo"
										:image="option.profile_picture || option.__photo" shape="circle"
										class="avatar-img" />
									<Avatar v-else :label="initials(option.__displayName)" shape="circle"
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
			<!-- TOPBAR: estilo Budget Case -->
			<Card class="topbar-card">
				<template #content>
					<div class="topbar-inner">
						<div class="title-left">
							<div class="eyebrow">Sales Force Analytics</div>
							<div class="title-line">
								<strong class="kunde">
									{{ selectedSellerName || 'Sales Rep auswählen' }}
								</strong>
							</div>
						</div>

						<div class="actions">
							<div class="date-controls">
								<Button icon="pi pi-chevron-left" text rounded aria-label="Vorheriger Monat"
									@click="shiftPeriod(-1)" />
								<span class="pill">
									<i class="pi pi-calendar mr-2" />{{ periodLabel }}
								</span>
								<Button icon="pi pi-chevron-right" text rounded aria-label="Nächster Monat"
									@click="shiftPeriod(1)" />
							</div>
						</div>
					</div>
				</template>
			</Card>

			<!-- MAIN ROW (8 + 4 columnas) -->
			<div class="charts-row">
				<!-- IZQ: Tabla de desvíos -->
				<Card class="chart-card chart-lg">
					<template #content>
						<div class="abw-header-row">
							<span class="abw-title">Abweichungsbegründungen</span>

							<div class="abw-kpi-row">
								<Tag severity="success" rounded class="abw-pill">
									<span class="pill-label">Fristgerecht</span>
									<span class="pill-value">{{ kpiInTerm }}</span>
								</Tag>
								<Tag severity="warning" rounded class="abw-pill">
									<span class="pill-label">Verspätet</span>
									<span class="pill-value">{{ kpiOutTerm }}</span>
								</Tag>
							</div>
						</div>

						<DataTable :value="deviationsSorted" responsiveLayout="scroll" :rows="10" paginator rowHover
							styleClass="text-sm"
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
												: 'warning'
										" :value="isOverdue(r)
												? 'Überfällig'
												: r.justified
													? 'Begründet'
													: 'Offen'
											" rounded />
								</template>
							</Column>

							<Column header="" style="width: 120px">
								<template #body="{ data: r }">
									<Button label="Ansehen" text size="small" title="Abweichung ansehen"
										@click.stop="openDeviation(r)" />
								</template>
							</Column>

							<template #empty>
								<div class="text-center text-500 p-3">Keine Daten für den Zeitraum.</div>
							</template>
						</DataTable>
					</template>
				</Card>

				<!-- DER: Profitcenter + ExtraQuota -->
				<div class="chart-card chart-sm">
					<Card class="pc-card">
						<template #content>
							<div class="pc-portfolio-content">
								<PcOverviewManager :userId="selectedSeller" unit="M3" :period="null" />
							</div>
						</template>
					</Card>

					<Card class="xq-card">
						<template #content>
							<div class="xq-header-row">
								<span class="xq-title">Zusatzquote</span>
								<div class="xq-kpi-row">
									<Button icon="pi pi-search" text @click="showXQAnalysis = true" />
								</div>
							</div>
							<ExtraQuotaCard unit="m³" :target="xq.target" :achieved="xq.achieved" :mix="xq.mix"
								:items="xq.items" :pcDetail="xq.pcDetail" :currentUserId="selectedSeller"
								:showMoreButton="false" />
						</template>
					</Card>
				</div>
			</div>
		</main>
	</div>

	<!-- DIALOG: Desvío Detalle -->
	<Dialog v-model:visible="showDeviationModal" modal appendTo="body" :draggable="false" :blockScroll="true"
		header="Abweichung · Detail" style="width: min(900px, 96vw)" class="dev-dialog-shell"
		:maskClass="'dev-dialog-mask'">
		<template v-if="activeDeviation">
			<div class="dev-dialog">
				<!-- Summary band -->
				<div class="dev-summary">
					<div class="dev-summary-main">
						<div class="dev-pc-name">
							<i class="pi pi-sliders-h dev-pc-icon" />
							<span>{{ activeDeviation.pcName || '—' }}</span>
						</div>
						<div class="dev-meta-row">
							<span class="dev-chip">{{ typeLabel(activeDeviation.type) }}</span>
							<span class="dev-dot">·</span>
							<span class="dev-meta">
								{{ periodText(activeDeviation.year, activeDeviation.month) }}
							</span>
						</div>
					</div>
					<div class="dev-summary-side">
						<Tag :severity="isOverdue(activeDeviation)
								? 'danger'
								: activeDeviation.justified
									? 'success'
									: 'warning'
							" :value="isOverdue(activeDeviation)
									? 'Überfällig'
									: activeDeviation.justified
										? 'Begründet'
										: 'Offen'
								" rounded />
					</div>
				</div>

				<!-- MAIN: gráfico izquierda / resto derecha -->
				<div class="dev-main">
					<!-- IZQUIERDA: Gráfico + historial manager -->
					<div class="dev-main-left">
						<Card class="dev-card dev-chart-card">
							<template #header>
								<div class="dev-card-header">
									<span class="dev-card-title">Verlauf</span>
								</div>
							</template>
							<template #content>
								<MiniDeviationChart :months="activeDeviation.months"
									:sales="activeDeviation.salesSeries ?? activeDeviation.sales"
									:budget="activeDeviation.budgetSeries ?? activeDeviation.budget"
									:forecast="activeDeviation.forecastSeries ?? activeDeviation.forecast"
									:height="420" />
							</template>
						</Card>

						<!-- Historial de feedback del manager (solo Forecast, mes anterior) -->
						<Card v-if="isForecastDeviation" class="mb-3 dev-card">
							<template #header>
								<div class="dev-card-header">
									<span class="dev-card-title">Rückmeldungen des Managers</span>
								</div>
							</template>
							<template #content>
								<div v-if="managerNotesLoading" class="dev-notes-loading text-500">
									Lädt…
								</div>
								<div v-else-if="!managerNotes.length" class="dev-notes-empty text-500">
									Keine bisherigen Rückmeldungen.
								</div>
								<ul v-else class="dev-notes-list">
									<li v-for="n in managerNotes" :key="n.id" class="dev-note-item">
										<div class="dev-note-meta">
											<span class="dev-note-author">
												{{ n.manager_name || 'Manager' }}
											</span>
											<span class="dev-note-date">
												{{ fmtDate(n.created_at) }}
											</span>
										</div>
										<div class="dev-note-text">
											{{ n.note }}
										</div>
									</li>
								</ul>
							</template>
						</Card>
					</div>

					<!-- DERECHA: Kennzahlen + Begründung + Aktionsplan + nueva nota -->
					<div class="dev-main-right">
						<!-- Kennzahlen -->
						<Card class="mb-3 dev-card">
							<template #header>
								<div class="dev-card-header">
									<span class="dev-card-title">Kennzahlen</span>
								</div>
							</template>
							<template #content>
								<div class="dev-metrics">
									<div class="metric-row">
										<span class="metric-label">Umsatz</span>
										<span class="metric-value">
											{{ fmtAmount(activeDeviation.sales, unitOf(activeDeviation.pcCode)) }}
										</span>
									</div>
									<div class="metric-row">
										<span class="metric-label">Budget</span>
										<span class="metric-value">
											{{ fmtAmount(activeDeviation.budget, unitOf(activeDeviation.pcCode)) }}
										</span>
									</div>
									<div class="metric-row">
										<span class="metric-label">Forecast</span>
										<span class="metric-value">
											{{ fmtAmount(activeDeviation.forecast, unitOf(activeDeviation.pcCode)) }}
										</span>
									</div>
									<div class="metric-row metric-row-delta">
										<span class="metric-label">Delta</span>
										<span class="metric-value metric-delta" :class="(activeDeviation.deltaAbs ?? 0) >= 0
												? 'text-green-500'
												: 'text-red-500'
											">
											{{ fmtAmount(activeDeviation.deltaAbs, unitOf(activeDeviation.pcCode)) }}
											<span class="metric-delta-pct">
												({{ Math.round(activeDeviation.deltaPct ?? 0) }}%)
											</span>
										</span>
									</div>
								</div>
							</template>
						</Card>

						<!-- Begründung -->
						<Card class="dev-card">
							<template #header>
								<div class="dev-card-header">
									<span class="dev-card-title">Begründung</span>
								</div>
							</template>
							<template #content>
								<p v-if="activeDeviation.comment?.trim().length" class="dev-comment">
									{{ activeDeviation.comment }}
								</p>
								<p v-else class="text-500 dev-comment empty">
									Keine Begründung angegeben.
								</p>
								<div class="dev-just-meta"
									v-if="activeDeviation.justAuthor || activeDeviation.justDate">
									<span v-if="activeDeviation.justAuthor" class="dev-meta-chip">
										<i class="pi pi-user mr-1" />{{ activeDeviation.justAuthor }}
									</span>
									<span v-if="activeDeviation.justDate" class="dev-meta-chip">
										<i class="pi pi-calendar mr-1" />{{
											activeDeviation.justDate
												? fmtDate(activeDeviation.justDate)
												: ''
										}}
									</span>
								</div>
							</template>
						</Card>

						<!-- Aktionsplan -->
						<Card class="dev-card dev-plan-card">
							<template #header>
								<div class="dev-card-header">
									<span class="dev-card-title">Aktionsplan</span>
								</div>
							</template>
							<template #content>
								<p v-if="activeDeviation.plan?.trim().length" class="dev-plan-intro">
									<i class="pi pi-flag mr-2" />{{ activeDeviation.plan }}
								</p>

								<ul v-if="
									Array.isArray(activeDeviation.actions) &&
									activeDeviation.actions.length
								" class="plan-timeline">
									<li v-for="(a, i) in activeDeviation.actions" :key="i" class="plan-item">
										<div class="plan-bullet">
											<i :class="[
												'pi',
												a.done
													? 'pi-check-circle text-green-500'
													: 'pi-circle text-500',
											]" />
											<div class="plan-line" />
										</div>
										<div class="plan-content">
											<div class="plan-title-row">
												<span class="plan-title">
													{{ a.title || '—' }}
												</span>
												<span v-if="a.due" class="plan-tag">
													Fällig: {{ fmtDate(a.due) }}
												</span>
											</div>
											<div v-if="a.desc" class="plan-desc">
												{{ a.desc }}
											</div>
										</div>
									</li>
								</ul>

								<p v-else class="text-500 dev-plan-empty">
									Kein Aktionsplan vorhanden.
								</p>
							</template>
						</Card>

						<!-- Neue Rückmeldung -->
						<div v-if="isForecastDeviation" class="dev-new-note">
							<div class="dev-new-note-header">
								<span class="dev-card-title">Neue Rückmeldung</span>
							</div>
							<textarea v-model="newManagerNote" class="dev-note-textarea" rows="3"
								placeholder="Feedback an den Sales Rep…"></textarea>
							<div class="dev-note-actions">
								<Button label="Speichern" icon="pi pi-save" class="dev-note-save-btn"
									:loading="managerNoteSaving" :disabled="!newManagerNote.trim()"
									@click="saveManagerNote" />
							</div>
						</div>
					</div>
				</div>
			</div>
		</template>
	</Dialog>

	<!-- DIALOG: Extra Quota Analysis -->
	<Dialog v-model:visible="showXQAnalysis" modal appendTo="body" :draggable="false" :blockScroll="true"
		header="Zusatzquoten · Analyse" style="width: min(1100px, 98vw)" class="xq-dialog-shell"
		:maskClass="'xq-dialog-mask'">
		<div class="xq-dialog-body">
			<ExtraQuotaAnalysis embedded :userId="selectedSeller" @close="showXQAnalysis = false" />
		</div>
	</Dialog>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
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

const toast = useToast()

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

const selectedSellerName = computed(() => {
	const id = selectedSeller.value
	if (!id) return ''
	const u = sellerItems.value.find((x) => Number(x.id) === Number(id))
	return u?.__displayName || u?.name || ''
})

/* ===== Manager feedback (solo Forecast) ===== */
const managerNotes = ref([])
const managerNotesLoading = ref(false)
const newManagerNote = ref('')
const managerNoteSaving = ref(false)

const isForecastDeviation = computed(() =>
	activeDeviation.value
		? String(activeDeviation.value.type || '').toLowerCase() === 'forecast'
		: false,
)

function isForecast(row) {
	return String(row?.type || '').toLowerCase() === 'forecast'
}

/** Año/mes anterior al desvío actual */
function prevYearMonth(y, m) {
	const year = Number(y) || 0
	const month = Number(m) || 0
	if (!year || !month) return { year: null, month: null }
	if (month === 1) return { year: year - 1, month: 12 }
	return { year, month: month - 1 }
}

async function loadManagerNotesForDeviation(row) {
	managerNotesLoading.value = true
	managerNotes.value = []
	newManagerNote.value = ''

	try {
		if (!row || !isForecast(row) || !selectedSeller.value) {
			return
		}

		const { year: prevYear, month: prevMonth } = prevYearMonth(row.year, row.month)
		if (!prevYear || !prevMonth) return

		await ensureCsrf()
		const params = {
			user_id: selectedSeller.value,
			pc_code: String(row.pcCode),
			type: 'forecast',
			year: prevYear,
			month: prevMonth,
		}

		const { data } = await api.get('/api/sales-force/justifications-analysis', { params })
		const arr = Array.isArray(data) ? data : []

		const filtered = arr
			.map((n) => ({
				id: n.id,
				note: n.note || '',
				manager_name: n.manager_name || '',
				created_at: n.created_at || n.createdAt || null,
				year: Number(n.year ?? prevYear),
				month: Number(n.month ?? prevMonth),
			}))
			.filter((n) => n.year === prevYear && n.month === prevMonth)
			.sort((a, b) => {
				const da = new Date(a.created_at || 0).getTime()
				const db = new Date(b.created_at || 0).getTime()
				return db - da
			})

		managerNotes.value = filtered
	} catch (e) {
		// en caso de error, dejamos vacío pero no rompemos el modal
		console.error('loadManagerNotesForDeviation error', e)
		managerNotes.value = []
	} finally {
		managerNotesLoading.value = false
	}
}

async function saveManagerNote() {
	if (!isForecastDeviation.value || !activeDeviation.value) return
	const note = newManagerNote.value.trim()
	if (!note) return
	managerNoteSaving.value = true
	try {
		await ensureCsrf()
		const payload = {
			user_id: selectedSeller.value,
			pc_code: String(activeDeviation.value.pcCode),
			year: activeDeviation.value.year,
			month: activeDeviation.value.month,
			type: 'forecast',
			note,
		}
		const { data } = await api.post('/api/sales-force/justifications-analysis', payload)
		const saved = data || {}
		const item = {
			id: saved.id ?? Date.now(),
			note: saved.note ?? note,
			manager_name: saved.manager_name || '',
			created_at: saved.created_at || new Date().toISOString(),
		}
		managerNotes.value = [item, ...managerNotes.value]
		newManagerNote.value = ''
		toast?.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Rückmeldung gespeichert',
			life: 2200,
		})
	} catch (e) {
		console.error('saveManagerNote error', e)
		toast?.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Rückmeldung konnte nicht gespeichert werden',
			life: 2500,
		})
	} finally {
		managerNoteSaving.value = false
	}
}

/* ===== Units / helpers ===== */
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

/* KPIs de cabecera */
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

/* Helpers */
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

/* TEAM (team_id 1/2/3) – anillos */
/* TEAM (team_id 1/2/3) – anillos */
function getTeamId(u) {
	if (!u) return NaN

	// 1) casos "planos"
	let raw = u.team_id ?? u.teamId ?? u.team?.id

	// 2) si no vino plano pero sí viene como array de teamIds (lo que tenés ahora)
	if (raw == null && Array.isArray(u.teamIds) && u.teamIds.length) {
		raw = u.teamIds[0]   // 👈 primer team como "principal"
	}

	const n = Number(raw)
	return Number.isFinite(n) && n > 0 ? n : NaN
}

function teamClass(u) {
	const id = Number(u?.__teamId ?? getTeamId(u))
	if (id === 1 || id === 2 || id === 3) return `team-${id}`
	return 'team-none'
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
	const last = p[p.length - 1]
	const first = p.slice(0, -1).join(' ')
	return `${last}, ${first}`
}
const sellerItems = computed(() => {
	const arr = Array.isArray(sellers.value) ? sellers.value.slice() : []

	// 1) primero enriquecemos cada user
	for (const u of arr) {
		u.__displayName = displayNameFromApiUser(u)
		u.__photo = u.avatar_url ?? u.photo_url ?? u.profile_photo_url ?? u.photo ?? null
		u.__teamId = getTeamId(u)
	}

	// 2) filtramos: solo team 1 y 2 (basado en team "principal")
	const filtered = arr.filter((u) => {
		const id = Number(u.__teamId ?? getTeamId(u))
		return id === 1 || id === 2
	})

	// 3) orden alfabético
	filtered.sort((a, b) =>
		String(a.__displayName).localeCompare(String(b.__displayName), 'de', {
			sensitivity: 'base',
		}),
	)

	return filtered
})


const deviationsSorted = computed(() => {
	return [...(deviations.value || [])].sort((a, b) =>
		String(a.pcCode || '').localeCompare(String(b.pcCode || ''), 'de', {
			sensitivity: 'base',
		}),
	)
})

/* UI Actions */
function shiftPeriod(delta) {
	periodOffset.value += delta
}
function onDeviationRowClick(e) {
	openDeviation(e?.data)
}
async function openDeviation(row) {
	if (!row) return
	activeDeviation.value = { ...row }
	showDeviationModal.value = true

	// Si en algún momento querés datos frescos:
	// await loadDeviationChart(row)

	if (isForecast(row)) {
		await loadManagerNotesForDeviation(row)
	} else {
		managerNotes.value = []
		newManagerNote.value = ''
	}
}

/* API */
async function loadSellers() {
	try {
		await ensureCsrf()
		const { data } = await api.get('/api/sales-force/users', { params: { per_page: 1000 } })
		const rows = Array.isArray(data) ? data : []
		sellers.value = rows

		// ⚠️ usamos el computed filtrado para elegir el primero visible
		if (!selectedSeller.value) {
			const firstVisible = sellerItems.value[0]
			if (firstVisible) {
				selectedSeller.value = firstVisible.id
			} else {
				selectedSeller.value = null
			}
		}
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
	const year = start.getFullYear()
	const month = start.getMonth() + 1
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

/* Extra Quota – construimos mix para dibujar la barra */
function mapXQFromAnalysisSummary(data = {}) {
	const totals = data?.totals || {}
	const assigned = Number(totals.assigned_m3 ?? 0) || 0
	const converted = Math.max(0, Number(totals.converted_m3 ?? 0) || 0)
	const inProgress = Math.max(0, Number(totals.in_progress_m3 ?? 0) || 0)
	const rawUsed = converted + inProgress
	const achieved = Math.min(assigned, rawUsed)

	// resto "abierto" si no está totalmente asignado
	const open = Math.max(0, assigned - converted - inProgress)

	const mix = [
		{
			key: 'converted',
			label: 'Konvertiert',
			amount_m3: converted,
			color: '#10b981',
		},
		{
			key: 'in_progress',
			label: 'In Arbeit',
			amount_m3: inProgress,
			color: '#f59e0b',
		},
	]

	if (open > 0) {
		mix.push({
			key: 'open',
			label: 'Offen',
			amount_m3: open,
			color: '#e5e7eb',
		})
	}

	return {
		target: assigned,
		achieved,
		items: [],
		mix,
		pcDetail: null,
	}
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
			const r = await api.get('/api/sales-force/extra-quota/analysis/summary-by-user', {
				params: { user_id: uid, fiscal_year },
			})
			data = r.data
		} catch {
			try {
				const r2 = await api.get('/api/extra-quota/analysis/summary-by-user', {
					params: { user_id: uid, fiscal_year },
				})
				data = r2.data
			} catch {
				const r3 = await api.get('/api/extra-quota/analysis/summary', {
					params: { user_id: uid, fiscal_year },
				})
				data = r3.data
			}
		}
		xq.value = mapXQFromAnalysisSummary(data || {})
	} catch (e) {
		console.error('loadExtraQuotaForSelected error', e)
		xq.value = { target: 0, achieved: 0, mix: [], items: [], pcDetail: null }
		toast?.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Zusatzquote konnte nicht geladen werden',
			life: 2500,
		})
	}
}

/* Wires */
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
	min-height: 0;
	box-sizing: border-box;
}

/* ====== PANE LEFT tipo Forecast: altura completa + scroll interno ====== */
.filters-col {
	grid-column: span 2;
	display: flex;
	min-width: 0;
	min-height: 0;
	height: 100%;
}

.filters-card {
	flex: 1 1 auto;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.filters-card :deep(.p-card-body) {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	flex-direction: column;
}

.filters-card :deep(.p-card-content) {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	flex-direction: column;
}

/* Listbox ocupa todo y scrollea internamente */
.seller-listbox {
	flex: 1 1 auto;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.seller-listbox :deep(.p-listbox-list-wrapper) {
	flex: 1 1 auto;
	min-height: 0;
	max-height: 100%;
	overflow-y: auto;
}

/* Fila de vendedor */
.seller-row {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 6px 8px;
	border-radius: 8px;
	cursor: pointer;
	transition: background 0.15s ease, box-shadow 0.15s ease;
}

.seller-row:hover {
	background: var(--surface-100);
}

.seller-row.selected {
	background: var(--primary-50);
	box-shadow: 0 0 0 1px var(--primary-color);
}

/* ==== AVATAR RING: tamaño lindo ==== */
.avatar-ring {
	width: 42px;                 /* más grande que 42, sin ser gigante */
	height: 42px;
	border-radius: 999px;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	box-sizing: border-box;
	transition: transform 0.15s ease, box-shadow 0.15s ease;
}

/* un poco de pop al pasar el mouse */
.seller-row:hover .avatar-ring {
	transform: translateY(-1px);
	box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
}

.avatar-img,
.avatar-initials {
	width: 100%;
	height: 100%;
}

/* imagen interna redonda */
.avatar-img :deep(img) {
	border-radius: 999px;
	width: 100%;
	height: 100%;
	object-fit: cover;
}

/* texto para iniciales */
.avatar-initials {
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff !important;
	font-weight: 600;
	font-size: 1rem;
	border-radius: 999px;
}

/* ===================== CON IMAGEN ===================== */
/* degrade SOLO en el borde */
.avatar-has-img {
	padding: 4px;                 /* grosor del anillo */
	border: none;
	background: radial-gradient(circle at 30% 30%, #e5e7eb, #cbd5f5);
}

/* anillo coloreado por team */
.avatar-has-img.team-1 {
	background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82)
}
.avatar-has-img.team-2 {
	background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
}
.avatar-has-img.team-3 {
	background: linear-gradient(60deg, #364fc7, #4263eb, #5c7cfa, #748ffc);
}
.avatar-has-img.team-none {
	background: radial-gradient(circle at 30% 30%, #e5e7eb, #cbd5f5);
	border: 2px dashed rgba(148, 163, 184, 0.7);
}

/* ===================== SIN IMAGEN ===================== */
/* sin padding: el círculo entero es el “contenido” */
.avatar-no-img {
	padding: 0;
	border: none;
}

/* gradiente vive en el propio círculo de iniciales */
.avatar-no-img.team-1 .avatar-initials {
	background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82)
}
.avatar-no-img.team-2 .avatar-initials {
	background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
}
.avatar-no-img.team-3 .avatar-initials {
	background: linear-gradient(60deg, #364fc7, #4263eb, #5c7cfa, #748ffc);
}
.avatar-no-img.team-none .avatar-initials {
	background: radial-gradient(circle at 30% 30%, #e5e7eb, #cbd5f5);
	border: 2px dashed rgba(148, 163, 184, 0.7);
}

/* fallback neutro por si algo viene sin team */
.team-none {
	border-style: dashed;
}

/* aseguramos que la fila acompañe el tamaño del avatar */
.seller-row {
	min-height: 64px;
}

.seller-name {
	flex: 1;
	font-weight: 500;
	font-size: 0.9rem;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* ====== CONTENT ====== */
.content-col {
	grid-column: span 10;
	display: grid;
	gap: var(--gap);
	grid-template-columns: repeat(12, minmax(0, 1fr));
	grid-template-rows: auto 1fr;
	min-height: 0;
}

/* TOPBAR */
.topbar-card {
	grid-column: 1 / -1;
}

.topbar-inner {
	display: flex;
	justify-content: space-between;
	align-items: center;
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
	font-size: 0.75rem;
	color: var(--text-muted);
	text-transform: uppercase;
	margin-bottom: 0.25rem;
}

.kunde {
	font-weight: bold;
}

.actions {
	display: flex;
	align-items: center;
	gap: 0.75rem;
}

.date-controls {
	display: flex;
	align-items: center;
	gap: 0.35rem;
}

.pill {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 4px 10px;
	border-radius: 999px;
	background-color: color-mix(in srgb, var(--surface-ground) 85%, var(--primary-color));
	font-weight: 600;
	font-size: 0.8rem;
	white-space: nowrap;
}

/* ===== CHARTS MAIN ===== */
.charts-row {
	grid-column: 1 / -1;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	align-items: stretch;
	min-height: 0;
	height: 100%;
}

.chart-card {
	min-height: 0;
	height: 100%;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.chart-lg {
	grid-column: span 8;
}

.chart-sm {
	grid-column: span 4;
	display: flex;
	flex-direction: column;
	gap: 1rem;
	min-height: 0;
	height: 100%;
}

/* Pc card se estira todo lo posible */
.pc-card {
	flex: 1 1 auto;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.pc-card :deep(.p-card-body) {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	flex-direction: column;
}

.pc-card :deep(.p-card-content) {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
}

.pc-portfolio-content {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	margin-top: 0.5rem;
}

/* Extra Quota card mantiene altura natural */
.xq-card {
	flex: 0 0 auto;
}

.xq-header-row,
.abw-header-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 1rem;
	margin-top: 0.5rem;
	flex-wrap: wrap;
	gap: 0.5rem;
}

.xq-title,
.abw-title {
	font-weight: 700;
}

/* KPI pills con número dentro */
.abw-kpi-row {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}

.abw-pill {
	display: inline-flex;
	align-items: center;
	gap: 0.35rem;
	font-size: 0.8rem;
}

.pill-label {
	font-weight: 500;
}

.pill-value {
	font-weight: 700;
}

/* Responsive */
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

.font-mono {
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.white-space-pre-line {
	white-space: pre-line;
}

/* ========= DIALOGS STYLING ========= */
:deep(.dev-dialog-mask),
:deep(.xq-dialog-mask) {
	backdrop-filter: blur(4px);
	-webkit-backdrop-filter: blur(4px);
	background: rgba(15, 23, 42, 0.3);
}

:deep(.dev-dialog-shell),
:deep(.xq-dialog-shell) {
	border-radius: 16px;
	overflow: hidden;
	box-shadow: 0 18px 40px rgba(15, 23, 42, 0.35);
}

:deep(.dev-dialog-shell .p-dialog-header),
:deep(.xq-dialog-shell .p-dialog-header) {
	padding: 0.75rem 1rem;
	border-bottom: 1px solid rgba(148, 163, 184, 0.35);
}

:deep(.dev-dialog-shell .p-dialog-title),
:deep(.xq-dialog-shell .p-dialog-title) {
	font-size: 0.95rem;
	font-weight: 600;
	color: #0f172a;
}

:deep(.dev-dialog-shell .p-dialog-header-icon),
:deep(.xq-dialog-shell .p-dialog-header-icon) {
	width: 26px;
	height: 26px;
	border-radius: 999px;
	padding: 0;
	font-size: 0.8rem;
	color: #6b7280;
}

:deep(.dev-dialog-shell .p-dialog-header-icon:hover),
:deep(.xq-dialog-shell .p-dialog-header-icon:hover) {
	background: rgba(148, 163, 184, 0.2);
	color: #111827;
}

:deep(.dev-dialog-shell .p-dialog-content),
:deep(.xq-dialog-shell .p-dialog-content) {
	padding: 0.9rem 1rem 1.1rem 1rem;
}

/* Deviation dialog */
.dev-dialog {
	display: flex;
	flex-direction: column;
	gap: 0.9rem;
}

.dev-summary {
	display: flex;
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
	gap: 0.75rem;
	padding: 0.75rem 0.9rem;
	border-radius: 0.9rem;
	background: radial-gradient(circle at 0 0, #e0f2fe, #eef2ff 40%, #f9fafb 80%);
	border: 1px solid rgba(148, 163, 184, 0.4);
}

.dev-summary-main {
	display: flex;
	flex-direction: column;
	gap: 0.15rem;
}

.dev-pc-name {
	display: flex;
	align-items: center;
	gap: 0.4rem;
	font-weight: 600;
	color: #0f172a;
}

.dev-pc-icon {
	font-size: 0.9rem;
	color: #64748b;
}

.dev-meta-row {
	display: flex;
	align-items: center;
	gap: 0.4rem;
	font-size: 0.8rem;
	color: #64748b;
}

.dev-chip {
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid rgba(148, 163, 184, 0.5);
	background: rgba(255, 255, 255, 0.8);
	font-weight: 500;
}

.dev-dot {
	opacity: 0.7;
}

.dev-summary-side {
	display: flex;
	align-items: center;
	justify-content: flex-end;
}

/* MAIN: gráfico izquierda / resto derecha */
.dev-main {
	display: grid;
	grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
	gap: 0.75rem;
	align-items: flex-start;
	margin-top: 0.35rem;
}

.dev-main-left,
.dev-main-right {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	min-width: 0;
}

.dev-card {
	border-radius: 0.9rem;
	overflow: hidden;
}

/* Header de cards internas */
.dev-card-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 0.4rem 0.2rem;
}

.dev-card-title {
	font-size: 0.85rem;
	font-weight: 600;
	color: #0f172a;
}

/* Métricas */
.dev-metrics {
	display: flex;
	flex-direction: column;
	gap: 0.4rem;
}

.metric-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	font-size: 0.85rem;
	color: #334155;
}

.metric-row-delta {
	margin-top: 0.25rem;
	padding-top: 0.25rem;
	border-top: 1px dashed rgba(148, 163, 184, 0.5);
}

.metric-label {
	opacity: 0.85;
}

.metric-value {
	font-weight: 600;
	font-variant-numeric: tabular-nums;
}

.metric-delta {
	display: inline-flex;
	align-items: baseline;
	gap: 0.25rem;
}

.metric-delta-pct {
	font-size: 0.8rem;
	opacity: 0.85;
}

/* Begründung */
.dev-comment {
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
	font-size: 0.83rem;
	line-height: 1.45;
	white-space: pre-line;
	color: #0f172a;
	margin-bottom: 0.5rem;
}

.dev-comment.empty {
	font-family: inherit;
}

.dev-just-meta {
	display: flex;
	flex-wrap: wrap;
	gap: 0.4rem;
	font-size: 0.78rem;
	color: #64748b;
}

.dev-meta-chip {
	display: inline-flex;
	align-items: center;
	gap: 0.2rem;
	padding: 2px 6px;
	border-radius: 999px;
	background: rgba(148, 163, 184, 0.16);
}

/* Aktionsplan timeline */
.dev-plan-intro {
	font-size: 0.85rem;
	color: #0f172a;
	display: flex;
	align-items: center;
	margin-bottom: 0.4rem;
}

.dev-plan-empty {
	margin: 0.2rem 0 0;
}

.plan-timeline {
	list-style: none;
	margin: 0.35rem 0 0 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 0.35rem;
}

.plan-item {
	display: flex;
	align-items: flex-start;
	gap: 0.55rem;
}

.plan-bullet {
	position: relative;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 0.1rem;
	margin-top: 2px;
}

.plan-bullet .pi {
	font-size: 0.75rem;
}

.plan-line {
	width: 1px;
	flex: 1 1 auto;
	background: rgba(148, 163, 184, 0.7);
	margin-top: 2px;
}

.plan-content {
	flex: 1;
	min-width: 0;
}

.plan-title-row {
	display: flex;
	align-items: baseline;
	gap: 0.5rem;
	flex-wrap: wrap;
}

.plan-title {
	font-size: 0.85rem;
	font-weight: 600;
	color: #0f172a;
}

.plan-tag {
	font-size: 0.76rem;
	padding: 2px 6px;
	border-radius: 999px;
	background: rgba(96, 165, 250, 0.15);
	color: #1d4ed8;
}

.plan-desc {
	font-size: 0.8rem;
	color: #64748b;
	margin-top: 0.15rem;
}

/* Chart card */
.dev-chart-card :deep(.p-card-body) {
	padding-top: 0.5rem;
	display: flex;
	flex-direction: column;
}

.dev-chart-card :deep(.p-card-content) {
	flex: 1 1 auto;
	display: flex;
}

/* Extra Quota dialog */
.xq-dialog-body {
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
}

:deep(.xq-dialog-shell) {
	background: radial-gradient(circle at 20% 0, #f1f5f9, #ffffff 45%, #eef2ff 90%);
}

/* Manager notes */
.dev-notes-loading,
.dev-notes-empty {
	font-size: 0.8rem;
	padding: 0.25rem 0;
}

.dev-notes-list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 0.35rem;
}

.dev-note-item {
	padding: 0.25rem 0;
	border-bottom: 1px dashed rgba(148, 163, 184, 0.35);
}

.dev-note-item:last-child {
	border-bottom: none;
}

.dev-note-meta {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	font-size: 0.75rem;
	color: #6b7280;
}

.dev-note-author {
	font-weight: 600;
}

.dev-note-date {
	opacity: 0.9;
}

.dev-note-text {
	margin-top: 0.2rem;
	font-size: 0.8rem;
	color: #111827;
}

/* Nueva nota */
.dev-new-note {
	padding: 0.6rem 0.7rem 0.7rem;
	border-radius: 0.9rem;
	border: 1px solid rgba(148, 163, 184, 0.4);
	background: rgba(248, 250, 252, 0.9);
	display: flex;
	flex-direction: column;
	gap: 0.45rem;
}

.dev-new-note-header {
	margin-bottom: 0.1rem;
}

.dev-note-textarea {
	width: 100%;
	resize: vertical;
	min-height: 70px;
	max-height: 200px;
	padding: 0.45rem 0.55rem;
	border-radius: 0.6rem;
	border: 1px solid rgba(148, 163, 184, 0.7);
	font-size: 0.82rem;
	font-family: inherit;
	box-sizing: border-box;
}

.dev-note-textarea:focus-visible {
	outline: none;
	border-color: #3b82f6;
	box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.5);
}

.dev-note-actions {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	margin-top: 0.25rem;
}

/* Botón más grande para guardar Rückmeldung */
.dev-note-actions :deep(.dev-note-save-btn.p-button) {
	font-size: 0.9rem;
	padding: 0.45rem 1.1rem;
	border-radius: 999px;
	font-weight: 600;
}

/* Responsive: modal layout */
@media (max-width: 900px) {
	.dev-main {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
