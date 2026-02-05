<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'

const props = defineProps({
	title: { type: String, default: 'Zusatzquoten' },
	unit: { type: String, default: 'M3' },        // etiqueta visual
	target: { type: Number, default: 0 },         // m³ asignados
	achieved: { type: Number, default: 0 },       // m³ consumidos (open+won si aplica)
	items: { type: Array, default: () => [] },
	// mix: [{ key,label, amount (m³), color?, displayAmount?, displayUnit? }, ...] o { label: amountM3 }
	mix: { type: [Array, Object], default: null },
	scope: { type: String, default: 'self' },
	currentUserId: { type: [String, Number], default: null },
	currentUserName: { type: String, default: '' },
	pcDetail: { type: Object, default: () => null }
})

const router = useRouter()

/* Utils */
const PALETTE = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316', '#06b6d4', '#22c55e', '#eab308']
const pick = i => PALETTE[i % PALETTE.length]
const unitLabel = () => 'm³'
function fmt(n) {
	const v = Number(n) || 0
	if (Math.abs(v) >= 1e6) {
		return new Intl.NumberFormat('de-DE', {
			minimumFractionDigits: 2,
			maximumFractionDigits: 2
		}).format(v / 1e6) + ' M'
	}
	if (Math.abs(v) >= 1e3) {
		return new Intl.NumberFormat('de-DE', {
			minimumFractionDigits: 1,
			maximumFractionDigits: 1
		}).format(v / 1e3) + ' k'
	}
	return new Intl.NumberFormat('de-DE', {
		maximumFractionDigits: 0
	}).format(v)
}

function fmtUnit(n, u) {
	const v = Number(n) || 0
	return `${new Intl.NumberFormat('de-DE', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2
	}).format(v)} ${u || ''}`.trim()
}

/* Normaliza segmentos para barra (m³) y listado (unidad original si viene) */
function normalizeMix(m) {
	if (!m) return []
	const arr = Array.isArray(m) ? m : Object.entries(m).map(([label, amount]) => ({ label, amount }))
	return arr.map((s, i) => ({
		key: s.key ?? String(i),
		label: String(s.label ?? s.key ?? i),
		amountM3: Number(s.amount_m3 ?? s.amount ?? 0) || 0,      // usado para la barra
		color: s.color || pick(i),
		displayAmount: s.displayAmount ?? s.display_amount ?? null, // solo UI lista
		displayUnit: s.displayUnit ?? s.display_unit ?? null
	}))
}

/* Totales: porcentaje por ASIGNADO (alokiert). 0=rojo, 100=verde */
const totals = computed(() => {
	const totalAssigned = Math.max(0, Number(props.target) || 0)
	const rawUsed = Math.max(0, Number(props.achieved) || 0)
	const totalUsed = Math.min(totalAssigned || Infinity, rawUsed)
	const totalAvail = Math.max(0, totalAssigned - totalUsed)
	const pctAlloc = totalAssigned > 0 ? (totalUsed * 100 / totalAssigned) : 0
	return { totalAssigned, totalUsed, totalAvail, pctAlloc }
})
function toneAlloc(p) { if (p >= 66) return 'ok'; if (p >= 33) return 'mid'; return 'low' }

const baseSegs = computed(() => normalizeMix(props.mix))

/**
 * LEYENDA: solo TOP 3 segmentos por volumen (amountM3).
 * La barra sigue usando TODOS los segmentos (baseSegs),
 * pero la lista de abajo muestra solo los más relevantes.
 */
const listSegs = computed(() => {
	const T = totals.value.totalAssigned || 0
	const sorted = [...baseSegs.value].sort((a, b) => b.amountM3 - a.amountM3)
	const top3 = sorted.slice(0, 3)
	return top3.map(s => ({
		...s,
		pctOfTarget: T > 0 ? (s.amountM3 * 100 / T) : 0
	}))
})

function formatPercentComma(value) {
	const num = Number(value) || 0
	return new Intl.NumberFormat('de-DE', {
		minimumFractionDigits: 1,
		maximumFractionDigits: 2
	}).format(num) + ' %'
}

/* Detalle por PC (opcional) */
const pc = computed(() => {
	const d = props.pcDetail
	if (!d) return null
	const allocated = Math.max(0, Number(d.allocated) || 0)
	const won = Math.max(0, Math.min(allocated, Number(d.won) || 0))
	const lost = Math.max(0, Math.min(Math.max(0, allocated - won), Number(d.lost) || 0))
	const parsedOpen = Number(d.open)
	const open = Math.max(0, isFinite(parsedOpen) ? parsedOpen : Math.max(0, allocated - won - lost))
	const sum = (won + lost + open) || 1
	return {
		pcName: d.pcName ?? '',
		segs: [
			{ key: 'won', label: 'Gewonnen', val: won, pct: won * 100 / sum, color: '#10b981' },
			{ key: 'open', label: 'Offen', val: open, pct: open * 100 / sum, color: '#f59e0b' },
			{ key: 'lost', label: 'Verloren', val: lost, pct: lost * 100 / sum, color: '#ef4444' }
		],
		allocated, won, lost, open
	}
})

const expanded = ref(false)
watch(() => props.pcDetail, v => { expanded.value = !!v })

function goAnalysis(e) {
	e?.preventDefault?.()
	try { router.push({ name: 'ExtraQuotasAnalysis' }) }
	catch { router.push('/extra-quota/analyse') }
}
</script>

<template>
	<div class="xq-root">
		<!-- Header -->
		<div class="xq-title-row">
			<div class="xq-title">
				<span>{{ title }}</span>
				<a href="#" class="xq-link" @click.prevent="goAnalysis">Mehr anzeigen</a>
			</div>
			<div class="xq-actions">
				<button v-if="pc" class="xq-toggle" @click.stop.prevent="expanded = !expanded">
					{{ expanded ? 'Details ausblenden' : 'Details anzeigen' }}
				</button>
			</div>
		</div>

		<!-- KPIs -->
		<div class="xq-row">
			<div class="xq-kpis">
				<div class="xq-value">
					{{ fmt(totals.totalUsed) }} <span class="xq-unit">{{ unitLabel() }}</span>
				</div>
				<div class="xq-sub">
					Zugewiesen: {{ fmt(totals.totalAssigned) }} <span>{{ unitLabel() }}</span>
					· Verfügbar: {{ fmt(totals.totalAvail) }} <span>{{ unitLabel() }}</span>
				</div>
			</div>
			<div class="xq-badge" :class="toneAlloc(totals.pctAlloc)">
				<span>{{ Math.round(totals.pctAlloc) }}%</span>
			</div>
		</div>

		<!-- Barra -->
		<div v-if="baseSegs.length" class="xq-stack" aria-label="Zusammensetzung">
			<div
				v-for="s in baseSegs"
				:key="s.key"
				class="xq-seg"
				:style="{ width: (totals.totalAssigned > 0 ? (s.amountM3 * 100 / totals.totalAssigned) : 0) + '%', background: s.color }"
				:title="`${s.label}: ${fmt(s.amountM3)} m³`"
			></div>
			<div
				class="xq-marker"
				:style="{ left: (Math.min(100, (totals.totalUsed / Math.max(1, totals.totalAssigned)) * 100)) + '%' }"
				title="Alokation"
			></div>
		</div>
		<div v-else class="xq-empty">Keine Zusammensetzung verfügbar.</div>

		<!-- Lista: SOLO TOP 3 -->
		<ul v-if="listSegs.length" class="seg-list">
			<li v-for="s in listSegs" :key="s.key" class="seg-li">
				<div class="seg-left">
					<i class="seg-dot" :style="{ background: s.color }" />
					<span class="name">{{ s.label }}</span>
					<span class="uval">
						–
						{{
							s.displayAmount != null
								? fmtUnit(s.displayAmount, s.displayUnit)
								: fmtUnit(s.amountM3, 'm³')
						}}
					</span>
				</div>
				<div class="seg-pct">{{ formatPercentComma(s.pctOfTarget) }}</div>
			</li>
		</ul>

		<!-- Detalle PC -->
		<transition name="fade">
			<div v-if="expanded && pc" class="pc-detail">
				<div class="pc-title">{{ pc.pcName }}</div>
				<div class="pc-bar">
					<div v-for="s in pc.segs" :key="s.key" class="pc-seg"
						:style="{ width: s.pct + '%', background: s.color }"></div>
				</div>
				<div class="pc-legend">
					<span class="pitem"><i class="dot dot-win"></i> Gewonnen: {{ fmt(pc.won) }} {{ unitLabel() }}</span>
					<span class="pitem"><i class="dot dot-open"></i> Offen: {{ fmt(pc.open) }} {{ unitLabel() }}</span>
					<span class="pitem"><i class="dot dot-lost"></i> Verloren: {{ fmt(pc.lost) }} {{ unitLabel() }}</span>
					<span class="pitem sep"></span>
					<span class="pitem total">Zugewiesen: {{ fmt(pc.allocated) }} {{ unitLabel() }}</span>
				</div>
			</div>
		</transition>
	</div>
</template>

<style scoped>
.xq-root {
	position: relative;
	display: flex;
	flex-direction: column;
	height: 100%;
	gap: .5rem;
}

/* ===== HEADER ===== */
.xq-title-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: .5rem;
}

.xq-title {
	display: flex;
	align-items: center;
	justify-content: space-between;
	width: 100%;
	font-size: .9rem;
	font-weight: 600;
	color: #334155;
}

.xq-link {
	margin-left: auto;
	font-weight: 500;
	color: var(--primary-color, #3b82f6);
	text-decoration: none;
	font-size: .8rem;
}
.xq-link:hover {
	text-decoration: underline;
}

.xq-actions {
	display: flex;
	gap: .4rem;
}

.xq-toggle {
	border: 1px solid rgba(2, 6, 23, .15);
	background: transparent;
	color: inherit;
	border-radius: .5rem;
	padding: .25rem .5rem;
	font-size: .8rem;
	cursor: pointer;
}

/* ===== KPIs ===== */
.xq-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: .75rem;
}

.xq-kpis {
	display: flex;
	flex-direction: column;
	gap: .1rem;
}

.xq-value {
	font-size: 1.4rem;
	font-weight: 800;
	color: #0f172a;
}

.xq-unit {
	font-size: .9rem;
	font-weight: 600;
	opacity: .85;
}

.xq-sub {
	font-size: .8rem;
	color: #64748b;
	display: flex;
	gap: .5rem;
	flex-wrap: wrap;
}

/* ===== BADGE (0→rojo, 100→verde) ===== */
.xq-badge {
	min-width: 3.25rem;
	height: 2rem;
	padding: 0 .5rem;
	border-radius: .75rem;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #fff;
	font-weight: 700;
	background: linear-gradient(to bottom, #ef4444, #b91c1c);
}
.xq-badge.mid {
	background: linear-gradient(to bottom, #fb923c, #ea580c);
}
.xq-badge.ok {
	background: linear-gradient(to bottom, #34d399, #059669);
}

/* ===== BARRA APILADA ===== */
.xq-stack {
	position: relative;
	height: 12px;
	border-radius: 999px;
	overflow: hidden;
	display: flex;
	width: 100%;
	box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .06);
}
.xq-seg {
	height: 100%;
}
.xq-marker {
	position: absolute;
	top: -2px;
	bottom: -2px;
	width: 2px;
	background: #111827;
}

/* ===== LISTA DE SEGMENTOS (TOP 3) ===== */
.seg-list {
	list-style: none;
	padding: 0;
	margin: 4px 0 0 0;
	display: flex;
	flex-direction: column;
	gap: 6px;
}
.seg-li {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.seg-left {
	display: flex;
	align-items: center;
	gap: .45rem;
	min-width: 0;
}
.seg-dot {
	width: .65rem;
	height: .65rem;
	border-radius: 3px;
	display: inline-block;
}
.name {
	font-size: .85rem;
	color: #475569;
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.uval {
	font-size: .8rem;
	color: #64748b;
}
.seg-pct {
	font-size: .8rem;
	font-weight: 700;
	color: #0f172a;
}

/* ===== DETALLE POR PC ===== */
.pc-detail {
	display: flex;
	flex-direction: column;
	gap: .5rem;
	padding-top: .25rem;
}
.pc-title {
	font-size: .85rem;
	font-weight: 600;
}
.pc-bar {
	height: 10px;
	border-radius: 999px;
	overflow: hidden;
	display: flex;
	width: 100%;
	box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .06);
}
.pc-seg {
	height: 100%;
}
.pc-legend {
	display: flex;
	flex-wrap: wrap;
	gap: .5rem .9rem;
	font-size: .8rem;
	align-items: center;
}
.pitem {
	display: flex;
	align-items: center;
	gap: .4rem;
}
.pitem.total {
	font-weight: 700;
}
.pitem.sep {
	flex: 0 0 8px;
}
.dot-win {
	background: #10b981;
}
.dot-open {
	background: #f59e0b;
}
.dot-lost {
	background: #ef4444;
}

/* ===== TRANSICIÓN ===== */
.fade-enter-active,
.fade-leave-active {
	transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
	opacity: 0;
}
</style>
