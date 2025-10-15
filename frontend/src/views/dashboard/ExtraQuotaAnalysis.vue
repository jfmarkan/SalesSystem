<!-- src/views/dashboard/ExtraQuotaAnalysis.vue -->
<script setup>
/* English code & comments; UI strings German */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Button from 'primevue/button'
import api from '@/plugins/axios'

const props = defineProps({
	embedded: { type: Boolean, default: false },
	userId: { type: [Number, String], default: null },
})
const emit = defineEmits(['close'])

const router = useRouter()

/* Wirtschaftsjahr (Abr–Mar) */
const now = new Date()
const initialFYStart = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
const currentFYStart = initialFYStart
const fyStart = ref(initialFYStart)
const fyLabel = () => `${fyStart.value}/${String(fyStart.value + 1).slice(-2)}`
function prevFY() {
	if (fyStart.value > 2000) {
		fyStart.value--
		load()
	}
}
function nextFY() {
	if (fyStart.value < currentFYStart) {
		fyStart.value++
		load()
	}
}

/* State */
const loading = ref(false)
const errorMsg = ref('')
const items = ref([])
const totals = ref({})

/* Utils */
function fmtInt(n) {
	return Number(n || 0).toLocaleString('de-DE')
}
function weightOpen(r) {
	const direct = r.prob_weighted_open_m3 ?? r.in_progress_weighted_m3 ?? r.in_progress_m3_weighted
	if (direct != null) return Number(direct) || 0
	if (Array.isArray(r.open_opportunities)) {
		let sum = 0
		for (const o of r.open_opportunities) {
			const v = Number(o?.volume || 0)
			const p = Number(o?.probability_pct || 0)
			sum += v * (p / 100)
		}
		return Number(sum) || 0
	}
	if (r.in_progress_m3 != null && r.avg_open_probability_pct != null) {
		const base = Number(r.in_progress_m3) || 0
		const pAvg = Number(r.avg_open_probability_pct) || 0
		return base * (pAvg / 100)
	}
	return Number(r.in_progress_m3 || 0)
}

/* Load */
async function load() {
	loading.value = true
	errorMsg.value = ''
	try {
		const params = { fiscal_year: fyStart.value }
		if (props.userId) params.user_id = props.userId
		const { data } = await api.get('/api/extra-quota/analysis/summary', { params })
		const rows = Array.isArray(data?.items) ? data.items : []

		items.value = rows.map((r) => {
			const assigned = Math.max(0, Number(r.assigned_m3 || 0))
			const wonM3 = Math.max(0, Number(r.converted_m3 || 0))
			const openW = Math.max(0, weightOpen(r))
			const usedWeighted = Math.min(assigned, wonM3 + openW)
			const avail = Math.max(0, assigned - usedWeighted)

			const wonPct = assigned > 0 ? Math.min(100, (wonM3 * 100) / assigned) : 0
			const openPct = assigned > 0 ? Math.min(100 - wonPct, (openW * 100) / assigned) : 0
			const progress = Math.round(wonPct + openPct)

			return {
				name: r.profit_center_name || String(r.profit_center_code),
				assigned_m3: assigned,
				converted_m3: wonM3,
				in_progress_m3: openW,
				available_m3: avail,
				count_open: Number(r.count_open || 0),
				count_won: Number(r.count_won || 0),
				count_lost: Number(r.count_lost || 0),
				won_pct: Math.round(wonPct),
				open_pct: Math.round(openPct),
				progress_pct: progress,
			}
		})

		totals.value = data?.totals || {}
	} catch (e) {
		errorMsg.value = e?.response?.data?.message || 'Fehler beim Laden.'
	} finally {
		loading.value = false
	}
}
onMounted(load)

function goBack(e) {
	e?.preventDefault?.()
	if (props.embedded) {
		emit('close')
		return
	}
	try {
		router.back()
	} catch {
		router.push('/')
	}
}
</script>

<template>
	<div class="ana">
		<!-- Header en una tarjeta -->
		<div class="glass-card header-card">
			<div class="row-head">
				<div class="left-head">
					<a href="#" class="back-link" @click="goBack">&lt;- Zurück</a>
					<span class="vbar" aria-hidden="true"></span>
					<ul class="crumb">
						<li><span class="c1">Anwendung</span></li>
						<li class="sep"><i class="pi pi-angle-right" /></li>
						<li><span class="c2">Zusatzquoten Analyse</span></li>
					</ul>
				</div>
				<div class="ctrls">
					<span class="lbl">Wirtschaftsjahr</span>
					<Button icon="pi pi-angle-left" text @click="prevFY" />
					<span class="fy-label">{{ fyLabel() }}</span>
					<Button
						icon="pi pi-angle-right"
						text
						@click="nextFY"
						:disabled="fyStart >= currentFYStart"
					/>
				</div>
			</div>

			<div class="totals">
				<div class="tbox">
					<span>Zugewiesen</span><b>{{ fmtInt(totals.assigned_m3 || 0) }} m³</b>
				</div>
				<div class="tbox">
					<span>Überführt</span><b>{{ fmtInt(totals.converted_m3 || 0) }} m³</b>
				</div>
				<div class="tbox">
					<span>In Arbeit</span><b>{{ fmtInt(totals.in_progress_m3 || 0) }} m³</b>
				</div>
				<div class="tbox">
					<span>Verfügbar</span><b>{{ fmtInt(totals.available_m3 || 0) }} m³</b>
				</div>
				<div class="tbox">
					<span>Chancen</span><b>{{ fmtInt(totals.count_total || 0) }}</b>
				</div>
			</div>
		</div>

		<!-- Lista por Profit Center -->
		<div class="list">
			<div v-if="loading" class="loading">Lädt…</div>
			<template v-else>
				<div v-for="(r, i) in items" :key="i" class="glass-card pc-line">
					<div class="col-left">
						<div class="pc-name" :title="r.name">{{ r.name }}</div>

						<div class="bar-row">
							<div
								class="bar"
								:title="`Gewonnen: ${fmtInt(r.converted_m3)} m³ • Offen (gewichtet): ${fmtInt(r.in_progress_m3)} m³`"
							>
								<div class="seg won" :style="{ width: r.won_pct + '%' }"></div>
								<div class="seg open" :style="{ width: r.open_pct + '%' }"></div>
							</div>
							<div class="pct">{{ r.progress_pct }}%</div>
						</div>

						<div class="quotes-row">
							<div class="q">
								<i class="pi pi-database ico-quote"></i><span>Quote gesamt</span
								><b>{{ fmtInt(r.assigned_m3) }} m³</b>
							</div>
							<div class="q">
								<i class="pi pi-cog ico-work"></i><span>Quote in Arbeit</span
								><b>{{ fmtInt(r.in_progress_m3) }} m³</b>
							</div>
							<div class="q">
								<i class="pi pi-box ico-free"></i><span>Quote verfügbar</span
								><b>{{ fmtInt(r.available_m3) }} m³</b>
							</div>
						</div>
					</div>

					<div class="col-right">
						<div class="s">
							<i class="pi pi-folder-open ico-open"></i><span>offen</span
							><b>{{ fmtInt(r.count_open) }}</b>
						</div>
						<div class="s">
							<i class="pi pi-check-circle ico-won"></i><span>gewonnen</span
							><b>{{ fmtInt(r.count_won) }}</b>
						</div>
						<div class="s">
							<i class="pi pi-times-circle ico-lost"></i><span>verloren</span
							><b>{{ fmtInt(r.count_lost) }}</b>
						</div>
					</div>
				</div>

				<div v-if="!items.length" class="empty">Keine Daten</div>
			</template>

			<div v-if="errorMsg" class="err">{{ errorMsg }}</div>
		</div>
	</div>
</template>

<style scoped>
:global(:root) {
	--eq-green: #16a34a;
	--eq-yellow: #f59e0b;
	--eq-blue: #3b82f6;
	--eq-red: #ef4444;
	--eq-gray: #64748b;
}

.ana {
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

/* Card */
.glass-card {
	background: rgba(255, 255, 255, 0.4);
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	border: 1px solid rgba(0, 0, 0, 0.08);
	border-radius: 12px;
	padding: 12px;
}
@media (prefers-color-scheme: dark) {
	.glass-card {
		background: rgba(0, 0, 0, 0.4);
		border-color: rgba(255, 255, 255, 0.16);
	}
}

/* Header */
.header-card {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.row-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 10px;
	flex-wrap: wrap;
}
.left-head {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
}
.back-link {
	color: #10b981;
	font-weight: 300;
	text-decoration: none;
	letter-spacing: 0.1px;
}
.back-link:hover {
	text-decoration: underline;
}
.vbar {
	width: 2px;
	height: 16px;
	background: #0f172a;
	opacity: 0.85;
	border-radius: 1px;
}
@media (prefers-color-scheme: dark) {
	.vbar {
		background: #fff;
		opacity: 0.9;
	}
}
.crumb {
	list-style: none;
	padding: 0;
	margin: 0;
	display: flex;
	align-items: center;
	gap: 8px;
	font-weight: 700;
}
.c1 {
	color: #64748b;
}
.c2 {
	color: #0f172a;
}
.sep {
	color: #64748b;
}
@media (prefers-color-scheme: dark) {
	.c1 {
		color: #94a3b8;
	}
	.c2 {
		color: #e5e7eb;
	}
	.sep {
		color: #94a3b8;
	}
}

.ctrls {
	display: flex;
	gap: 8px;
	align-items: center;
}
.lbl {
	color: #64748b;
}
.fy-label {
	font-weight: 700;
	color: #0f172a;
	min-width: 84px;
	text-align: center;
}
@media (prefers-color-scheme: dark) {
	.lbl {
		color: #cbd5e1;
	}
	.fy-label {
		color: #e5e7eb;
	}
}

/* Totals */
.totals {
	display: grid;
	grid-template-columns: repeat(5, minmax(0, 1fr));
	gap: 10px;
}
.tbox {
	background: rgba(255, 255, 255, 0.35);
	border: 1px solid rgba(0, 0, 0, 0.08);
	border-radius: 10px;
	padding: 10px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
@media (prefers-color-scheme: dark) {
	.tbox {
		background: rgba(0, 0, 0, 0.35);
		border-color: rgba(255, 255, 255, 0.16);
	}
}
.tbox span {
	color: #475569;
	font-size: 12px;
}
.tbox b {
	color: #0f172a;
}
@media (prefers-color-scheme: dark) {
	.tbox span {
		color: #cbd5e1;
	}
	.tbox b {
		color: #e5e7eb;
	}
}

/* List */
.list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

/* PC line */
.pc-line {
	display: grid;
	grid-template-columns: 1fr 220px;
	gap: 12px;
	align-items: center;
	padding: 10px 20px;
}

/* Izquierda */
.col-left {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}
.pc-name {
	font-weight: 800;
	color: #0f172a;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
@media (prefers-color-scheme: dark) {
	.pc-name {
		color: #e5e7eb;
	}
}

/* Barra apilada */
.bar-row {
	display: grid;
	grid-template-columns: 1fr 54px;
	gap: 8px;
	align-items: center;
}
.bar {
	height: 10px;
	border-radius: 999px;
	overflow: hidden;
	display: flex;
	background: rgba(0, 0, 0, 0.08);
}
@media (prefers-color-scheme: dark) {
	.bar {
		background: rgba(255, 255, 255, 0.12);
	}
}
.seg {
	height: 100%;
}
.won {
	background: var(--eq-green, #16a34a) !important;
	border-right: solid 2px #fff;
}
.open {
	background: var(--eq-yellow, #f59e0b) !important;
}
.pct {
	text-align: right;
	font-weight: 700;
	color: #0f172a;
}
@media (prefers-color-scheme: dark) {
	.pct {
		color: #e5e7eb;
	}
}

/* Quote badges */
.quotes-row {
	display: flex;
	flex-wrap: wrap;
	gap: 10px 16px;
	align-items: center;
}
.q {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 12px;
	color: #475569;
}
.q b {
	color: #0f172a;
}
@media (prefers-color-scheme: dark) {
	.q {
		color: #cbd5e1;
	}
	.q b {
		color: #e5e7eb;
	}
}

/* Status counts */
.col-right {
	display: flex;
	flex-direction: column;
	gap: 6px;
	justify-self: end;
}
.s {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 12px;
	color: #475569;
}
.s b {
	color: #0f172a;
}
@media (prefers-color-scheme: dark) {
	.s {
		color: #cbd5e1;
	}
	.s b {
		color: #e5e7eb;
	}
}

/* Icons */
.pi.ico-quote {
	color: var(--eq-blue, #3b82f6) !important;
}
.pi.ico-work {
	color: var(--eq-yellow, #f59e0b) !important;
}
.pi.ico-free {
	color: var(--eq-gray, #64748b) !important;
}
.pi.ico-open {
	color: var(--eq-yellow, #f59e0b) !important;
}
.pi.ico-won {
	color: var(--eq-green, #16a34a) !important;
}
.pi.ico-lost {
	color: var(--eq-red, #ef4444) !important;
}

/* Misc */
.loading,
.empty {
	color: #94a3b8;
	text-align: center;
	padding: 8px;
}
.err {
	color: #ef4444;
	text-align: center;
	padding: 8px;
}

/* Responsive */
@media (max-width: 960px) {
	.pc-line {
		grid-template-columns: 1fr;
		padding: 10px 14px;
	}
	.col-right {
		justify-self: start;
		flex-direction: row;
		flex-wrap: wrap;
	}
}
</style>