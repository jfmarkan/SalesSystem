<template>
	<div class="kpi-root">
		<div class="kpi-title">
			{{ current.label }}
		</div>

		<div class="kpi-main">
			<div class="kpi-value-block">
				<div class="kpi-value">
					<span v-if="current.unit === '%'">
						{{ formatPercent(numericValue) }}
					</span>
					<span v-else>
						{{ formatNumber(numericValue) }}
					</span>
				</div>
				<div
					v-if="current.unit && current.unit !== '%'"
					class="kpi-unit-chip"
				>
					{{ current.unit }}
				</div>

				<!-- Distancia a objetivo, si viene target -->
				<div
					v-if="hasTarget"
					class="kpi-target-line"
				>
					<span class="kpi-target-label">
						Ziel: {{ targetLabel }}
					</span>
					<span
						class="kpi-target-diff"
						:class="{ neg: distanceFromTarget < 0 }"
					>
						{{ distanceLabel }}
					</span>
				</div>
			</div>

			<div
				class="kpi-icon"
				:style="iconStyle"
			>
				<i
					:class="['pi', iconName]"
					class="kpi-icon-i"
				/>
			</div>
		</div>

		<!-- Mini “gráfico” para KPIs en % -->
		<div
			v-if="isPercent"
			class="kpi-mini"
		>
			<div class="kpi-mini-row">
				<div class="kpi-mini-label">0%</div>
				<div class="kpi-mini-track">
					<div
						class="kpi-mini-fill"
						:class="miniClass"
						:style="{ width: miniWidth + '%' }"
					></div>
					<div class="kpi-mini-marker marker-100"></div>
				</div>
				<div class="kpi-mini-label">150%</div>
			</div>
		</div>

		<div class="kpi-foot">
			<div
				class="kpi-sub"
				v-if="subnote"
			>
				{{ subnote }}
			</div>

			<!-- Selector de KPI en la tarjeta -->
			<div
				class="kpi-selector"
				v-if="editable"
			>
				<select
					:value="modelValue"
					@change="$emit('update:modelValue', $event.target.value)"
				>
					<option
						v-for="(v, key) in kpis"
						:key="key"
						:value="key"
					>
						{{ v.label || key }}
					</option>
				</select>
			</div>
		</div>
	</div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
	modelValue: { type: String, required: true },
	kpis: { type: Object, required: true },
	unit: { type: String, default: 'VK-EH' },
	editable: { type: Boolean, default: false },
	icon: { type: String, default: '' },
	accent: { type: String, default: '' },
	note: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const modelValue = computed({
	get: () => props.modelValue,
	set: (v) => emit('update:modelValue', v),
})

const current = computed(
	() =>
		props.kpis[modelValue.value] ?? {
			label: 'KPI',
			value: 0,
			unit: '',
		},
)

const numericValue = computed(
	() => Number(current.value.value ?? 0) || 0,
)

function formatNumber(n) {
	const num = Number(n) || 0
	const abs = Math.abs(num)
	const sign = num < 0 ? '-' : ''

	if (abs >= 1_000_000) {
		return (
			sign +
			new Intl.NumberFormat('de-DE', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			}).format(abs / 1_000_000) +
			' M'
		)
	}

	if (abs >= 1_000) {
		return (
			sign +
			new Intl.NumberFormat('de-DE', {
				minimumFractionDigits: 1,
				maximumFractionDigits: 1,
			}).format(abs / 1_000) +
			' k'
		)
	}

	return (
		sign +
		new Intl.NumberFormat('de-DE', {
			minimumFractionDigits: 0,
			maximumFractionDigits: 2,
		}).format(abs)
	)
}

function formatPercent(p) {
	const v = Number(p) || 0
	return new Intl.NumberFormat('de-DE', {
		style: 'percent',
		minimumFractionDigits: 1,
		maximumFractionDigits: 1,
	}).format(v / 100)
}

/* Icono + acento */
const defaultIconByKpi = {
	ist_vs_prognose: 'pi-chart-line',
	ist_vs_budget: 'pi-wallet',
	diff_ist_budget_m3: 'pi-database',
	umsatz_eur: 'pi-euro',
}
const defaultAccentByKpi = {
	ist_vs_prognose: 'cyan',
	ist_vs_budget: 'orange',
	diff_ist_budget_m3: 'slate',
	umsatz_eur: 'violet',
}
const gradientByAccent = {
	cyan: 'linear-gradient(to bottom, #22d3ee, #0891b2)',
	orange: 'linear-gradient(to bottom, #fb923c, #ea580c)',
	slate: 'linear-gradient(to bottom, #94a3b8, #475569)',
	violet: 'linear-gradient(to bottom, #a78bfa, #7c3aed)',
	emerald: 'linear-gradient(to bottom, #34d399, #059669)',
	rose: 'linear-gradient(to bottom, #fb7185, #e11d48)',
}

const kpiId = computed(() => modelValue.value)
const iconName = computed(
	() => props.icon || defaultIconByKpi[kpiId.value] || 'pi-chart-bar',
)
const accent = computed(
	() => props.accent || defaultAccentByKpi[kpiId.value] || 'cyan',
)
const iconStyle = computed(() => ({
	background: gradientByAccent[accent.value] || gradientByAccent.cyan,
}))

const subnote = computed(() => {
	if (props.note) return props.note
	if (current.value.unit && current.value.unit !== '%') {
		return `Einheit: ${current.value.unit}`
	}
	return ''
})

/* Target / objetivo (si el back lo manda) */
const hasTarget = computed(
	() => current.value.target != null && !Number.isNaN(Number(current.value.target)),
)
const targetValue = computed(
	() => (hasTarget.value ? Number(current.value.target) || 0 : 0),
)
const distanceFromTarget = computed(
	() => numericValue.value - targetValue.value,
)

const distanceLabel = computed(() => {
	if (!hasTarget.value) return ''
	const diff = distanceFromTarget.value
	const pref = diff >= 0 ? '+' : ''
	if (current.value.unit === '%') {
		return pref + formatPercent(diff)
	}
	return pref + formatNumber(diff)
})
const targetLabel = computed(() => {
	if (!hasTarget.value) return ''
	if (current.value.unit === '%') return formatPercent(targetValue.value)
	return formatNumber(targetValue.value)
})

/* Mini “chart” para KPIs en % */
const isPercent = computed(
	() => String(current.value.unit || '').trim() === '%',
)

function clamp(v, min, max) {
	return Math.min(max, Math.max(min, v))
}

const miniWidth = computed(() => {
	if (!isPercent.value) return 0
	const raw = numericValue.value
	// rango visual 0–150%
	const clamped = clamp(raw, 0, 150)
	return (clamped / 150) * 100
})

const miniClass = computed(() => {
	const v = numericValue.value
	if (v >= 120) return 'good'
	if (v >= 100) return 'mid'
	if (v > 0) return 'low'
	if (v === 0) return 'zero'
	return 'neg'
})
</script>

<style scoped>
.kpi-root {
	display: flex;
	flex-direction: column;
	height: 100%;
	gap: 0.5rem;
}

/* titulo */
.kpi-title {
	font-size: 0.85rem;
	line-height: 1.2;
	font-weight: 500;
	color: #475569;
}

/* valor + icono */
.kpi-main {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 0.75rem;
}

.kpi-value-block {
	display: flex;
	flex-direction: column;
	gap: 0.15rem;
}

.kpi-value {
	font-size: 1.9rem;
	line-height: 1;
	font-weight: 800;
	color: #0f172a;
}

.kpi-unit-chip {
	align-self: flex-start;
	padding: 2px 8px;
	border-radius: 999px;
	font-size: 0.7rem;
	font-weight: 600;
	background: rgba(15, 23, 42, 0.04);
	color: #64748b;
}

/* target */
.kpi-target-line {
	display: flex;
	align-items: baseline;
	gap: 0.4rem;
	font-size: 0.75rem;
	color: #9ca3af;
}

.kpi-target-diff {
	font-weight: 600;
	color: #16a34a;
}
.kpi-target-diff.neg {
	color: #ef4444;
}

/* icono */
.kpi-icon {
	width: 2.9rem;
	height: 2.9rem;
	border-radius: 0.9rem;
	color: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 8px 18px rgba(15, 23, 42, 0.35);
}

.kpi-icon-i {
	font-size: 1.3rem;
	line-height: 1;
}

/* mini chart */
.kpi-mini {
	margin-top: 0.1rem;
}

.kpi-mini-row {
	display: grid;
	grid-template-columns: auto 1fr auto;
	align-items: center;
	gap: 0.35rem;
}

.kpi-mini-label {
	font-size: 0.7rem;
	color: #9ca3af;
}

.kpi-mini-track {
	position: relative;
	height: 6px;
	border-radius: 999px;
	background: rgba(15, 23, 42, 0.06);
	overflow: hidden;
}

.kpi-mini-fill {
	height: 100%;
	border-radius: inherit;
	background: linear-gradient(to right, #f97316, #22c55e);
	transition: width 0.25s ease;
}

/* distintos estados de color */
.kpi-mini-fill.low {
	background: linear-gradient(to right, #f97316, #facc15);
}
.kpi-mini-fill.mid {
	background: linear-gradient(to right, #facc15, #22c55e);
}
.kpi-mini-fill.good {
	background: linear-gradient(to right, #22c55e, #16a34a);
}
.kpi-mini-fill.zero {
	background: rgba(148, 163, 184, 0.6);
}
.kpi-mini-fill.neg {
	background: linear-gradient(to right, #ef4444, #f97316);
}

/* marcador 100% */
.kpi-mini-marker {
	position: absolute;
	top: -2px;
	bottom: -2px;
	width: 2px;
	background: rgba(15, 23, 42, 0.5);
}
.marker-100 {
	left: calc(100% * (100 / 150));
}

/* pie */
.kpi-foot {
	margin-top: 0.15rem;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 0.5rem;
}

.kpi-sub {
	color: #94a3b8;
	font-weight: 500;
	font-size: 0.75rem;
}

/* selector de KPI */
.kpi-selector {
	margin-left: auto;
}

.kpi-selector select {
	border-radius: 999px;
	border: 1px solid rgba(148, 163, 184, 0.6);
	font-size: 0.7rem;
	padding: 2px 8px;
	color: #4b5563;
	background: #fff;
}
</style>
