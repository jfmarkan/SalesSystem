<template>
	<div class="chart-area">
		<Radar v-if="chartData" :data="chartData" :options="chartOptions" />
	</div>
</template>

<script setup>
// Code/vars/comments in English
import { computed } from 'vue'
import {
	Chart as ChartJS,
	RadialLinearScale, PointElement, LineElement, Filler,
	Tooltip, Legend, Title
} from 'chart.js'
import { Radar } from 'vue-chartjs'

ChartJS.register(RadialLinearScale, PointElement, LineElement, Filler, Tooltip, Legend, Title)

const props = defineProps({
	labels: { type: Array, required: true },   // only numeric PC ids
	series: { type: Array, required: true },   // [{ name, data }]
	unit: { type: String, default: 'm³' }
})

const palette = [
	{ border: 'rgba(69,98,135, 1)', bg: 'rgba(69,98,135,.20)' },
	{ border: 'rgba(255,194,14,1)', bg: 'rgba(255,194,14,.20)' },
	{ border: 'rgba(116,134,61,1)', bg: 'rgba(116,134,61,.20)' },
]

const chartData = computed(() => ({
	labels: props.labels,
	datasets: props.series.map((s, i) => ({
		label: s.name,
		data: s.data ?? [],
		borderColor: palette[i % palette.length].border,
		backgroundColor: palette[i % palette.length].bg,
		pointBackgroundColor: palette[i % palette.length].border,
		pointBorderColor: '#fff',
		borderWidth: 2,
		fill: true,
		tension: 0.25
	}))
}))

const chartOptions = computed(() => ({
	responsive: true,
	maintainAspectRatio: false,
	plugins: {
		legend: { position: 'bottom', labels: { usePointStyle: true } },
		tooltip: {
			callbacks: {
				label: (ctx) => `${ctx.dataset.label}: ${fmtAbs(ctx.parsed.r ?? 0)} ${props.unit}`
			}
		},
		title: { display: false }
	},
	scales: {
		r: {
			angleLines: { color: 'rgba(0,0,0,.2)' },
			grid: { color: 'rgba(0,0,0,.2)' },
			pointLabels: { color: '#111827', font: { size: 10, weight: '700' } }, // compact labels
			ticks: {
				backdropColor: 'transparent',
				showLabelBackdrop: false,
				color: '#374151',
				callback: (v) => fmtAbs(Number(v))
			},
			suggestedMin: 0
		}
	},
	animation: { duration: 250 }
}))

const nf0 = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 })
function fmtAbs(n) { return nf0.format(Math.round(n ?? 0)) }
</script>

<style scoped>
.chart-area {
	display: flex;
	flex: 1;
	align-items: stretch;
	justify-content: center;
	width: 100%;
	height: 100% !important;
	min-height: 0 !important;
}

.chart-area :deep(canvas) {
	width: 100% !important;
	height: 100% !important;
}
</style>
