<script setup>
import { computed, ref, onMounted, nextTick, watch, onBeforeUnmount } from 'vue'
import InputText from 'primevue/inputtext'

const props = defineProps({
	months: { type: Array, required: true },
	sales: { type: Array, default: () => [] },
	budget: { type: Array, default: () => [] },
	forecast: { type: Array, default: () => [] },
	ventas: { type: Array, default: null },

	viewportStart: { type: Number, default: 0 }, // índice inicial visible
	viewportSize: { type: Number, default: 12 }, // 12 visibles
	isEditableYm: { type: Function, default: null },
})
const emit = defineEmits(['edit-forecast'])

/* ---- Helpers ---- */
function fmtMonthDE(ym) {
	if (!ym) return '—'
	const [yS, mS] = String(ym).split('-')
	const y = yS?.slice(2) ?? ''
	const m = parseInt(mS || '1', 10)
	const map = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
	return `${map[m - 1] || '—'} ${y}`
}
function yyyymm(d) {
	return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function defaultIsEditable(ym) {
	if (!ym) return false
	const now = new Date()
	const cur = new Date(now.getFullYear(), now.getMonth(), 1)
	const next = new Date(now.getFullYear(), now.getMonth() + 1, 1)
	const [yS, mS] = String(ym).split('-')
	const y = +yS,
		m = +mS
	const target = new Date(y, m - 1, 1)
	if (target <= cur) return false
	if (target.getTime() === next.getTime()) return now.getDate() <= 15
	return true
}
function canEdit(ym) {
	return typeof props.isEditableYm === 'function'
		? !!props.isEditableYm(ym)
		: defaultIsEditable(ym)
}

const curIdx = computed(() => {
	const key = yyyymm(new Date())
	return Array.isArray(props.months) ? props.months.findIndex((m) => m === key) : -1
})

function devPct(num, den) {
	if (!den) return 0
	return (num / den - 1) * 100
}
function clsSalesDev(v, b) {
	const d = Math.abs(devPct(v, b))
	if (d > 10) return 'dev-red'
	if (d > 5) return 'dev-orange'
	if (d > 2) return 'dev-yellow'
	return 'dev-green'
}
function clsFcstDev(v, b) {
	const d = Math.abs(devPct(v, b))
	if (d > 5) return 'dev-red'
	if (d > 2) return 'dev-yellow'
	return 'dev-green'
}

function formatNumber(v) {
	const n = parseFloat(v)
	if (isNaN(n)) return '—'
	return new Intl.NumberFormat('de-DE', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2,
	}).format(n)
}
function pctLabel(num, den) {
	if (!den) return '0%'
	return new Intl.NumberFormat('de-DE', {
		style: 'percent',
		minimumFractionDigits: 0,
		maximumFractionDigits: 0,
	}).format(num / den)
}

const salesData = computed(() => {
	if (Array.isArray(props.sales) && props.sales.length) return props.sales
	if (Array.isArray(props.ventas)) return props.ventas
	return Array(props.months?.length || 12).fill(0)
})

/* ---- Layout: 12 visibles exactos (sin barra) ---- */
const container = ref(null) // ancho total disponible
const scroller = ref(null) // clip + scroll programático
const leftStickyW = ref(150) // px, fijo (coincidir con padre)
const colW = ref(96) // px dinámico

// ancho visible = leftStickyW + 12 * colW
const viewportWidth = computed(() => leftStickyW.value + props.viewportSize * colW.value)

// tabla debe tener el ancho de TODOS los meses: leftStickyW + (N * colW)
const tableMinWidth = computed(() => leftStickyW.value + (props.months?.length || 0) * colW.value)

let ro = null
function recomputeColW() {
	if (!container.value) return
	const full = container.value.clientWidth || 0
	const usable = Math.max(200, full - leftStickyW.value)
	const w = Math.floor(usable / props.viewportSize)
	colW.value = Math.max(70, Math.min(180, w)) // clamp
	nextTick(() => scrollToIndex(props.viewportStart, false))
}
onMounted(() => {
	recomputeColW()
	ro = new ResizeObserver(recomputeColW)
	ro.observe(container.value)
})
onBeforeUnmount(() => {
	try {
		ro?.disconnect()
	} catch {
		//else
	}
})

function scrollToIndex(i, smooth = true) {
	const target = Math.max(0, i) * colW.value
	scroller.value?.scrollTo({ left: target, top: 0, behavior: smooth ? 'smooth' : 'auto' })
}
defineExpose({ scrollToIndex })
watch(
	() => props.viewportStart,
	(v) => nextTick(() => scrollToIndex(v)),
)

/* ---- Input ---- */
function onForecastInput(i, e, ym) {
	if (!canEdit(ym)) return
	const raw = String(e?.target?.value ?? '')
	const n = Number(raw.replace(',', '.'))
	emit('edit-forecast', { index: i, value: isNaN(n) ? 0 : n })
}
</script>

<template>
	<!-- Contenedor ocupa 100% del ancho del card/panel -->
	<div class="table-shell" ref="container">
		<!-- Scroller: sin barra; recorta a 12 columnas visibles -->
		<div class="table-scroll-x" ref="scroller" :style="{ width: viewportWidth + 'px' }">
			<table
				class="tbl"
				:style="{
					minWidth: tableMinWidth + 'px',
					borderCollapse: 'separate',
					borderSpacing: '0',
				}"
			>
				<thead>
					<tr>
						<th
							class="p-2 text-left sticky left-0 z-3 stick-left head-left"
							:style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }"
						></th>
						<th
							v-for="(m, i) in months"
							:key="'m' + i"
							class="p-2 text-center stick-head month-col"
							:style="{ minWidth: colW + 'px', width: colW + 'px' }"
							:class="{
								'cur-left': i === curIdx,
								'cur-right': i === curIdx,
								'cur-top': i === curIdx,
								'head-current': i === curIdx,
							}"
						>
							{{ fmtMonthDE(m) }}
						</th>
					</tr>
				</thead>

				<tbody>
					<!-- Ist -->
					<tr>
						<td
							class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
							:style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }"
						>
							Ist
						</td>
						<td
							v-for="(m, i) in months"
							:key="'v' + i"
							class="p-2 text-center cell cell-sales"
							:style="{ minWidth: colW + 'px', width: colW + 'px' }"
							:class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }"
						>
							{{ formatNumber(salesData[i] ?? 0) }}
						</td>
					</tr>

					<!-- Budget -->
					<tr>
						<td
							class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
							:style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }"
						>
							Budget
						</td>
						<td
							v-for="(m, i) in months"
							:key="'b' + i"
							class="p-2 text-center cell cell-budget"
							:style="{ minWidth: colW + 'px', width: colW + 'px' }"
							:class="{ 'cur-left': i === curIdx, 'cur-right': i === curIdx }"
						>
							{{ formatNumber(budget[i] ?? 0) }}
						</td>
					</tr>

					<!-- Forecast (editable) -->
					<tr>
						<td
							class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
							:style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }"
						>
							Forecast
						</td>
						<td
							v-for="(m, i) in months"
							:key="'f' + i"
							class="p-1 cell"
							:style="{ minWidth: colW + 'px', width: colW + 'px' }"
							:class="{
								'cur-left': i === curIdx,
								'cur-right': i === curIdx,
								'cur-bottom': i === curIdx,
							}"
						>
							<InputText
								class="w-full p-inputtext-sm text-center inp-forecast"
								:value="forecast[i]"
								:disabled="!canEdit(m)"
								@input="(e) => onForecastInput(i, e, m)"
							/>
						</td>
					</tr>

					<!-- % Ist / Budget -->
					<tr>
						<td
							class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
							:style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }"
						>
							% Ist / Bud.
						</td>
						<td
							v-for="(m, i) in months"
							:key="'ivb' + i"
							class="p-2 text-center cell dev-cell"
							:style="{ minWidth: colW + 'px', width: colW + 'px' }"
							:class="[
								clsSalesDev(salesData[i] ?? 0, budget[i] ?? 0),
								{ 'cur-left': i === curIdx, 'cur-right': i === curIdx },
							]"
						>
							{{ pctLabel(salesData[i] ?? 0, budget[i] ?? 0) }}
						</td>
					</tr>

					<!-- % Forecast / Budget -->
					<tr>
						<td
							class="p-2 sticky text-right left-0 z-3 stick-left left-cover"
							:style="{ minWidth: leftStickyW + 'px', width: leftStickyW + 'px' }"
						>
							% For. / Bud.
						</td>
						<td
							v-for="(m, i) in months"
							:key="'ifb' + i"
							class="p-2 text-center cell dev-cell"
							:style="{ minWidth: colW + 'px', width: colW + 'px' }"
							:class="[
								clsFcstDev(forecast[i] ?? 0, budget[i] ?? 0),
								{ 'cur-left': i === curIdx, 'cur-right': i === curIdx },
							]"
						>
							{{ pctLabel(forecast[i] ?? 0, budget[i] ?? 0) }}
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>
</template>

<style scoped>
.table-shell {
	height: 100%;
	width: 100%;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	color: inherit;
}

/* Viewport recorta a 12 columnas; oculta scrollbar */
.table-scroll-x {
	overflow-x: hidden; /* <- sin barra */
	overflow-y: hidden;
	height: 100%;
	/* ocultar barras en navegadores que igual las dibujan */
	-ms-overflow-style: none; /* IE/Edge */
	scrollbar-width: none; /* Firefox */
}
.table-scroll-x::-webkit-scrollbar {
	display: none;
} /* Chrome/Safari */

/* Tabla fija */
.tbl {
	table-layout: fixed;
}

/* Sticky header: cubre (blur + fondo opaco) */
.stick-head {
	position: sticky;
	top: 0;

	background: rgba(255, 255, 255, 0.92);
	backdrop-filter: blur(8px);
	border-bottom: 1px solid rgba(2, 6, 23, 0.12);
}


/* Sticky left: cubre completamente */
.stick-left {
	position: sticky;
	left: 0;
	text-align: right;
	background: #FFF;
}


.left-cover {
	z-index: 3;
}

/* Header: resaltar mes actual */
.head-current {
	background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
}

/* Filas */
.cell {
	border-bottom: 1px solid rgba(0, 0, 0, 0.06);
	text-align: center;
	font-size: 0.85rem;
}
.cell-sales {
	background: rgba(31, 86, 115, 0.25);
}
.cell-budget {
	background: rgba(84, 132, 154, 0.25);
}

/* Input Forecast */
.inp-forecast {
	background: rgba(255, 255, 255, 0.9) !important;
	color: #0f172a !important;
	border: 1px solid rgba(2, 6, 23, 0.2) !important;
	width: 100%;
	border-radius: 6px;
	text-align: center;
}

/* Desvíos */
.dev-cell {
	transition:
		background-color 0.2s ease,
		color 0.2s ease;
	font-variant-numeric: tabular-nums;
}
.dev-red {
	background: rgba(176, 21, 19, 0.16);
	color: #3b0d0d;
}
.dev-orange {
	background: rgba(234, 99, 18, 0.16);
	color: #3b260d;
}
.dev-yellow {
	background: rgba(230, 183, 41, 0.18);
	color: #3a300b;
}
.dev-green {
	background: rgba(5, 164, 111, 0.16);
	color: #093a2c;
}
</style>
