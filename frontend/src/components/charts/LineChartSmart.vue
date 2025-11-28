<script setup>
import { ref, watch, onMounted } from 'vue'
import { Line } from 'vue-chartjs'
import {
	Chart, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend
} from 'chart.js'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

Chart.register(LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend)

const props = defineProps({
	type: { type: String, required: true },
	clientId: { type: [Number, String], default: null },
	profitCenterId: { type: [Number, String], default: null },
	apiPrefix: { type: String, default: '/api' },
	autoFetch: { type: Boolean, default: true },
	cumData: { type: Object, default: null },
	versionsData: { type: Object, default: null },
	logo: { type: String, default: '' },
	logoSize: { type: Number, default: 64 },
	busy: { type: Boolean, default: false }
})

const loading = ref(false)

const labels = ref([])
const datasets = ref([])

const palette = [
	'#22C55E', '#3B82F6', '#F59E0B', '#A855F7', '#EF4444', '#06B6D4',
	'#84CC16', '#F472B6', '#10B981', '#64748B', '#8B5CF6', '#F97316'
]

// ✅ Formateo EU: miles con punto, decimales con coma
function formatNumberEU(value) {
  const num = Number(value)
  if (isNaN(num)) return '—'
  return new Intl.NumberFormat('de-DE', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(Math.round(num))
}

function typeNorm() {
	const t = String(props.type || '').toLowerCase()
	if (['cumulative', 'cum', 'cummulative'].includes(t)) return 'cumulative'
	return t
}

function mapCumulative(d) {
	const m = Array.isArray(d.months) ? d.months : []
	const sales = d.sales_cum ?? d.salesCum ?? m.map(() => 0)
	const budget = d.budget_cum ?? d.budgetCum ?? m.map(() => 0)
	const forecast = d.forecast_cum ?? d.forecastCum ?? m.map(() => 0)
	const fyLine = Array.isArray(d.budget_fy_line) && d.budget_fy_line.length === m.length
		? d.budget_fy_line.map(Number)
		: m.map(() => Number(d.budget_fy ?? 0))

	labels.value = m
	const base = [
		{ label: 'Ist (Kum.)    ', data: sales, borderColor: '#6E8DA8', backgroundColor: 'rgba(255,255,255,0)', tension: .25, pointRadius: 0, fill: false },
		{ label: 'Budget (Kum.)    ', data: budget, borderColor: '#7AA488', backgroundColor: 'rgba(255,255,255,0)', tension: .25, pointRadius: 0, fill: false },
		{ label: 'Forecast (Kum.)    ', data: forecast, borderColor: '#B3A45B', backgroundColor: 'rgba(255,255,255,0)', tension: .25, pointRadius: 0, fill: false },
		{ label: 'Ziel Budget', data: fyLine, borderColor: '#557761', borderDash: [6, 6], tension: 0, pointRadius: 0, fill: false }
	]

	if (Array.isArray(d.overlay_best) && d.overlay_best.length === m.length) {
		base.push({
			label: 'Best Case',
			data: d.overlay_best.map(Number),
			borderColor: '#16a34a',
			backgroundColor: 'rgba(255,255,255,0)',
			borderDash: [6, 6],
			tension: .25,
			pointRadius: 0,
			fill: false
		})
	}

	if (Array.isArray(d.overlay_worst) && d.overlay_worst.length === m.length) {
		base.push({
			label: 'Worst Case',
			data: d.overlay_worst.map(Number),
			borderColor: '#EF4444',
			backgroundColor: 'rgba(255,255,255,0)',
			borderDash: [6, 6],
			tension: .25,
			pointRadius: 0,
			fill: false
		})
	}

	datasets.value = base
}

function mapVersions(d, budgetValue = null) {
	if (Array.isArray(d.versions) && Array.isArray(d.volumes)) {
		labels.value = d.versions.map(v => `v${v}`)
		const base = [{
			label: 'Forecast',
			data: d.volumes.map(Number),
			borderColor: '#B3A45B',
			backgroundColor: 'rgba(255,255,255,0)',
			tension: .15,
			pointRadius: 3,
			fill: false
		}]
		if (budgetValue != null && !Number.isNaN(Number(budgetValue))) {
			base.push({
				label: 'Budget (Monat)',
				data: Array(labels.value.length).fill(Number(budgetValue)),
				borderColor: '#557761',
				borderDash: [6, 6],
				tension: 0,
				pointRadius: 0,
				fill: false
			})
		}
		datasets.value = base
		return
	}

	const m = Array.isArray(d.months) ? d.months : (Array.isArray(d.labels) ? d.labels : [])
	const arr = Array.isArray(d.versions) ? d.versions : []
	if (m.length && arr.length && typeof arr[0] === 'object') {
		labels.value = m
		const base = arr.map((v, i) => ({
			label: `v${v.version ?? (i + 1)}`,
			data: Array.isArray(v.values) ? v.values : (Array.isArray(v.data) ? v.data : []),
			borderColor: palette[i % palette.length],
			backgroundColor: palette[i % palette.length] + '33',
			tension: .25,
			pointRadius: 0,
			fill: false
		}))
		if (budgetValue != null && !Number.isNaN(Number(budgetValue))) {
			base.push({
				label: 'Budget (Monat)',
				data: Array(m.length).fill(Number(budgetValue)),
				borderColor: '#64748B',
				borderDash: [6, 6],
				tension: 0,
				pointRadius: 0,
				fill: false
			})
		}
		datasets.value = base
		return
	}

	labels.value = []
	datasets.value = []
}

// Fetchers
async function fetchCumulative() {
	if (!props.clientId || !props.profitCenterId) {
		labels.value = []
		datasets.value = []
		return
	}
	loading.value = true
	try {
		await ensureCsrf()
		const { data } = await api.get(`${props.apiPrefix}/forecast/series`, {
			params: { clientId: props.clientId, profitCenterId: props.profitCenterId }
		})
		mapCumulative(data || {})
	} finally {
		loading.value = false
	}
}

async function fetchVersions() {
	if (!props.clientId || !props.profitCenterId) {
		labels.value = []
		datasets.value = []
		return
	}
	loading.value = true
	try {
		const { data: d } = await api.get(`${props.apiPrefix}/forecast/current-month-versions`, {
			params: { clientId: props.clientId, profitCenterId: props.profitCenterId }
		})

		let budgetValue = null
		try {
			const { data: s } = await api.get(`${props.apiPrefix}/forecast/series`, {
				params: { clientId: props.clientId, profitCenterId: props.profitCenterId }
			})
			const months = Array.isArray(s.months) ? s.months : []
			const mm = d?.month ? String(d.month).padStart(2, '0') : null
			const yy = d?.fiscal_year ? String(d.fiscal_year) : null
			const key = (yy && mm) ? `${yy}-${mm}` : null
			const idx = key ? months.findIndex(x => String(x) === key) : -1
			if (idx >= 0 && Array.isArray(s.budget) && s.budget[idx] != null) {
				budgetValue = Number(s.budget[idx])
			}
		} catch {
			//ignore
		}

		mapVersions(d || {}, budgetValue)
	} finally {
		loading.value = false
	}
}

// Refresh controller
function refresh() {
	const t = typeNorm()
	if (t === 'cumulative') {
		if (props.cumData) mapCumulative(props.cumData)
		else if (props.autoFetch) fetchCumulative()
		else { labels.value = []; datasets.value = [] }
	} else if (t === 'versions') {
		if (props.versionsData) mapVersions(props.versionsData)
		else if (props.autoFetch) fetchVersions()
		else { labels.value = []; datasets.value = [] }
	} else {
		labels.value = []
		datasets.value = []
	}
}

watch(() => [
	props.type, props.clientId, props.profitCenterId,
	props.cumData, props.versionsData, props.autoFetch, props.busy
], refresh, { deep: true })

onMounted(refresh)

const chartRef = ref(null)

// ✅ Chart options con formato EU en tooltip y eje Y
const options = {
	responsive: true,
	maintainAspectRatio: false,
	plugins: {
		legend: { position: 'bottom' },
		tooltip: {
			mode: 'index',
			intersect: false,
			callbacks: {
				label: function (context) {
					const label = context.dataset.label || ''
					const value = context.raw
					return `${label}: ${formatNumberEU(value)}`
				}
			}
		}
	},
	interaction: { mode: 'nearest', intersect: false },
	animation: { duration: 120 },
	scales: {
		x: { ticks: { maxRotation: 0 } },
		y: {
			beginAtZero: true,
			ticks: {
				callback: function (value) {
					return formatNumberEU(value)
				}
			}
		}
	}
}

watch([labels, datasets], () => {
	chartRef.value?.chart?.update('none')
})
</script>

<template>
	<div class="chart-shell">
		<Line ref="chartRef" :data="{ labels, datasets }" :options="options" class="chart" />
	</div>
</template>


<style scoped>
.chart-shell {
	width: 100%;
	height: 100%;
	position: relative;
	display: flex;
	flex: 1;
	flex-direction: column;
}

.chart {
	width: 100% !important;
	height: 100% !important;
}
</style>
