<!-- src/views/SalesManagerProDashboardPrime.vue -->
<template>
	<div class="view-wrap full-bleed" style="--top-offset: 80px">
		<div class="grid">
			<!-- IZQ: LISTA VENDEDORES -->
			<aside class="col-12 md:col-2">
				<div class="glass card-shadow panel aside">
					<Listbox
						v-model="selectedSeller"
						:options="sellerItems"
						optionValue="id"
						optionLabel="__displayName"
						dataKey="id"
						class="seller-listbox"
						listStyle="max-height: 90vh"
					>
						<template #option="{ option }">
							<div class="seller-item">
								<div :class="['avatar-ring', teamClass(option)]">
									<Avatar
										v-if="option.__photo"
										:image="option.__photo"
										class="avatar-img"
										shape="circle"
									/>
									<Avatar
										v-else
										:label="initials(option.__displayName)"
										class="avatar-initials"
										shape="circle"
									/>
								</div>
								<div class="seller-name">{{ option.__displayName }}</div>
							</div>
						</template>
						<template #empty><div class="empty">Keine Einträge.</div></template>
					</Listbox>
				</div>
			</aside>

			<!-- DER -->
			<section class="col-12 md:col-10">
				<div class="glass card-shadow panel header-strip">
					<div class="flex align-items-center justify-content-between gap-2">
						<h3 class="m-0">Übersicht</h3>
						<div class="flex align-items-center gap-2">
							<Button
								icon="pi pi-chevron-left"
								class="p-button-rounded p-button-text"
								@click="shiftPeriod(-1)"
							/>
							<span class="pill"
								><i class="pi pi-calendar mr-2" />{{ periodLabel }}</span
							>
							<Button
								icon="pi pi-chevron-right"
								class="p-button-rounded p-button-text"
								@click="shiftPeriod(1)"
							/>
						</div>
					</div>
				</div>

				<div class="grid">
					<!-- Tabla de desvíos -->
					<div class="col-12 md:col-8">
						<div class="glass card-shadow panel tall-vh">
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

							<DataTable
								:value="deviationsSorted"
								responsiveLayout="scroll"
								:rows="10"
								:paginator="true"
								paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
								currentPageReportTemplate="{first}–{last} von {totalRecords}"
								class="table-plain table-flex roomy-rows"
								:loading="isLoadingDevs"
								@row-click="onDeviationRowClick"
							>
								<Column header="Typ" style="width: 140px">
									<template #body="{ data: r }">
										<span
											v-if="String(r.type).toLowerCase() === 'forecast'"
											class="pill-tag pill-forecast"
											>Forecast</span
										>
										<span v-else class="pill-tag pill-ist">Ist</span>
									</template>
								</Column>

								<Column header="Profitcenter" style="width: 280px">
									<template #body="{ data: r }">
										<span v-if="r.pcName">{{ r.pcName }}</span>
									</template>
								</Column>

								<Column header="Delta" style="width: 160px">
									<template #body="{ data: r }">
										<span :class="Number(r.deltaAbs || 0) >= 0 ? 'pos' : 'neg'">
											{{ fmtCurrency(Number(r.deltaAbs || 0)) }}
										</span>
									</template>
								</Column>

								<Column header="Plan" style="width: 130px">
									<template #body="{ data: r }">
										<span
											v-if="hasPlan(r)"
											class="p-tag p-tag-success p-tag-rounded"
											>Plan</span
										>
										<span v-else class="p-tag p-tag-secondary p-tag-rounded"
											>Kein Plan</span
										>
									</template>
								</Column>

								<Column header="Status" style="width: 160px">
									<template #body="{ data: r }">
										<span
											v-if="isOverdue(r)"
											class="p-tag p-tag-danger p-tag-rounded"
											>Überfällig</span
										>
										<span
											v-else
											class="p-tag p-tag-rounded"
											:class="r.justified ? 'p-tag-success' : 'p-tag-warning'"
										>
											{{ r.justified ? 'Begründet' : 'Offen' }}
										</span>
									</template>
								</Column>

								<Column header="" style="width: 120px">
									<template #body="{ data: r }">
										<Button
											label="Ansehen"
											class="p-button-text p-button-sm"
											@click.stop="openDeviation(r)"
										/>
									</template>
								</Column>

								<template #empty>
									<div class="empty">Keine Daten für den Zeitraum.</div>
								</template>
							</DataTable>
						</div>
					</div>

					<!-- KPIs/otros -->
					<div class="col-12 md:col-4">
						<div class="glass card-shadow panel">
							<div class="panel-head"><h3>Profitcenter</h3></div>
							<div class="empty">
								Profitcenter werden im nächsten Schritt geladen.
							</div>
						</div>

						<div class="glass card-shadow panel">
							<div class="kpi-head">
								<h4>Zusatzquote</h4>
								<Tag :value="extraQuotaPct + '%'" rounded />
							</div>
							<ProgressBar :value="extraQuotaPct" />
							<div class="kpi-foot">Klicken für Analyse</div>
						</div>

						<div class="glass card-shadow panel">
							<div class="kpi-head"><h4>Begründungen</h4></div>
							<div class="pairs">
								<div class="pair">
									<span class="label"
										><i
											class="pi pi-check-circle text-success mr-2"
										/>Fristgerecht</span
									>
									<span class="value">{{ kpiJust.inTerm }}</span>
								</div>
								<div class="pair">
									<span class="label"
										><i class="pi pi-clock text-warn mr-2" />Verspätet</span
									>
									<span class="value">{{ kpiJust.outTerm }}</span>
								</div>
							</div>
						</div>

						<div class="glass card-shadow panel">
							<div class="kpi-head"><h4>Aktionspläne</h4></div>
							<div class="pairs">
								<div class="pair">
									<span class="label"
										><i
											class="pi pi-check-circle text-success mr-2"
										/>Erfüllt</span
									>
									<span class="value">{{ kpiPlans.done }}</span>
								</div>
								<div class="pair">
									<span class="label"
										><i class="pi pi-times-circle text-danger mr-2" />Nicht
										erfüllt</span
									>
									<span class="value">{{ kpiPlans.notDone }}</span>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
		</div>

		<!-- MODAL -->
		<Dialog
			v-model:visible="showDeviationModal"
			modal
			appendTo="body"
			:draggable="false"
			:blockScroll="true"
			:style="{ width: 'min(900px, 96vw)' }"
			:breakpoints="{ '960px': '96vw', '640px': '100vw' }"
			header="Abweichung · Detail"
			class="glass-modal"
		>
			<template v-if="activeDeviation">
				<div class="dev-header">
					<div class="dev-title">
						<i class="pi pi-sliders-h mr-2" />
						<span>{{ activeDeviation.pcName || '—' }}</span>
						<span class="sep-dot">·</span>
						<span>{{ typeLabel(activeDeviation.type) }}</span>
						<span class="sep-dot">·</span>
						<span>{{ periodText(activeDeviation.year, activeDeviation.month) }}</span>
					</div>
					<div>
						<span
							v-if="isOverdue(activeDeviation)"
							class="p-tag p-tag-danger p-tag-rounded"
							>Überfällig</span
						>
						<span
							v-else
							class="p-tag p-tag-rounded"
							:class="activeDeviation.justified ? 'p-tag-success' : 'p-tag-warning'"
						>
							{{ activeDeviation.justified ? 'Begründet' : 'Offen' }}
						</span>
					</div>
				</div>

				<div class="grid gap-3">
					<div class="col-12 md:col-6">
						<div class="inner glass card-shadow">
							<h4 class="inner-title">Kennzahlen</h4>
							<ul class="kv">
								<li>
									<span>Umsatz</span
									><strong>{{ fmtCurrency(activeDeviation.sales) }}</strong>
								</li>
								<li>
									<span>Budget</span
									><strong>{{ fmtCurrency(activeDeviation.budget) }}</strong>
								</li>
								<li>
									<span>Forecast</span
									><strong>{{ fmtCurrency(activeDeviation.forecast) }}</strong>
								</li>
								<li>
									<span>Delta</span>
									<strong
										:class="
											(activeDeviation.deltaAbs ?? 0) >= 0 ? 'pos' : 'neg'
										"
									>
										{{ fmtCurrency(activeDeviation.deltaAbs) }}
										<small
											>({{
												Math.round(activeDeviation.deltaPct ?? 0)
											}}%)</small
										>
									</strong>
								</li>
								<li>
									<span>Status</span>
									<strong>
										<span v-if="isOverdue(activeDeviation)">Überfällig</span>
										<span v-else>{{
											activeDeviation.justified ? 'Begründet' : 'Offen'
										}}</span>
									</strong>
								</li>
							</ul>
						</div>
					</div>

					<div class="col-12 md:col-6">
						<div class="inner glass card-shadow">
							<h4 class="inner-title">Begründung</h4>
							<p
								class="mono"
								v-if="
									activeDeviation.comment && activeDeviation.comment.trim().length
								"
							>
								{{ activeDeviation.comment }}
							</p>
							<p v-else class="muted">Keine Begründung angegeben.</p>
							<div
								class="muted small"
								v-if="activeDeviation.justAuthor || activeDeviation.justDate"
							>
								<i class="pi pi-user mr-1" v-if="activeDeviation.justAuthor" />{{
									activeDeviation.justAuthor || ''
								}}
								<span
									v-if="activeDeviation.justAuthor && activeDeviation.justDate"
									class="mx-2"
									>·</span
								>
								<i class="pi pi-calendar mr-1" v-if="activeDeviation.justDate" />{{
									activeDeviation.justDate
										? fmtDate(activeDeviation.justDate)
										: ''
								}}
							</div>
						</div>
					</div>

					<div class="col-12">
						<div class="inner glass card-shadow">
							<h4 class="inner-title">Aktionsplan</h4>
							<p
								v-if="
									activeDeviation.plan &&
									String(activeDeviation.plan).trim().length
								"
								class="mono"
							>
								<i class="pi pi-flag mr-2" />{{ activeDeviation.plan }}
							</p>
							<template
								v-if="
									Array.isArray(activeDeviation.actions) &&
									activeDeviation.actions.length
								"
							>
								<ul class="actions">
									<li v-for="(a, i) in activeDeviation.actions" :key="i">
										<i
											:class="[
												'pi',
												a.done
													? 'pi-check-circle text-success'
													: 'pi-circle',
											]"
										/>
										<span class="ml-2">{{ a.title || '—' }}</span>
										<span class="muted ml-2" v-if="a.due"
											>· Fällig: {{ fmtDate(a.due) }}</span
										>
										<span class="muted ml-2" v-if="a.desc">· {{ a.desc }}</span>
									</li>
								</ul>
							</template>
							<p v-else class="muted">Kein Aktionsplan vorhanden.</p>
						</div>
					</div>
				</div>
			</template>

			<template #footer>
				<Button label="Schließen" icon="pi pi-check" @click="showDeviationModal = false" />
			</template>
		</Dialog>
	</div>
</template>

<script setup>
/* English code & comments; UI strings German */
import { ref, computed, onMounted, watch } from 'vue'
import Listbox from 'primevue/listbox'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import ProgressBar from 'primevue/progressbar'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

/* ========= STATE ========= */
const sellers = ref([])
const selectedSeller = ref(null)

const showDeviationModal = ref(false)
const activeDeviation = ref(null)
const isLoadingDevs = ref(false)
const deviations = ref([])

const periodOffset = ref(0)
const periodLabel = computed(() => {
  const [s] = monthRangeFromOffset(periodOffset.value)
  return `${labelMonth(s)} ${s.getFullYear()}`
})

/* ========= KPIs ========= */
const kpiJust = computed(() => {
  const a = deviations.value || []
  const today = new Date()
  const curY = today.getFullYear()
  const curM = today.getMonth() + 1
  const day  = today.getDate()
  let inTerm = 0, outTerm = 0
  for (const d of a) {
    const justified = !!d.justified
    const isThisMonth = Number(d.year) === curY && Number(d.month) === curM
    const overdue = !justified && isThisMonth && day > 10
    if (justified) inTerm++
    else outTerm++ // incluye Überfällig
  }
  return { inTerm, outTerm }
})
const kpiPlans = computed(() => ({ done: 0, notDone: 0 }))
const extraQuotaPct = computed(() => 0)

/* ========= HELPERS ========= */
function labelMonth(d) { return d.toLocaleString('de-DE', { month: 'long' }).replace(/^./, m => m.toUpperCase()) }
function monthRangeFromOffset(off = 0) {
  const now = new Date()
  const base = new Date(now.getFullYear(), now.getMonth() + off, 1)
  const start = new Date(base.getFullYear(), base.getMonth(), 1)
  const end = new Date(base.getFullYear(), base.getMonth() + 1, 0, 23, 59, 59, 999)
  return [start, end]
}
function fmtDate(iso) { const d = new Date(String(iso)); return isNaN(d) ? '—' : d.toLocaleDateString('de-DE') }
function fmtCurrency(n) { return Number(n || 0).toLocaleString('de-DE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }) }
function initials(n = '') { return n.split(' ').filter(Boolean).map(w => w[0]?.toUpperCase()).slice(0, 2).join('') }
function periodText(y, m) { const date = new Date(Number(y), Number(m) - 1, 1); return `${labelMonth(date)} ${date.getFullYear()}` }
function typeLabel(type) { return String(type||'').toLowerCase() === 'forecast' ? 'Forecast' : 'Ist' }
function hasPlan(r){ return !!r?.plan || (Array.isArray(r?.actions) && r.actions.length > 0) }
function isOverdue(row) {
  const justified = !!row?.justified
  const today = new Date()
  const curY = today.getFullYear()
  const curM = today.getMonth() + 1
  const day  = today.getDate()
  const isThisMonth = Number(row?.year) === curY && Number(row?.month) === curM
  return !justified && isThisMonth && day > 10
}

/* ========= TEAM NORMALIZATION ========= */
function getTeamId(u) {
  const candidates = [
    u?.team_id,
    u?.teamId,
    u?.team?.id,
    Array.isArray(u?.teams) ? u.teams[0]?.id : undefined,
    Array.isArray(u?.team_members) ? u.team_members[0]?.team_id : undefined,
    Array.isArray(u?.memberships) ? u.memberships[0]?.team_id : undefined,
  ].filter(v => v !== undefined && v !== null)
  for (const c of candidates) {
    const n = Number(c)
    if (Number.isFinite(n) && n > 0) return n
  }
  return NaN
}
function teamClass(u) {
  const tid = Number(u?.__teamId ?? getTeamId(u))
  if (Number.isFinite(tid)) return (tid % 2) ? 'team-alpha' : 'team-beta' // o mapea 1/2 directo si querés
  const id = Number(u?.id ?? 0)
  return id % 2 ? 'team-alpha' : 'team-beta'
}

/* ========= SELLERS ADAPTER ========= */
function displayNameFromApiUser(u = {}) {
  const name = u.name || [u.first_name ?? u.firstName, u.last_name ?? u.lastName].filter(Boolean).join(' ').trim()
  const p = String(name || '').trim().split(/\s+/).filter(Boolean)
  if (p.length <= 1) return p[0] || ''
  const last = p[p.length - 1]
  const first = p.slice(0, -1).join(' ')
  return `${last}, ${first}`
}
const sellerItems = computed(() => {
  const arr = Array.isArray(sellers.value) ? sellers.value.slice() : []
  for (const u of arr) {
    u.__displayName = displayNameFromApiUser(u)
    u.__photo = u.avatar_url ?? u.photo_url ?? u.profile_photo_url ?? u.photo ?? null
    u.__teamId = getTeamId(u) // normalizado
  }
  arr.sort((a, b) => String(a.__displayName).localeCompare(String(b.__displayName), 'de', { sensitivity: 'base' }))
  return arr
})

/* ========= TABLE ORDER ========= */
const deviationsSorted = computed(() => {
  return [...(deviations.value || [])].sort((a, b) =>
    String(a.pcCode || '').localeCompare(String(b.pcCode || ''), 'de', { sensitivity: 'base' })
  )
})

/* ========= UI Actions ========= */
function shiftPeriod(delta) { periodOffset.value += delta }
function onDeviationRowClick(e) { openDeviation(e?.data) }
function openDeviation(row) { if (!row) return; activeDeviation.value = { ...row }; showDeviationModal.value = true }
function openTestModal() {
  activeDeviation.value = {
    pcName: 'PC Demo', type: 'forecast',
    year: new Date().getFullYear(), month: new Date().getMonth() + 1,
    sales: 100000, budget: 120000, forecast: 110000,
    deltaAbs: -10000, deltaPct: -8.3, justified: false,
    comment: 'Beispielkommentar', justAuthor: 'Max Mustermann', justDate: new Date().toISOString(),
    plan: 'Umsatz durch Kampagne Q4 steigern',
    actions: [{ title: 'Kampagne starten', desc: 'E-Mail + Ads', due: '2025-11-05', done: false }],
  }
  showDeviationModal.value = true
}

/* ========= API Utils ========= */
function extractArrayAny(resp) {
  const root = resp?.data ?? resp
  if (Array.isArray(root)) return root
  if (Array.isArray(root?.data)) return root.data
  return []
}

/* ========= API Calls ========= */
async function loadSellers() {
  try {
    await ensureCsrf()
    // Back ya filtra: role_id=4, disabled=0, y resuelve team_id por team_members
    const { data } = await api.get('/api/sales-force/users', { params: { per_page: 1000 } })
    const rows = Array.isArray(data) ? data : []
    // Defensa extra si el back cambia
    const filtered = rows.filter(u => Number(u.role_id) === 4 && Number(u.disabled) === 0)
    sellers.value = filtered
    if (!selectedSeller.value && filtered.length) selectedSeller.value = filtered[0].id
  } catch {
    sellers.value = []
    selectedSeller.value = null
  }
}

async function loadDeviationsForMonth() {
  const uid = selectedSeller.value
  if (!uid) { deviations.value = []; return }
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

/* ========= WIRES ========= */
onMounted(async () => {
  await loadSellers()
  await loadDeviationsForMonth()
  // Debug opcional
  // console.table((sellers.value || []).map(u => ({ id: u.id, team_id: u.team_id, __teamId: getTeamId(u) })))
})
watch([selectedSeller, periodOffset], () => { loadDeviationsForMonth() })
</script>

<style scoped>
/* Overlay modal arriba de todo */
:deep(.p-dialog-mask) {
	z-index: 10010;
}

/* Full-bleed */
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

/* Glass */
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
.header-strip {
	margin-bottom: 12px;
}

/* Aside */
.aside {
	position: sticky;
	top: var(--top-offset, 80px);
	height: calc(100vh - var(--top-offset, 80px) - 16px);
	display: flex;
	flex-direction: column;
	min-height: 0;
}
.seller-listbox {
	flex: 1 1 auto;
	min-height: 0;
	height: 100%;
}
:deep(.p-listbox.seller-listbox) {
	background: transparent;
	border: 0;
	box-shadow: none;
	display: flex;
	flex-direction: column;
	height: 100%;
	min-height: 0;
}
:deep(.p-listbox.seller-listbox .p-listbox-list-wrapper) {
	flex: 1 1 auto;
	min-height: 0;
	max-height: none;
	height: 100%;
	overflow: auto;
}
:deep(.p-listbox.seller-listbox .p-listbox-list) {
	max-height: none;
}
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

/* Avatar + ring */
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

/* Quitar fondo gris default del Avatar (imagen o iniciales) */
:deep(.p-avatar.avatar-img),
:deep(.p-avatar.avatar-initials) {
	background: transparent !important;
	border: 0 !important;
}

/* Contenido del avatar transparente, el color lo da el ring */
.avatar-img,
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

/* Panel alto */
.tall-vh {
	height: 82vh;
}
.table-flex :deep(.p-datatable-wrapper) {
	flex: 1 1 auto;
	min-height: 0;
	max-height: none;
	overflow: auto;
}

/* Pills Typ */
.pill-tag {
	display: inline-flex;
	align-items: center;
	padding: 0.25rem 0.6rem;
	border-radius: 999px;
	font-weight: 700;
	font-size: 0.85rem;
	line-height: 1;
}
.pill-forecast {
	background: #d8a406;
	color: #000;
} /* amarillo */
.pill-ist {
	background: #456287;
	color: #fff;
} /* azul */

.panel-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 6px;
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
.sep {
	opacity: 0.55;
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
.empty {
	text-align: center;
	opacity: 0.75;
	padding: 12px;
}

/* Modal visuals */
.glass-modal :deep(.p-dialog-content) {
	background: transparent;
}
.dev-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 10px;
}
.dev-title {
	font-weight: 800;
	display: flex;
	align-items: center;
	gap: 6px;
	flex-wrap: wrap;
}
.sep-dot {
	opacity: 0.6;
	margin: 0 6px;
}
.inner {
	padding: 12px;
	border-radius: 12px;
}
.inner-title {
	margin: 0 0 8px 0;
	font-size: 1rem;
	font-weight: 800;
}
.kv {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}
.kv li {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.kv li span {
	opacity: 0.8;
}
.kv li strong small {
	opacity: 0.7;
	font-weight: 600;
	margin-left: 6px;
}
.mono {
	white-space: pre-wrap;
	font-family:
		ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New',
		monospace;
}
.muted {
	opacity: 0.75;
}
.small {
	font-size: 0.9rem;
}
.actions {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
}
.ml-2 {
	margin-left: 0.5rem;
}
.pos {
	color: #059669;
}
.neg {
	color: #e11d48;
}
</style>

<!-- NO SCOPED: tabla realmente transparente -->
<style>
.table-plain .p-datatable-wrapper,
.table-plain table,
.table-plain .p-datatable-header,
.table-plain .p-datatable-footer,
.table-plain .p-paginator,
.table-plain thead > tr > th,
.table-plain tbody > tr > td {
	background-color: transparent !important;
}
.table-plain thead > tr > th {
	border: 0;
	color: inherit;
}
.table-plain tbody > tr > td {
	color: inherit;
	border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}
.table-plain tbody > tr:hover {
	background-color: rgba(0, 0, 0, 0.04);
}
@media (prefers-color-scheme: dark) {
	.table-plain tbody > tr > td {
		border-bottom: 1px solid rgba(255, 255, 255, 0.08);
	}
	.table-plain tbody > tr:hover {
		background-color: rgba(255, 255, 255, 0.06);
	}
}
.table-plain tbody > tr > td {
	padding: 0.9rem 0.75rem;
}
.table-plain thead > tr > th {
	padding: 0.85rem 0.75rem;
}
</style>