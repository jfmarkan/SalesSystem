<!-- src/views/SalesManagerProDashboardPrime.vue -->
<template>
	<div class="view-wrap full-bleed">
		<div class="grid">
			<!-- IZQ 2/12 · lista limpia, transparente, alto completo -->
			<aside class="col-12 md:col-2">
				<div class="glass card-shadow panel aside">
					<Listbox
						v-model="selectedSeller"
						:options="sellerItems"
						optionValue="id"
						optionLabel="displayName"
						dataKey="id"
						class="seller-listbox"
					>
						<template #option="{ option }">
							<div class="seller-item">
								<div
									:class="[
										'avatar-ring',
										option.teamId === 1 ? 'team-alpha' : 'team-beta',
									]"
								>
									<Avatar
										v-if="option.photo"
										:image="option.photo"
										class="avatar-img"
										shape="circle"
									/>
									<Avatar
										v-else
										:label="initials(option.name)"
										class="avatar-initials"
										shape="circle"
									/>
								</div>
								<div class="seller-name">{{ option.displayName }}</div>
							</div>
						</template>
						<template #empty><div class="empty">Keine Einträge.</div></template>
					</Listbox>
				</div>
			</aside>

			<!-- DER 10/12 -->
			<section class="col-12 md:col-10">
				<div class="grid">
					<!-- Header controles -->
					<div class="col-12">
						<div class="glass card-shadow panel">
							<div class="flex align-items-center justify-content-between gap-2">
								<h3 class="m-0">Übersicht</h3>
								<div class="flex align-items-center gap-2">
									<SelectButton
										v-model="dataType"
										:options="typeOptions"
										optionLabel="label"
										optionValue="value"
										:allowEmpty="false"
									/>
									<SelectButton
										v-model="selectedPeriod"
										:options="periodOptions"
										optionLabel="label"
										optionValue="value"
										:allowEmpty="false"
									/>
									<Button
										icon="pi pi-chevron-left"
										class="p-button-rounded p-button-text"
										@click="shiftPeriod(-1)"
										:disabled="dataType === 'forecast' && periodOffset <= -12"
									/>
									<span class="pill"
										><i class="pi pi-calendar mr-2" />{{ periodLabel }}</span
									>
									<Button
										icon="pi pi-chevron-right"
										class="p-button-rounded p-button-text"
										@click="shiftPeriod(1)"
										:disabled="dataType === 'forecast' && periodOffset >= 12"
									/>
								</div>
							</div>
						</div>
					</div>

					<!-- 6 + 6 -->
					<div class="col-12 md:col-6">
						<div class="glass card-shadow panel">
							<div class="panel-head">
								<h3>Abweichungsbegründungen</h3>
								<div class="kpis flex align-items-center gap-2">
									<Tag severity="success" value="Fristgerecht" rounded />
									<strong>{{ kpiJust.inTerm }}</strong>
									<span class="sep">|</span>
									<Tag severity="warning" value="Verspätet" rounded />
									<strong>{{ kpiJust.outTerm }}</strong>
								</div>
							</div>

							<template v-if="dataType === 'ventas'">
								<DataTable
									:value="visibleJustifications"
									size="small"
									responsiveLayout="scroll"
									:rows="10"
									:paginator="true"
									:rowsPerPageOptions="[10, 20, 50]"
									paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
									currentPageReportTemplate="{first}–{last} von {totalRecords}"
									class="no-bg-table"
								>
									<Column
										field="date"
										header="Datum"
										:body="dateBody"
										style="width: 120px"
									/>
									<Column field="client" header="Kunde" style="width: 160px" />
									<Column field="reason" header="Grund" />
									<Column
										header="Status"
										:body="statusBody"
										style="width: 140px"
									/>
									<Column header="Frist" :body="termBody" style="width: 140px" />
									<template #empty
										><div class="empty">
											Keine Daten für den Zeitraum.
										</div></template
									>
								</DataTable>
							</template>
							<template v-else
								><div class="empty p-3">
									Dieser Bereich gilt für <strong>Vertrieb</strong>.
								</div></template
							>
						</div>
					</div>

					<div class="col-12 md:col-6">
						<div class="glass card-shadow panel">
							<div class="panel-head">
								<h3>Profitcenter</h3>
								<div class="breadcrumbs" v-if="selectedPC || selectedClient">
									<Button class="p-button-text p-button-sm" @click="resetPC"
										><i class="pi pi-home mr-2" />{{
											selectedSellerObj?.displayName || '—'
										}}</Button
									>
									<i class="pi pi-angle-right mx-2" v-if="selectedPC" />
									<Button
										v-if="selectedPC"
										class="p-button-text p-button-sm"
										@click="resetClient"
										>{{ selectedPC.name }}</Button
									>
									<i class="pi pi-angle-right mx-2" v-if="selectedClient" />
									<span v-if="selectedClient" class="crumb-current">{{
										selectedClient.name
									}}</span>
								</div>
							</div>

							<div v-if="!selectedPC" class="bars">
								<div
									v-for="pc in pcsAgg"
									:key="pc.id"
									class="bar-row"
									:title="pcTitle(pc)"
									@click="selectPC(pc)"
								>
									<div class="bar-label">
										<span class="dot" :style="{ background: pc.color }" />{{
											pc.name
										}}
									</div>
									<div class="bar-track">
										<div
											class="bar-fill"
											:style="{
												width: pc.achievedPct + '%',
												background: pc.color,
											}"
										/>
									</div>
									<div class="bar-val">{{ pc.achievedPct }}%</div>
								</div>
								<div v-if="!pcsAgg.length" class="empty">
									Keine Profitcenter vorhanden.
								</div>
							</div>

							<div v-else-if="!selectedClient">
								<div class="subhead">
									<strong>Kunden von {{ selectedPC.name }}</strong>
								</div>
								<DataTable
									:value="selectedPC.clients"
									size="small"
									responsiveLayout="scroll"
									class="no-bg-table"
								>
									<Column field="name" header="Kunde" />
									<Column
										field="sales"
										header="Umsatz"
										:body="currencyBody('sales')"
										style="width: 160px"
									/>
									<Column
										field="target"
										header="Ziel"
										:body="currencyBody('target')"
										style="width: 160px"
									/>
									<Column
										header="Abweichung"
										:body="deltaBody"
										style="width: 160px"
									/>
									<Column
										header=""
										:body="clientActionBody"
										style="width: 120px"
									/>
								</DataTable>
							</div>

							<div v-else class="client-detail">
								<div class="subhead">
									<strong>{{ selectedClient.name }}</strong> — Detail
								</div>
								<ul class="bullets">
									<li>
										<i class="pi pi-dollar mr-2" />Umsatz:
										<strong>{{ fmtCurrency(selectedClient.sales) }}</strong>
									</li>
									<li>
										<i class="pi pi-bullseye mr-2" />Ziel:
										<strong>{{ fmtCurrency(selectedClient.target) }}</strong>
									</li>
									<li>
										<i class="pi pi-chart-line mr-2" />Trend (3M):
										<strong>{{ trendLabel(selectedClient.trend3m) }}</strong>
									</li>
									<li>
										<i class="pi pi-exclamation-triangle mr-2" />Abweichungen:
										<strong>{{ selectedClient.deviations }}</strong>
									</li>
								</ul>
							</div>
						</div>
					</div>

					<!-- 4 + 4 + 4 -->
					<div class="col-12 md:col-4">
						<div class="glass card-shadow panel tall" @click="openExtraQuota">
							<div class="kpi-head">
								<h4>Zusatzquote</h4>
								<Tag :value="extraQuotaPct + '%'" rounded />
							</div>
							<ProgressBar :value="extraQuotaPct" />
							<div class="kpi-foot">Klicken für Analyse</div>
						</div>
					</div>

					<div class="col-12 md:col-4">
						<div class="glass card-shadow panel">
							<div class="kpi-head"><h4>Begründungen</h4></div>
							<div class="pairs">
								<div class="pair">
									<span class="label"
										><i
											class="pi pi-check-circle text-success mr-2"
										/>Fristgerecht</span
									><span class="value">{{ kpiJust.inTerm }}</span>
								</div>
								<div class="pair">
									<span class="label"
										><i class="pi pi-clock text-warn mr-2" />Verspätet</span
									><span class="value">{{ kpiJust.outTerm }}</span>
								</div>
							</div>
						</div>
					</div>

					<div class="col-12 md:col-4">
						<div class="glass card-shadow panel">
							<div class="kpi-head"><h4>Aktionspläne</h4></div>
							<div class="pairs">
								<div class="pair">
									<span class="label"
										><i
											class="pi pi-check-circle text-success mr-2"
										/>Erfüllt</span
									><span class="value">{{ kpiPlans.done }}</span>
								</div>
								<div class="pair">
									<span class="label"
										><i class="pi pi-times-circle text-danger mr-2" />Nicht
										erfüllt</span
									><span class="value">{{ kpiPlans.notDone }}</span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
		</div>

		<!-- Dialog -->
		<Dialog
			v-model:visible="showExtraQuota"
			modal
			:style="{ width: 'min(1100px, 95vw)' }"
			:breakpoints="{ '960px': '95vw', '640px': '100vw' }"
			header="Analyse der Zusatzquote"
			class="glass-modal"
		>
			<div class="dialog-subtitle">
				Verkäufer: <strong>{{ selectedSellerObj?.displayName }}</strong>
			</div>
		</Dialog>
	</div>
</template>

<script setup>
/* English names & comments, UI in German */
import { ref, computed, onMounted, watch } from 'vue'
import Listbox from 'primevue/listbox'
import Avatar from 'primevue/avatar'
import SelectButton from 'primevue/selectbutton'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import ProgressBar from 'primevue/progressbar'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

/* state */
const sellers = ref([])
const selectedSeller = ref(null)
const selectedPC = ref(null)
const selectedClient = ref(null)

const dataType = ref('ventas')
const selectedPeriod = ref('last_month')
const periodOffset = ref(0)
const showExtraQuota = ref(false)

/* JUSTIFICATIONS from server (drives the table) */
const justificationsRaw = ref([])   // <- se llena por API
const isLoadingJust = ref(false)
const loadErrorJust = ref(null)

/* options */
const typeOptions = [
  { label: 'Vertrieb', value: 'ventas' },
  { label: 'Forecast', value: 'forecast' },
]
const periodOptions = computed(() =>
  dataType.value === 'ventas'
    ? [
        { label: 'Letzter Monat', value: 'last_month' },
        { label: 'YTD bis letzten Monat', value: 'ytd_until_last' },
      ]
    : [{ label: 'Nächste 6 Monate (ohne aktuellen)', value: 'next_6' }],
)

/* names & sorting */
function splitName(full = '') {
  const p = String(full).trim().split(/\s+/).filter(Boolean)
  return p.length <= 1 ? { first: p[0] || '', last: '' } : { first: p.slice(0, -1).join(' '), last: p[p.length - 1] }
}
function displayName(full = '') { const { first, last } = splitName(full); return last ? `${last}, ${first}` : first }
function compareNachname(aName = '', bName = '') {
  const a = splitName(aName), b = splitName(bName)
  const c = a.last.localeCompare(b.last, 'de', { sensitivity: 'base' })
  return c !== 0 ? c : a.first.localeCompare(b.first, 'de', { sensitivity: 'base' })
}
const sellerItems = computed(() => {
  const arr = sellers.value.map(s => ({ ...s, displayName: displayName(s.name) }))
  arr.sort((a, b) => compareNachname(a.name, b.name))
  return arr
})
const selectedSellerObj = computed(() => sellerItems.value.find(s => s.id === selectedSeller.value) || null)

/* dashboard computed (uses justificationsRaw) */
const pcsAgg = computed(() => selectedSellerObj.value?.profitCenters ?? [])
const justificationsFiltered = computed(() => {
  // Si el server ignoró el rango, filtramos acá por las mismas fechas
  const all = Array.isArray(justificationsRaw.value) ? justificationsRaw.value : []
  if (dataType.value !== 'ventas') return []
  if (selectedPeriod.value === 'last_month') {
    const [s, e] = monthRangeFromOffset(-1 + periodOffset.value)
    return all.filter(a => inRange(a.date, s, e))
  }
  if (selectedPeriod.value === 'ytd_until_last') {
    const [s, e] = ytdUntilLastWithOffset(periodOffset.value)
    return all.filter(a => inRange(a.date, s, e))
  }
  return all
})
const visibleJustifications = computed(() => justificationsFiltered.value)
const kpiJust = computed(() => {
  const a = justificationsFiltered.value
  return { inTerm: a.filter(x => x.inTerm).length, outTerm: a.filter(x => !x.inTerm).length }
})
const kpiPlans = computed(() => selectedSellerObj.value?.plans ?? { done: 0, notDone: 0 })
const extraQuota = computed(() => selectedSellerObj.value?.extraQuota ?? { meta: 0, reached: 0 })
const extraQuotaPct = computed(() => { const { meta, reached } = extraQuota.value; return meta > 0 ? Math.min(100, Math.round((reached / meta) * 100)) : 0 })
const periodLabel = computed(() => {
  if (dataType.value === 'ventas') {
    if (selectedPeriod.value === 'last_month') { const [s] = monthRangeFromOffset(-1 + periodOffset.value); return `${labelMonth(s)} ${s.getFullYear()}` }
    const [s, e] = ytdUntilLastWithOffset(periodOffset.value)
    return `YTD: ${labelMonth(new Date(s.getFullYear(), 0, 1))} ${s.getFullYear()} – ${labelMonth(e)} ${e.getFullYear()}`
  }
  const [s, e] = next6MonthsWindow(periodOffset.value)
  return `${labelMonth(s)} ${s.getFullYear()} → ${labelMonth(e)} ${e.getFullYear()}`
})

/* actions */
function selectPC(pc) { selectedPC.value = pc; selectedClient.value = null }
function resetPC() { selectedPC.value = null; selectedClient.value = null }
function resetClient() { selectedClient.value = null }
function openExtraQuota() { showExtraQuota.value = true }
function shiftPeriod(d) { periodOffset.value += d }

/* helpers */
function initials(n = '') { return n.split(' ').filter(Boolean).map(w => w[0]?.toUpperCase()).slice(0, 2).join('') }
function dateBody(r) { return fmtDate(r.date) }
function statusBody(r) { const sev = r.justified ? 'success' : 'warning'; const txt = r.justified ? 'Begründet' : 'Offen'; return `<span class="p-tag p-tag-${sev} p-tag-rounded">${txt}</span>` }
function termBody(r) { const sev = r.inTerm ? 'success' : 'danger'; const txt = r.inTerm ? 'Fristgerecht' : 'Verspätet'; return `<span class="p-tag p-tag-${sev} p-tag-rounded">${txt}</span>` }
function currencyBody(f) { return (r) => fmtCurrency(r[f]) }
function deltaBody(r) { const d = r.sales - r.target; const cls = d >= 0 ? 'pos' : 'neg'; return `<span class="${cls}">${fmtCurrency(d)}</span>` }
function clientActionBody() { return `<button class="p-button p-button-text p-button-sm link-btn">Ansehen</button>` }

function fmtCurrency(n) { return Number(n || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }) }
function fmtDate(iso) { const d = new Date(iso); return isNaN(d) ? '—' : d.toLocaleDateString('de-DE') }
function trendLabel(v) { return v > 0 ? 'Aufwärts' : v < 0 ? 'Abwärts' : 'Stabil' }
function inRange(iso, s, e) { const d = new Date(iso); return !isNaN(d) && d >= s && d <= e }
function labelMonth(d) { return d.toLocaleString('de-DE', { month: 'long' }).replace(/^./, m => m.toUpperCase()) }
function monthRangeFromOffset(rel) { const now = new Date(); const y = now.getFullYear(); const m = now.getMonth() + rel; const s = new Date(y, m, 1); const e = new Date(y, m + 1, 0, 23, 59, 59, 999); return [s, e] }
function ytdUntilLastWithOffset(off) { const [, e] = monthRangeFromOffset(-1 + off); const s = new Date(e.getFullYear(), 0, 1); const eAdj = new Date(e.getFullYear(), e.getMonth() + 1, 0, 23, 59, 59, 999); return [s, eAdj] }
function next6MonthsWindow(off) { const now = new Date(); const s = new Date(now.getFullYear(), now.getMonth() + 1 + off, 1); const e = new Date(s.getFullYear(), s.getMonth() + 6, 0, 23, 59, 59, 999); return [s, e] }
function pcTitle(pc) { return `${pc.name}: ${pc.achievedPct}%` }

/* API helpers */
function extractRows(payload) {
  const cands = [payload?.data?.data, payload?.data, Array.isArray(payload) ? payload : null]
  for (const c of cands) if (Array.isArray(c)) return c
  return []
}
function normalizeRoleId(u) {
  const direct = Number(u.role_id ?? u.roleId)
  if (direct) return direct
  if (Array.isArray(u.roles)) {
    const r = u.roles.find(r => Number(r.id ?? r.role_id))
    if (r) return Number(r.id ?? r.role_id)
  }
  return Number(u.role?.id ?? 0)
}
function joinName(u) {
  const f = (u.first_name ?? u.firstName ?? '').toString().trim()
  const l = (u.last_name ?? u.lastName ?? '').toString().trim()
  const n = (u.name ?? '').toString().trim()
  return (f || l) ? `${f} ${l}`.trim() : n
}
function pickTeamId(u) {
  const flat = u.team_id ?? u.teamId
  const arr = (u.teams ?? u.team ?? [])[0]
  const arrId = arr ? (arr.id ?? arr.team_id ?? arr.teamId) : undefined
  const id = Number(flat ?? arrId ?? 0)
  return id || ((Number(u.id) % 2 === 0) ? 2 : 1)
}
function pickPhoto(u) { return u.avatar_url ?? u.photo_url ?? u.profile_photo_url ?? u.photo ?? null }

/* Load sellers (role_id=4) */
async function loadSellers() {
  try {
    await ensureCsrf()
    const { data } = await api.get('/api/sales-force/users', { params: { per_page: 1000, role_id: 4 } })
    const rows = extractRows(data).filter(u => normalizeRoleId(u) === 4)
    sellers.value = rows.map(u => ({
      id: Number(u.id),
      name: joinName(u),
      teamId: pickTeamId(u),
      photo: pickPhoto(u),
      email: u.email ?? null,
      profitCenters: u.profitCenters ?? [],
      plans: u.plans ?? { done: 0, notDone: 0 },
      extraQuota: u.extraQuota ?? { meta: 0, reached: 0 },
      opportunities: u.opportunities ?? [],
    })).sort((a, b) => compareNachname(a.name, b.name))
    selectedSeller.value = sellers.value[0]?.id ?? null
  } catch {
    sellers.value = []; selectedSeller.value = null
  }
}

/* Load JUSTIFICATIONS for selected user + period */
function normalizeDeviation(d) {
  return {
    id: Number(d.id ?? d.deviation_id ?? Math.random()*1e9),
    date: d.date ?? d.created_at ?? d.createdAt ?? d.occurred_at ?? d.updated_at ?? new Date().toISOString(),
    client: d.client_name ?? d.client?.name ?? d.customer?.name ?? d.client ?? '—',
    reason: d.reason ?? d.message ?? d.comment ?? '',
    justified: Boolean(d.justified ?? d.is_justified ?? d.status === 'justified'),
    inTerm: Boolean(d.in_term ?? d.inTerm ?? d.on_time),
  }
}
function periodParams() {
  if (dataType.value !== 'ventas') return {}
  if (selectedPeriod.value === 'last_month') {
    const [s, e] = monthRangeFromOffset(-1 + periodOffset.value)
    return { from: s.toISOString().slice(0,10), to: e.toISOString().slice(0,10) }
  }
  if (selectedPeriod.value === 'ytd_until_last') {
    const [s, e] = ytdUntilLastWithOffset(periodOffset.value)
    return { from: s.toISOString().slice(0,10), to: e.toISOString().slice(0,10) }
  }
  return {}
}
async function loadJustifications() {
  if (!selectedSeller.value || dataType.value !== 'ventas') { justificationsRaw.value = []; return }
  isLoadingJust.value = true; loadErrorJust.value = null
  try {
    await ensureCsrf()
    const params = { user_id: selectedSeller.value, ...periodParams() }
    const { data } = await api.get('/api/deviations', { params })
    const rows = extractRows(data)
    // si el backend no filtró por user, filtramos client-side con varias llaves comunes
    const byUser = rows.filter(d => {
      const uid = Number(d.user_id ?? d.userId ?? d.sales_rep_id ?? d.salesRepId ?? d.owner_id ?? d.ownerId)
      return uid === Number(selectedSeller.value)
    })
    const base = byUser.length ? byUser : rows
    justificationsRaw.value = base.map(normalizeDeviation)
  } catch (e) {
    loadErrorJust.value = 'Fehler beim Laden der Abweichungen.'
    justificationsRaw.value = []
  } finally {
    isLoadingJust.value = false
  }
}

/* wires */
onMounted(loadSellers)
watch([selectedSeller, dataType, selectedPeriod, periodOffset], loadJustifications, { immediate: true })
</script>

<style scoped>
.full-bleed {
	width: 100vw;
	margin-left: calc(50% - 50vw);
	margin-right: calc(50% - 50vw);
}
.view-wrap {
	min-height: calc(100vh - 80px);
	padding: 12px 24px 28px;
	box-sizing: border-box;
}

.glass {
	background: rgba(255, 255, 255, 0.45);
	border: 1px solid rgba(255, 255, 255, 0.35);
	backdrop-filter: blur(12px);
	-webkit-backdrop-filter: blur(12px);
	border-radius: 14px;
}
.card-shadow {
	box-shadow: 0 18px 50px rgba(0, 0, 0, 0.25);
}
@media (prefers-color-scheme: dark) {
	.glass {
		background: rgba(0, 0, 0, 0.38);
		border-color: rgba(255, 255, 255, 0.18);
		color: #e5e7eb;
	}
	.card-shadow {
		box-shadow: 0 24px 64px rgba(0, 0, 0, 0.66);
	}
}
.panel {
	padding: 10px;
}

/* ASIDE fijo y alto completo */
.aside {
	position: sticky;
	top: 70px;
	height: calc(100vh - 70px - 16px);
	display: flex;
	flex-direction: column;
}
/* ✅ La clase está en el MISMO nodo que .p-listbox */
:deep(.p-listbox.seller-listbox){
  display:flex !important;
  flex-direction:column !important;
  flex-wrap: wrap !important;
  height:auto !important;
  background:transparent !important;
  border:0 !important;
  box-shadow:none !important;
}

:deep(.p-listbox.seller-listbox .p-listbox-list-wrapper){
  max-height:none !important;
  height:80vh !important;
}

@media (prefers-color-scheme: dark) {
	:deep(.p-listbox.seller-listbox .p-listbox-item.p-highlight) {
		background: rgba(255, 255, 255, 0.08) !important;
	}
}

/* item lista */
.seller-item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 8px;
	border-radius: 10px;
}
.seller-name {
	font-weight: 500;
}

/* avatar + colores por team */
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
.team-beta {
	background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
}
.avatar-img {
	width: 100%;
	height: 100%;
	border-radius: 999px;
	background: #111;
}
.avatar-initials {
	width: 100%;
	height: 100%;
	border-radius: 999px;
	color: #fff;
	font-weight: 300;
	display: flex;
	align-items: center;
	justify-content: center;
}
.team-alpha .avatar-initials {
	background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
}
.team-beta .avatar-initials {
	background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
}

.pill {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 6px 12px;
	border-radius: 999px;
	background: rgba(0, 0, 0, 0.06);
	font-weight: 800;
}
.mr-2 {
	margin-right: 0.5rem;
}
.mx-2 {
	margin: 0 0.5rem;
}

/* tablas / barras */
.no-bg-table :deep(.p-datatable-wrapper),
.no-bg-table :deep(.p-datatable-table) {
	background: transparent;
}
.bars {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 10px;
}
.bar-row {
	display: flex;
	align-items: center;
	gap: 10px;
	cursor: pointer;
}
.bar-label {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	font-weight: 700;
	flex: 0 0 30%;
}
.dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
}
.bar-track {
	flex: 1 1 auto;
	height: 12px;
	border-radius: 999px;
	background: rgba(0, 0, 0, 0.06);
	overflow: hidden;
}
.bar-fill {
	height: 100%;
	border-radius: 999px;
	transition: width 0.35s ease;
}
.bar-val {
	width: 56px;
	text-align: right;
	font-weight: 800;
	opacity: 0.8;
}
.subhead {
	padding: 8px 12px;
	font-weight: 700;
}

/* KPIs */
.panel-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 6px;
}
.kpi-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 10px;
}
.kpi-foot {
	font-size: 0.85rem;
	opacity: 0.75;
	margin-top: 8px;
}
.pairs {
	display: flex;
	flex-direction: column;
	gap: 8px;
}
.pair {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.text-success {
	color: #059669;
}
.text-warn {
	color: #d97706;
}
.text-danger {
	color: #e11d48;
}
.tall {
	min-height: 300px;
}

/* dialog */
.glass-modal :deep(.p-dialog-content) {
	background: transparent;
}
.dialog-subtitle {
	margin-bottom: 10px;
	opacity: 0.85;
}

/* misc */
.empty {
	text-align: center;
	opacity: 0.75;
	padding: 12px;
}
.pos {
	color: #059669;
}
.neg {
	color: #e11d48;
}
.link-btn {
	cursor: pointer;
	color: var(--primary-color, #3b82f6);
	background: transparent;
	border: none;
}
.sep {
	opacity: 0.55;
}
</style>