<template>
	<div class="pc-overview-grid">
		<!-- KPI ROW -->
		<section class="kpi-row">
			<Card class="kpi kpi-card" v-for="card in kpiCards" :key="card.key">
				<template #content>
					<div class="kpi-wrap">
						<div>
							<div class="kpi-title">{{ card.title }}</div>
							<div class="kpi-value" v-if="!card.kind">{{ card.value }}</div>
							<div v-else class="spark-top">
								<div class="spark-pct" :class="pctClass(card.pct)">
									{{ pct(card.pct) }}
								</div>
							</div>
						</div>

						<div v-if="['ytd_s', 'ytd_b', 'ytd_f'].includes(card.key)" class="mini-bar-zone">
							<Chart type="bar" :data="miniBarStacked(card.key)" :options="miniBarOptions"
								class="mini-bar" />
						</div>

						<div v-if="card.kind === 'spark' && card.chart" class="spark-zone">
							<Chart type="line" :data="card.chart" :options="sparkOptions" class="sparkline" />
						</div>

						<div class="subline">
							<small class="kpi-sub" v-if="card.sub">{{ card.sub }}</small>
							<div v-if="card.kind === 'spark' && trendsMap[card.key]?.show" class="trend-inline"
								:title="'Tendenz: ' + trendsMap[card.key].deltaLabel">
								<i class="pi pi-arrow-right trend-icon" :class="trendsMap[card.key].colorClass"
									:style="{ transform: `rotate(${trendsMap[card.key].angle}deg)` }" />
								<span class="trend-label">Tendenz</span>
							</div>
						</div>
					</div>
				</template>
			</Card>
		</section>

		<!-- CHARTS ROW -->
		<section class="viz-row">
			<Card class="viz-card viz-line-card">
				<template #title>Monatliche Entwicklung – Unternehmen gesamt</template>
				<template #content>
					<div class="viz-body">
						<Chart type="line" :data="lineData" :options="lineOptions" class="viz-line" />
					</div>
				</template>
			</Card>

			<Card class="viz-card viz-donut-card">
				<template #title>Zusammensetzung (Budget / Extra-Quota)</template>
				<template #content>
					<div class="viz-body donut-layout">
						<div class="pie-area">
							<Chart type="doughnut" :data="multiPieData" :options="multiPieOptions" class="viz-donut" />
						</div>
						<div class="legend mt-2">
							<div class="legend-row">
								<span class="lg-dot" :style="{ background: '#16a34a' }"></span>
								Budget (Außenring)
								<span class="lg-dot ml-3" :style="{ background: '#7c3aed' }"></span>
								Extra-Quota (Außenring)
							</div>
							<div class="legend-row">
								<span class="lg-dot" :style="{ background: '#f59e0b' }"></span>
								Budget prognostiziert
								<span class="lg-dot ml-3" :style="{ background: '#86efac' }"></span>
								Budget offen
							</div>
							<div class="legend-row">
								<span class="lg-dot" :style="{ background: '#a78bfa' }"></span>
								Quota prognostiziert
								<span class="lg-dot ml-3" :style="{ background: '#ddd0ff' }"></span>
								Quota offen
							</div>
							<div class="legend-row">
								<span class="lg-dot" :style="{ background: '#7ba3ea' }"></span>
								Ist (YTD)
								<span class="lg-dot ml-3" :style="{ background: '#f1d97d' }"></span>
								Forecast (Rest)
								<span class="lg-dot ml-3" :style="{ background: '#94a3b8' }"></span>
								Differenz zu Ziel
							</div>
						</div>
					</div>
				</template>
			</Card>
		</section>

		<!-- TABLA -->
		<section class="table-row">
			<Card>
				<template #title>Monatliche Werte (FY – Unternehmen)</template>
				<template #content>
					<PcMonthlyTable :months="months" :sales="sArr" :budget="bArr" :forecast="fArr" />
				</template>
			</Card>
		</section>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import Card from 'primevue/card'
import Chart from 'primevue/chart'
import PcMonthlyTable from './PcMonthlyTable.vue'
import api from '@/plugins/axios'

const props = defineProps({
	fiscalYearStart: { type: Number, required: true },
	asOf: { type: String, default: null }, // 'YYYY-MM'
	unit: { type: String, default: 'm3' }, // 'm3' | 'euro' | 'units'
})

const unitLabel = computed(() =>
	props.unit === 'euro' ? 'EUR' : props.unit === 'units' ? 'VK-EH' : 'm³',
)
const createdAtDE = new Date().toLocaleDateString('de-DE')

const data = ref(null)

async function fetchOverview() {
	const params = {
		node_id: 'company_main',
		fiscal_year: props.fiscalYearStart,
	}
	const { data: resp } = await api.get('/api/analytics/series', { params })

	const months = resp.months || []

	// lastIdx según asOf dentro del FY Apr–März
	let lastIdx = 0
	if (months.length) {
		if (props.asOf) {
			const idx = months.indexOf(props.asOf)
			lastIdx = idx >= 0 ? idx : months.length - 1
		} else {
			lastIdx = months.length - 1
		}
	}

	const raw = {
		monthly: {
			sales: resp.sales || { units: [], m3: [], euro: [] },
			budgets: resp.budgets || { units: [], m3: [], euro: [] },
			forecast: resp.forecasts || { units: [], m3: [], euro: [] },
		},
	}

	const eq = resp.extra_quotas || { units: '0', m3: '0', euro: '0' }
	const extra_quota = {
		allocated: eq,
		used: { units: '0', m3: '0', euro: '0' },
		remaining: eq,
	}

	data.value = {
		months,
		last_complete_index: lastIdx,
		raw,
		extra_quota,
	}
}

onMounted(fetchOverview)
watch(() => [props.fiscalYearStart, props.asOf, props.unit], fetchOverview)

/* Helpers numéricos iguales que en PcOverview */
const toNum = v =>
	typeof v === 'number'
		? v
		: typeof v === 'string'
			? Number(v.replace(/\./g, '')) || 0
			: 0
const fmt = v =>
	Math.round(toNum(v))
		.toString()
		.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

const months = computed(() => data.value?.months || [])
const lastIdx = computed(() => Number(data.value?.last_complete_index ?? 0))
const nextIdx = computed(() => Math.min(11, lastIdx.value + 1))

const labelDE = ym => {
	const m = Number(ym?.slice(5, 7))
	const map = {
		4: 'Apr',
		5: 'Mai',
		6: 'Jun',
		7: 'Jul',
		8: 'Aug',
		9: 'Sep',
		10: 'Okt',
		11: 'Nov',
		12: 'Dez',
		1: 'Jan',
		2: 'Feb',
		3: 'Mär',
	}
	return map[m] || ym
}

const unitKey = computed(() => {
	const v = props.unit
	if (v === 'VK-EH') return 'units'
	return ['m3', 'euro', 'units'].includes(v) ? v : 'm3'
})

const sArr = computed(
	() => data.value?.raw?.monthly?.sales?.[unitKey.value] || [],
)
const bArr = computed(
	() => data.value?.raw?.monthly?.budgets?.[unitKey.value] || [],
)
const fArr = computed(
	() => data.value?.raw?.monthly?.forecast?.[unitKey.value] || [],
)

const sum = a => a.reduce((acc, v) => acc + toNum(v), 0)

/* cumulativa TODO el FY (Abr–März) */
const cumSeriesFull = arr => {
	const out = []
	let acc = 0
	for (let i = 0; i < 12; i++) {
		acc += toNum(arr[i] || 0)
		out.push(acc)
	}
	return out
}

/* YTD, ratios, KPIs, mini-bars, donut, flechas:
   la misma lógica que usamos en PcOverview,
   aplicada sobre sArr/bArr/fArr.
*/

const ytdSales = computed(() => sum(sArr.value.slice(0, lastIdx.value + 1)))
const ytdBudget = computed(() => sum(bArr.value.slice(0, lastIdx.value + 1)))
const ytdForecast = computed(() => sum(fArr.value.slice(0, lastIdx.value + 1)))

const attBudgetPct = computed(() =>
	ytdBudget.value > 0 ? (ytdSales.value / ytdBudget.value) * 100 : 0,
)
const attForecastPct = computed(() =>
	ytdForecast.value > 0 ? (ytdSales.value / ytdForecast.value) * 100 : 0,
)

const ytdLabels = computed(() =>
	months.value
		.slice(0, lastIdx.value + 1)
		.map(ym => labelDE(ym).slice(0, 1)),
)

const ratioSeriesPct = (numArr, denArr) => {
	const L = lastIdx.value
	const numCum = []
	const denCum = []
	let accN = 0
	let accD = 0
	for (let i = 0; i <= L; i++) {
		accN += toNum(numArr[i] || 0)
		accD += toNum(denArr[i] || 0)
		numCum.push(accN)
		denCum.push(accD)
	}
	return numCum.map((n, i) =>
		toNum(denCum[i]) > 0 ? (n / denCum[i]) * 100 : 0,
	)
}

const bandColor = p => {
	const v = Number(p) || 0
	if (v >= 96) return { line: '#16a34a', fill: '#16a34a22' }
	if (v >= 90) return { line: '#eab308', fill: '#eab30822' }
	if (v >= 80) return { line: '#f59e0b', fill: '#f59e0b22' }
	return { line: '#ef4444', fill: '#ef444422' }
}

const seriesBudArr = computed(() => ratioSeriesPct(sArr.value, bArr.value))
const seriesFcArr = computed(() => ratioSeriesPct(sArr.value, fArr.value))

const trendBud = computed(() => {
	const s = seriesBudArr.value
	const { line, fill } = bandColor(s.at(-1) ?? 0)
	return s.length
		? {
			labels: ytdLabels.value,
			datasets: [
				{
					data: s,
					borderColor: line,
					backgroundColor: fill,
					fill: true,
					tension: 0.3,
				},
			],
		}
		: null
})
const trendFc = computed(() => {
	const s = seriesFcArr.value
	const { line, fill } = bandColor(s.at(-1) ?? 0)
	return s.length
		? {
			labels: ytdLabels.value,
			datasets: [
				{
					data: s,
					borderColor: line,
					backgroundColor: fill,
					fill: true,
					tension: 0.3,
				},
			],
		}
		: null
})

const idx6 = computed(() => {
	const a = []
	for (let i = nextIdx.value; i < Math.min(12, nextIdx.value + 6); i++) a.push(i)
	return a
})
const future6Budget = computed(() =>
	idx6.value.reduce((acc, i) => acc + toNum(bArr.value[i] || 0), 0),
)
const future6Forecast = computed(() =>
	idx6.value.reduce((acc, i) => acc + toNum(fArr.value[i] || 0), 0),
)
const future6Pct = computed(() =>
	future6Budget.value > 0
		? (future6Forecast.value / future6Budget.value) * 100
		: 0,
)

const ytdPeriod = computed(() =>
	months.value.length
		? `${labelDE(months.value[0])}–${labelDE(
			months.value[lastIdx.value],
		)}`
		: '',
)

const pct = p => `${(Number(p) || 0).toFixed(1)}%`
const pctClass = p => {
	const n = Number(p) || 0
	return n >= 100
		? 'pct-good'
		: n >= 90
			? 'pct-warn'
			: n >= 80
				? 'pct-mid'
				: 'pct-bad'
}

const kpiCards = computed(() => [
	{
		key: 'ytd_s',
		title: 'YTLFM Ist',
		value: fmt(ytdSales.value),
		sub: `Zeitraum: ${ytdPeriod.value}`,
	},
	{
		key: 'ytd_b',
		title: 'YTLFM Budget',
		value: fmt(ytdBudget.value),
		sub: `inkl. Extra-Quota · ${ytdPeriod.value}`,
	},
	{
		key: 'ytd_f',
		title: 'YTLFM Forecast',
		value: fmt(ytdForecast.value),
		sub: ytdPeriod.value,
	},
	{
		key: 'att_b',
		title: 'Budgeterreichung',
		kind: 'spark',
		pct: attBudgetPct.value,
		chart: trendBud.value,
		sub: 'Evolution (YTLFM)',
	},
	{
		key: 'att_f',
		title: 'Forecasterreichung',
		kind: 'spark',
		pct: attForecastPct.value,
		chart: trendFc.value,
		sub: 'Evolution (YTLFM)',
	},
	{
		key: 'sixm',
		title: 'Prognose',
		kind: 'spark',
		pct: future6Pct.value,
		chart: trendBud.value,
		sub: `Budget: ${fmt(future6Budget.value)} · Forecast: ${fmt(
			future6Forecast.value,
		)}`,
	},
])

/* Mini-bars, sparkOptions, donut, flechas → igual que PcOverview, los dejo por brevedad */

// Mini-bars
function miniBarStacked(key) {
	const labels = months.value
		.map(m => labelDE(m).slice(0, 1))
		.slice(0, 12)
	const L = Math.min(11, lastIdx.value)

	let actual = [],
		budget = []
	if (key === 'ytd_s') {
		actual = sArr.value.map(toNum)
		budget = bArr.value.map(toNum)
	} else if (key === 'ytd_b') {
		actual = bArr.value.map(toNum)
		budget = bArr.value.map(toNum)
	} else if (key === 'ytd_f') {
		actual = fArr.value.map(toNum)
		budget = bArr.value.map(toNum)
	}

	const perfColor = (v, b) => {
		const p = b > 0 ? (v / b) * 100 : 0
		if (p >= 100) return '#16a34a'
		if (p >= 90) return '#eab308'
		return '#ef4444'
	}

	const colored = new Array(12).fill(0)
	const remaining = new Array(12).fill(0)
	const colors = new Array(12).fill('#d1d5db')

	for (let i = 0; i < 12; i++) {
		const b = toNum(budget[i] || 0)
		const v = toNum(actual[i] || 0)

		if (i > L) {
			colored[i] = 0
			remaining[i] = 0
			colors[i] = key === 'ytd_b' ? '#d1d5db' : 'transparent'
			continue
		}

		if (key === 'ytd_b') {
			colored[i] = b
			remaining[i] = 0
			colors[i] = '#d1d5db'
		} else {
			if (v >= b) {
				colored[i] = b
				remaining[i] = 0
			} else {
				colored[i] = v
				remaining[i] = b - v
			}
			colors[i] = perfColor(v, b)
		}
	}

	return {
		labels,
		datasets: [
			{
				label: 'Erreicht',
				data: colored,
				backgroundColor: colors,
				stack: 'stack1',
				barPercentage: 0.8,
				categoryPercentage: 0.9,
			},
			{
				label: 'Rest',
				data: remaining,
				backgroundColor: '#d1d5db',
				stack: 'stack1',
				barPercentage: 0.8,
				categoryPercentage: 0.9,
			},
		],
	}
}

const miniBarOptions = {
	maintainAspectRatio: false,
	scales: {
		x: {
			display: true,
			stacked: true,
			ticks: {
				color: '#64748b',
				font: { size: 10 },
				padding: 2,
			},
			grid: { display: false },
		},
		y: {
			display: false,
			stacked: true,
			beginAtZero: true,
			grid: { display: false },
		},
	},
	plugins: { legend: { display: false }, tooltip: { enabled: false } },
}

const sparkOptions = {
	maintainAspectRatio: false,
	plugins: { legend: { display: false }, tooltip: { enabled: true } },
	elements: { point: { radius: 0 } },
	scales: {
		x: {
			display: true,
			ticks: { font: { size: 10 } },
			grid: { display: false },
		},
		y: { display: false, grid: { display: false } },
	},
}

/* Línea grande – TODO el FY */
const budgetFY = computed(() => sum(bArr.value))
const lineData = computed(() => ({
	labels: months.value,
	datasets: [
		{
			label: 'Ist',
			data: cumSeriesFull(sArr.value),
			borderColor: '#2563eb',
			backgroundColor: '#2563eb33',
			fill: false,
			tension: 0.3,
			spanGaps: false,
		},
		{
			label: 'Budget',
			data: cumSeriesFull(bArr.value),
			borderColor: '#16a34a',
			backgroundColor: '#16a34a33',
			fill: false,
			tension: 0.3,
			spanGaps: false,
		},
		{
			label: 'Forecast',
			data: cumSeriesFull(fArr.value),
			borderColor: '#f59e0b',
			backgroundColor: '#f59e0b33',
			fill: false,
			tension: 0.3,
			spanGaps: false,
		},
		{
			label: 'Budget Ziel',
			data: Array(12).fill(budgetFY.value),
			borderColor: '#64748b',
			borderDash: [6, 6],
			fill: false,
			tension: 0,
			spanGaps: true,
		},
	],
}))

const lineOptions = {
	maintainAspectRatio: false,
	layout: { padding: { top: 12, right: 12, left: 12, bottom: 12 } },
	plugins: {
		legend: { position: 'bottom' },
		tooltip: {
			callbacks: {
				label: ctx => `${ctx.dataset.label}: ${fmt(ctx.parsed.y)}`,
			},
		},
	},
	scales: {
		x: {
			ticks: {
				callback: (v, i) => labelDE(months.value[i] || ''),
			},
		},
		y: { beginAtZero: true, ticks: { callback: v => fmt(v) } },
	},
}

/* Donut simplificado usando budgetFY y extra_quota */
const extraQuotaTotal = computed(() =>
	toNum(data.value?.extra_quota?.allocated?.[unitKey.value] ?? 0),
)
const extraQuotaUsed = computed(() =>
	toNum(data.value?.extra_quota?.used?.[unitKey.value] ?? 0),
)
const extraRemaining = computed(() =>
	Math.max(0, extraQuotaTotal.value - extraQuotaUsed.value),
)

const targetTotal = computed(() => budgetFY.value)
const baseBudget = computed(() =>
	Math.max(0, targetTotal.value - extraQuotaTotal.value),
)
const forecastTotal = computed(() => sum(fArr.value))
const forecastToExtra = computed(() =>
	Math.min(extraQuotaTotal.value, extraQuotaUsed.value),
)
const forecastToBase = computed(() =>
	Math.max(
		0,
		Math.min(baseBudget.value, forecastTotal.value - forecastToExtra.value),
	),
)
const baseRemaining = computed(() =>
	Math.max(0, baseBudget.value - forecastToBase.value),
)

const ytdCapped = computed(() =>
	Math.min(ytdSales.value, targetTotal.value),
)
const remainingAfterYTD = computed(() =>
	Math.max(0, targetTotal.value - ytdCapped.value),
)
const forecastFuture = computed(() => sum(fArr.value.slice(nextIdx.value)))
const forecastFutureShown = computed(() =>
	Math.min(
		remainingAfterYTD.value,
		Math.max(0, forecastFuture.value),
	),
)
const gapToTarget = computed(
	() =>
		Math.max(
			0,
			targetTotal.value - ytdCapped.value - forecastFutureShown.value,
		),
)

const multiPieData = computed(() => ({
	labels: [],
	datasets: [
		{
			label: 'Total Target',
			data: [baseBudget.value, extraQuotaTotal.value],
			backgroundColor: ['#16a34a', '#7c3aed'],
			borderWidth: 2,
		},
		{
			label: 'Quota Usage',
			data: [
				forecastToBase.value,
				baseRemaining.value,
				forecastToExtra.value,
				extraRemaining.value,
			],
			backgroundColor: ['#f59e0b', '#86efac', '#a78bfa', '#ddd0ff'],
			borderWidth: 2,
		},
		{
			label: 'Execution',
			data: [ytdCapped.value, forecastFutureShown.value, gapToTarget.value],
			backgroundColor: ['#7BA3EA', '#F1D97D', '#94a3b8'],
			borderWidth: 2,
		},
	],
}))

const ringLabels = [
	['Budget', 'Extra-Quota'],
	[
		'Budget prognostiziert',
		'Budget offen',
		'Quota prognostiziert',
		'Quota offen',
	],
	['Ist (YTD)', 'Forecast (Rest)', 'Differenz zu Ziel'],
]

const multiPieOptions = {
	maintainAspectRatio: false,
	radius: '98%',
	cutout: '25%',
	layout: { padding: 0 },
	plugins: {
		legend: { display: false },
		tooltip: {
			callbacks: {
				label: ctx => {
					const ds = ctx.datasetIndex ?? 0
					const di = ctx.dataIndex ?? 0
					const seg =
						ringLabels[ds]?.[di] ?? ctx.dataset?.label ?? ''
					const val = ctx.raw != null ? fmt(ctx.raw) : '0'
					return `${seg}: ${val}`
				},
			},
		},
	},
}

/* Flechas de tendencia */
const deltaFromSeries = arr =>
	Array.isArray(arr) && arr.length >= 2
		? (arr.at(-1) ?? 0) - (arr.at(-2) ?? 0)
		: 0
function arrowForDelta(delta) {
	const d = Number(delta) || 0
	if (Math.abs(d) <= 2.5)
		return {
			angle: 0,
			colorClass: 'tr-yellow',
			show: true,
			deltaLabel: `${d.toFixed(1)} pp`,
		}
	if (d > 2.5 && d <= 5)
		return {
			angle: -25,
			colorClass: 'tr-yellow',
			show: true,
			deltaLabel: `${d.toFixed(1)} pp`,
		}
	if (d < -2.5 && d >= -5)
		return {
			angle: 25,
			colorClass: 'tr-yellow',
			show: true,
			deltaLabel: `${d.toFixed(1)} pp`,
		}
	if (d > 5 && d <= 10)
		return {
			angle: -60,
			colorClass: 'tr-green',
			show: true,
			deltaLabel: `${d.toFixed(1)} pp`,
		}
	if (d < -5 && d >= -10)
		return {
			angle: 60,
			colorClass: 'tr-red',
			show: true,
			deltaLabel: `${d.toFixed(1)} pp`,
		}
	if (d > 10)
		return {
			angle: -90,
			colorClass: 'tr-green',
			show: true,
			deltaLabel: `${d.toFixed(1)} pp`,
		}
	if (d < -10)
		return {
			angle: 90,
			colorClass: 'tr-red',
			show: true,
			deltaLabel: `${d.toFixed(1)} pp`,
		}
	return {
		angle: 0,
		colorClass: 'tr-yellow',
		show: false,
		deltaLabel: '0.0 pp',
	}
}
const trendsMap = computed(() => ({
	att_b: arrowForDelta(deltaFromSeries(seriesBudArr.value)),
	att_f: arrowForDelta(deltaFromSeries(seriesFcArr.value)),
	sixm: arrowForDelta(deltaFromSeries(seriesBudArr.value)),
}))
</script>

<style scoped>
/* mismos estilos que tu PcOverview (grid, cards, etc.) */
.pc-overview-grid {
	--gap: 16px;
	--viz-body-h: 640px;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	padding: 10px 12px 12px 12px;
	box-sizing: border-box;
}

.kpi-row {
	grid-column: 1 / -1;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
}

.kpi-row>.kpi {
	grid-column: span 2;
}

.viz-row {
	grid-column: 1 / -1;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
}

.viz-line-card {
	grid-column: span 9;
}

.viz-donut-card {
	grid-column: span 3;
}

.table-row {
	grid-column: 1 / -1;
}

.kpi-card :deep(.p-card-content) {
	padding: 0 !important;
	height: 160px;
}

.kpi-wrap {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	height: 100%;
}

.kpi-title {
	font-size: 0.85rem;
	color: #64748b;
}

.kpi-value {
	font-size: 2.5rem;
	font-weight: 700;
	line-height: 1.2;
}

.kpi-sub {
	color: #94a3b8;
}

.spark-top {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.spark-zone {
	padding-top: 0.25rem;
}

.sparkline {
	height: 62px;
}

.mini-bar-zone {
	margin-top: 0.35rem;
	height: 65px;
}

.mini-bar {
	height: 100% !important;
}

.subline {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 0.5rem;
	margin-top: 0.25rem;
	font-size: 0.85rem;
}

.trend-inline {
	display: flex;
	align-items: center;
	gap: 6px;
}

.trend-inline .trend-icon.pi {
	font-size: 1rem;
	display: inline-block;
	transition: transform 0.2s ease;
	color: var(--trend-color, currentColor) !important;
	opacity: 1 !important;
}

.trend-label {
	font-size: 0.5rem;
	color: #64748b;
}

.tr-yellow {
	--trend-color: #eab308;
}

.tr-green {
	--trend-color: #16a34a;
}

.tr-red {
	--trend-color: #ef4444;
}

.pct-good,
.pct-warn,
.pct-mid,
.pct-bad {
	font-weight: 700;
	font-size: 2.5rem;
}

.pct-good {
	color: #16a34a;
}

.pct-warn {
	color: #eab308;
}

.pct-mid {
	color: #f59e0b;
}

.pct-bad {
	color: #ef4444;
}

.viz-card :deep(.p-card-content) {
	display: flex;
	flex-direction: column;
	height: var(--viz-body-h);
	padding: 0.5rem 0.75rem !important;
}

.viz-body {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	min-height: 0;
}

.viz-line {
	flex: 1 1 auto;
	height: 100% !important;
	width: 100%;
}

.donut-layout {
	display: flex;
	flex-direction: column;
	gap: 0.35rem;
	height: 100%;
}

.pie-area {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	align-items: center;
	justify-content: center;
}

.viz-donut {
	height: 100% !important;
	width: 100% !important;
	max-height: 100%;
}

.legend {
	flex: 0 0 auto;
	color: #475569;
	font-size: 0.85rem;
}

.legend-row {
	display: flex;
	align-items: center;
	gap: 0.35rem;
	margin-bottom: 0.15rem;
}

.lg-dot {
	display: inline-block;
	width: 10px;
	height: 10px;
	border-radius: 50%;
}

@media (max-width: 1199px) {
	.kpi-row>.kpi {
		grid-column: span 6;
	}

	.viz-line-card {
		grid-column: span 12;
	}

	.viz-donut-card {
		grid-column: span 12;
	}
}

@media (max-width: 700px) {
	.kpi-row>.kpi {
		grid-column: span 12;
	}
}

/* Cabecera empresa */
.report-print-header {
	margin: 0 0 8mm 0;
	padding-bottom: 4mm;
	border-bottom: 1px solid #ddd;
}

.report-print-header h2 {
	margin: 0 0 2mm 0;
	font-size: 18pt;
	font-weight: 700;
}

.report-print-header .meta {
	font-size: 11pt;
	color: #333;
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.report-print-header .dot {
	opacity: 0.6;
}

/* ✅ Tabla: ancho completo + celdas centradas */
.table-row :deep(table) {
  width: 100%;
  table-layout: fixed;
}

.table-row :deep(th),
.table-row :deep(td) {
  text-align: center;
  vertical-align: middle;
}

/* Si usás PrimeVue DataTable, también lo cubrimos */
.table-row :deep(.p-datatable-table) {
  width: 100%;
}

.table-row :deep(.p-datatable-thead > tr > th),
.table-row :deep(.p-datatable-tbody > tr > td) {
  text-align: center;
  vertical-align: middle;
}
</style>
