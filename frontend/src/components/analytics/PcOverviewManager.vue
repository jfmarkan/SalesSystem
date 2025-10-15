<!-- src/components/analytics/PcBarsByUser.vue -->
<script setup>
import { ref, watch, onMounted } from 'vue'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import api from '@/plugins/axios'

const props = defineProps({
	userId: { type: [Number, String], required: true },
	unit: { type: String, default: 'M3' }, // VKEH | M3 | EUR
	period: { type: String, default: '' }, // opcional YYYY-MM
})

const loading = ref(false)
const error = ref('')
const rows = ref([]) // [{ code,name,ist,fc,bud }]
const totals = ref({ ist: 0, fc: 0, bud: 0 })

function toNum(x) {
	if (x == null) return 0
	if (typeof x === 'number') return x
	const n = Number(String(x).replace(/\./g, '').replace(',', '.'))
	return Number.isFinite(n) ? n : 0
}
function fmt(n) {
	return Number(n || 0).toLocaleString('de-DE', { maximumFractionDigits: 0 })
}

async function load() {
	if (!props.userId) {
		rows.value = []
		totals.value = { ist: 0, fc: 0, bud: 0 }
		return
	}
	loading.value = true
	error.value = ''
	rows.value = []
	totals.value = { ist: 0, fc: 0, bud: 0 }
	try {
		const params = { unit: props.unit, user_id: props.userId }
		if (props.period) params.period = props.period
		const { data } = await api.get('/api/dashboard', { params })
		const rws = Array.isArray(data?.table?.rows) ? data.table.rows : []
		rows.value = rws
			.map((r) => ({
				code: String(r.pc_code ?? ''),
				name: String(r.pc_name ?? r.pc_code ?? ''),
				ist: toNum(r.ist),
				fc: toNum(r.prognose),
				bud: toNum(r.budget),
			}))
			.sort((a, b) =>
				String(a.name).localeCompare(String(b.name), 'de', {
					numeric: true,
					sensitivity: 'base',
				}),
			)

		totals.value = {
			ist: toNum(data?.table?.totals?.ist),
			fc: toNum(data?.table?.totals?.prognose),
			bud: toNum(data?.table?.totals?.budget),
		}
	} catch (e) {
		error.value = e?.response?.data?.message || 'Fehler beim Laden.'
	} finally {
		loading.value = false
	}
}

onMounted(load)
watch(() => [props.userId, props.unit, props.period], load)

/** Capas por fila: 100% = Budget; si bud=0 → denom = max(fc, ist, 1) */
function layersFor(r) {
	const bud = Math.max(0, r.bud)
	const fc = Math.max(0, r.fc)
	const ist = Math.max(0, r.ist)
	const denom = bud > 0 ? bud : Math.max(fc, ist, 1)

	const wBud = Math.min(1, bud > 0 ? bud / denom : 1) // base verde (si bud=0, 100%)
	const wFc = Math.min(1, fc / denom) // capa amarilla
	const wIst = Math.min(1, ist / denom) // capa azul

	return {
		budPct: (wBud * 100).toFixed(2) + '%',
		fcPct: (wFc * 100).toFixed(2) + '%',
		istPct: (wIst * 100).toFixed(2) + '%',
	}
}
</script>

<template>
	<div class="pc-bars">
		<div class="head">
			<div class="left">
				<span class="title">Profitcenter</span>
				<span class="muted unit">({{ (unit || '').toUpperCase() }})</span>
				<span v-if="period" class="muted sep">·</span>
				<span v-if="period" class="muted">{{ period }}</span>
			</div>
			<div class="right">
				<Tag :value="`Ist ${fmt(totals.ist)}`" class="pill pill-ist" />
				<Tag :value="`Fc ${fmt(totals.fc)}`" class="pill pill-fc" />
				<Tag :value="`Bud ${fmt(totals.bud)}`" class="pill pill-bud" />
				<Button class="p-button-text p-button-sm" icon="pi pi-refresh" @click="load" />
			</div>
		</div>

		<div v-if="loading" class="empty">Lädt…</div>
		<div v-else-if="error" class="empty err">{{ error }}</div>
		<div v-else-if="!rows.length" class="empty">Keine Profitcenter gefunden.</div>

		<ul v-else class="list">
			<li
				v-for="r in rows"
				:key="r.code"
				class="row"
				:title="`${r.name} · Ist ${fmt(r.ist)} · Fc ${fmt(r.fc)} · Bud ${fmt(r.bud)}`"
			>
				<!-- Top: nombre chico a la izq, valores a la der (IST → FC → BUD) -->
				<div class="top">
					<div class="name">{{ r.name }}</div>
					<div class="vals">
						<span class="val ist" :title="'Ist'">{{ fmt(r.ist) }}</span>
						<span class="val fc" :title="'Forecast'">{{ fmt(r.fc) }}</span>
						<span class="val bud" :title="'Budget'">{{ fmt(r.bud) }}</span>
					</div>
				</div>

				<!-- Barra: base Bud, luego Fc, luego Ist. 100% = Bud (o max(Fc, Ist) si Bud=0) -->
				<div class="bar-wrap">
					<div class="bg"></div>
					<div class="seg bud" :style="{ width: layersFor(r).budPct }"></div>
					<div class="seg fc" :style="{ width: layersFor(r).fcPct }"></div>
					<div class="seg ist" :style="{ width: layersFor(r).istPct }"></div>
				</div>
			</li>
		</ul>
	</div>
</template>

<style scoped>
.pc-bars {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.title {
	font-weight: 700;
}
.unit,
.muted {
	opacity: 0.7;
}
.sep {
	margin: 0 0.4rem;
}
.right {
	display: flex;
	gap: 6px;
	align-items: center;
	flex-wrap: wrap;
}
.pill {
	font-weight: 800;
}
.pill-bud {
	background: #dcfce7;
	color: #064e3b;
}
.pill-fc {
	background: #fef3c7;
	color: #7c2d12;
}
.pill-ist {
	background: #dbeafe;
	color: #1e3a8a;
}

.empty {
	text-align: center;
	opacity: 0.8;
	padding: 8px;
}
.err {
	color: #ef4444;
}

.list {
	display: flex;
	flex-direction: column;
	gap: 10px;
	margin: 0;
	padding: 0;
	list-style: none;
}
.row {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 8px 10px;
	border-radius: 12px;
}
.row:hover {
	background: rgba(0, 0, 0, 0.04);
}
@media (prefers-color-scheme: dark) {
	.row:hover {
		background: rgba(255, 255, 255, 0.06);
	}
}

.top {
	display: flex;
	align-items: center;
	gap: 12px;
	justify-content: space-between;
}
.name {
	font-weight: 600;
	font-size: 0.85rem; /* nombre más chico */
	line-height: 1.2;
	opacity: 0.9;
	flex: 1 1 auto;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.vals {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
	flex: 0 0 auto;
}
.val {
	min-width: 84px;
	text-align: right;
	font-variant-numeric: tabular-nums;
	padding: 2px 8px;
	border-radius: 8px;
	font-weight: 800;
	font-size: 0.84rem;
}
.val.ist {
	background: rgba(59, 130, 246, 0.16);
	color: #1e3a8a;
}
.val.fc {
	background: rgba(245, 158, 11, 0.18);
	color: #7c2d12;
}
.val.bud {
	background: rgba(16, 185, 129, 0.16);
	color: #065f46;
}
@media (prefers-color-scheme: dark) {
	.val.ist {
		background: rgba(59, 130, 246, 0.22);
		color: #93c5fd;
	}
	.val.fc {
		background: rgba(245, 158, 11, 0.22);
		color: #fbbf24;
	}
	.val.bud {
		background: rgba(16, 185, 129, 0.22);
		color: #34d399;
	}
}

/* Barra angosta, ancho completo, con capas en orden Bud → Fc → Ist */
.bar-wrap {
	position: relative;
	height: 10px;
	border-radius: 999px;
	overflow: hidden;
	width: 100%;
}
.bg {
	position: absolute;
	inset: 0;
	background: rgba(0, 0, 0, 0.08);
}
@media (prefers-color-scheme: dark) {
	.bg {
		background: rgba(255, 255, 255, 0.12);
	}
}
.seg {
	position: absolute;
	left: 0;
	top: 0;
	bottom: 0;
	border-radius: 999px;
}
.bud {
	background: #10b981;
	opacity: 0.6;
} /* verde */
.fc {
	background: #f59e0b;
	opacity: 0.9;
} /* amarillo */
.ist {
	background: #3b82f6;
	opacity: 1;
} /* azul */
</style>
