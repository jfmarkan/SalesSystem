<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import api from '@/plugins/axios'

import Card from 'primevue/card'
import Button from 'primevue/button'
import Chart from 'primevue/chart'
import Tag from 'primevue/tag'
import InputNumber from 'primevue/inputnumber'

const loading = ref(false)
const overview = ref(null)
const ov = computed(() => overview.value)

/* ===== AUTO REFRESH ===== */
// Refresca el overview entero (incluye Budget nach Verkäufer)
const REFRESH_INTERVAL_MS = 15000
let refreshTimer = null

/* ========== FORMATOS ========== */
const nfInt = new Intl.NumberFormat('de-DE', {
	maximumFractionDigits: 0,
	minimumFractionDigits: 0,
})
const nfPct = new Intl.NumberFormat('de-DE', {
	style: 'percent',
	maximumFractionDigits: 1,
	minimumFractionDigits: 1,
})

function fmtInt(v) {
	const n = Number(v || 0)
	if (!Number.isFinite(n)) return '0'
	return nfInt.format(n)
}
function fmtPct(v) {
	const n = Number(v || 0) / 100
	if (!Number.isFinite(n)) return '0 %'
	return nfPct.format(n)
}
function fmtPctDelta(val, base) {
	const v = Number(val || 0)
	const b = Number(base || 0)
	if (!b) return '0 %'
	const d = (v / b - 1) * 100
	const sign = d > 0 ? '+' : ''
	return `${sign}${nfPct.format(d / 100)}`
}
function monthName(m) {
	const map = {
		1: 'Jan',
		2: 'Feb',
		3: 'Mär',
		4: 'Apr',
		5: 'Mai',
		6: 'Jun',
		7: 'Jul',
		8: 'Aug',
		9: 'Sep',
		10: 'Okt',
		11: 'Nov',
		12: 'Dez',
	}
	return map[m] || '—'
}

function classificationSeverity(letter) {
	const v = String(letter || '').toUpperCase()
	if (v === 'A') return 'success'
	if (v === 'B') return 'info'
	if (v === 'PA' || v === 'PB') return 'warning'
	if (v === 'C' || v === 'D') return 'secondary'
	return 'secondary'
}

/* ===== DELTAS ===== */
function relDelta(curr, prev) {
	const c = Number(curr || 0)
	const p = Number(prev || 0)
	if (!p) return 0
	return (c / p - 1) * 100
}
function fmtDeltaAbs(curr, prev) {
	const c = Number(curr || 0)
	const p = Number(prev || 0)
	if (!p && !c) return '0'
	const d = c - p
	const sign = d > 0 ? '+' : ''
	return sign + fmtInt(Math.round(d))
}
function fmtDeltaPct(curr, prev) {
	const d = relDelta(curr, prev)
	if (!prev) return '—'
	const sign = d > 0 ? '+' : ''
	return `${sign}${nfPct.format(d / 100)}`
}

/**
 * Colores:
 *  - sin prev      → neutro
 *  - d >= 0        → verde
 *  - 0 > d >= -10  → amarillo
 *  - d < -10       → rojo
 */
function deltaClass(curr, prev) {
	const p = Number(prev || 0)
	if (!p) return 'chip-neutral'
	const d = relDelta(curr, prev)
	if (d >= 0) return 'chip-positive'
	if (d > -10) return 'chip-warning'
	return 'chip-negative'
}

/* ========== LOAD OVERVIEW ========== */
async function loadOverview() {
	loading.value = true
	try {
		const { data } = await api.get('/api/budget-cases/overview')
		overview.value = data || null
	} catch (e) {
		console.error('Error loading budget overview', e)
		overview.value = null
	} finally {
		loading.value = false
	}
}

onMounted(() => {
	loadOverview()
	refreshTimer = setInterval(() => {
		loadOverview()
	}, REFRESH_INTERVAL_MS)
})

onUnmounted(() => {
	if (refreshTimer) {
		clearInterval(refreshTimer)
		refreshTimer = null
	}
})

/* ========== DONUTS ========== */
function baseDonutOptions() {
	return {
		maintainAspectRatio: false,
		cutout: '60%',
		animation: false, // sin animación para refrescos frecuentes
		plugins: {
			legend: { display: false },
			tooltip: {
				callbacks: {
					label(ctx) {
						const label = ctx.label || ''
						const val = ctx.parsed || 0
						return `${label}: ${val.toFixed(1)} %`
					},
				},
			},
			centerText: null,
		},
	}
}
function makeDonutObject(title, covPct, withCase, totalCpc) {
	const coverage = Math.max(0, Math.min(100, covPct || 0))
	if (!totalCpc) {
		return {
			data: { labels: [], datasets: [{ data: [], backgroundColor: [] }] },
			options: baseDonutOptions(),
		}
	}
	const data = [coverage, Math.max(0, 100 - coverage)]
	const labels = ['Coverage', 'Rest']
	const options = baseDonutOptions()
	options.plugins.centerText = {
		title,
		main: fmtPct(coverage),
		sub: `${withCase}/${totalCpc} CPC`,
	}
	return {
		data: {
			labels,
			datasets: [
				{
					data,
					backgroundColor: ['#22c55e', '#e5e7eb'],
				},
			],
		},
		options,
	}
}
const donutA = computed(() => {
	const cls = ov.value?.classes?.A
	if (!cls)
		return { data: { labels: [], datasets: [{ data: [], backgroundColor: [] }] }, options: baseDonutOptions() }
	return makeDonutObject(
		'A-Kunden',
		Number(cls.coverage_pct || 0),
		Number(cls.with_case || 0),
		Number(cls.total_cpcs || 0),
	)
})
const donutB = computed(() => {
	const cls = ov.value?.classes?.B
	if (!cls)
		return { data: { labels: [], datasets: [{ data: [], backgroundColor: [] }] }, options: baseDonutOptions() }
	return makeDonutObject(
		'B-Kunden',
		Number(cls.coverage_pct || 0),
		Number(cls.with_case || 0),
		Number(cls.total_cpcs || 0),
	)
})
const donutPot = computed(() => {
	const pa = ov.value?.classes?.PA
	const pb = ov.value?.classes?.PB
	if (!pa && !pb)
		return { data: { labels: [], datasets: [{ data: [], backgroundColor: [] }] }, options: baseDonutOptions() }
	const withCase = Number(pa?.with_case || 0) + Number(pb?.with_case || 0)
	const total = Number(pa?.total_cpcs || 0) + Number(pb?.total_cpcs || 0)
	const covPct = total ? (withCase / total) * 100 : 0
	return makeDonutObject('PA/PB-Kunden', covPct, withCase, total)
})
/* plugin texto centro */
const centerTextPlugin = {
	id: 'centerText',
	beforeDraw(chart) {
		const opts = chart.config.options.plugins?.centerText
		if (!opts) return
		const { ctx, chartArea } = chart
		if (!chartArea) return
		const x = (chartArea.left + chartArea.right) / 2
		const y = (chartArea.top + chartArea.bottom) / 2
		ctx.save()
		ctx.textAlign = 'center'
		ctx.textBaseline = 'middle'
		if (opts.title) {
			ctx.fillStyle = '#6b7280'
			ctx.font = '600 9px system-ui, -apple-system, BlinkMacSystemFont, sans-serif'
			ctx.fillText(opts.title, x, y - 18)
		}
		if (opts.main) {
			ctx.fillStyle = '#111827'
			ctx.font = '600 14px system-ui, -apple-system, BlinkMacSystemFont, sans-serif'
			ctx.fillText(opts.main, x, y)
		}
		if (opts.sub) {
			ctx.fillStyle = '#6b7280'
			ctx.font = '400 10px system-ui, -apple-system, BlinkMacSystemFont, sans-serif'
			ctx.fillText(opts.sub, x, y + 16)
		}
		ctx.restore()
	},
}

/* ========== INPUTS C/D PARA ESCENARIO GLOBAL ========== */

const cBestPct = ref(0)
const cWorstPct = ref(0)
const dBestPct = ref(0)
const dWorstPct = ref(0)

// A, B, C, D, PA, PB, X
const classKeysAll = ['A', 'B', 'C', 'D', 'PA', 'PB', 'X']

const derivedTotals = computed(() => {
	const out = {
		base_total: 0,
		best_total: 0,
		worst_total: 0,
		base_by_class: {},
		best_by_class: {},
		worst_by_class: {},
	}
	const classes = ov.value?.classes || {}
	for (const key of classKeysAll) {
		const row = classes[key] || {}
		const base = Number(row.base_m3_all ?? 0)
		if (!Number.isFinite(base) || base === 0) continue

		out.base_total += base
		out.base_by_class[key] = base

		let best = 0
		let worst = 0

		if (key === 'C') {
			const pctBest = Number(cBestPct.value || 0)
			const pctWorst = Number(cWorstPct.value || 0)
			best = base * (1 + pctBest / 100)
			worst = base * (1 + pctWorst / 100)
		} else if (key === 'D') {
			const pctBest = Number(dBestPct.value || 0)
			const pctWorst = Number(dWorstPct.value || 0)
			best = base * (1 + pctBest / 100)
			worst = base * (1 + pctWorst / 100)
		} else if (key === 'X') {
			best = base
			worst = base
		} else {
			const rowBest = Number(row.best_m3 ?? 0)
			const rowWorst = Number(row.worst_m3 ?? 0)
			best = Number.isFinite(rowBest) && rowBest !== 0 ? rowBest : base
			worst = Number.isFinite(rowWorst) && rowWorst !== 0 ? rowWorst : base
		}

		out.best_total += best
		out.worst_total += worst
		out.best_by_class[key] = best
		out.worst_by_class[key] = worst
	}
	return out
})

const baseTotalForUI = computed(() => {
	const d = derivedTotals.value
	if (d.base_total > 0) return d.base_total
	const g = ov.value?.global
	return Number(g?.base_m3_all ?? 0)
})
const bestTotalForUI = computed(() => {
	const d = derivedTotals.value
	if (d.base_total > 0) return d.best_total
	const g = ov.value?.global
	return Number(g?.best_m3 ?? 0)
})
const worstTotalForUI = computed(() => {
	const d = derivedTotals.value
	if (d.base_total > 0) return d.worst_total
	const g = ov.value?.global
	return Number(g?.worst_m3 ?? 0)
})

const cdMeta = computed(() => {
	const d = derivedTotals.value
	const baseC = d.base_by_class.C || 0
	const baseD = d.base_by_class.D || 0
	const bestC = d.best_by_class.C || 0
	const bestD = d.best_by_class.D || 0
	const worstC = d.worst_by_class.C || 0
	const worstD = d.worst_by_class.D || 0
	const base_cd = baseC + baseD
	const best_cd = bestC + bestD
	const worst_cd = worstC + worstD
	return { base_cd, best_cd, worst_cd, baseC, baseD, bestC, bestD, worstC, worstD }
})

/* ========== HELPERS COMUNES ========== */

function pcDisplayName(pc) {
	return (
		pc.profit_center_name ??
		pc.pc_name ??
		pc.name ??
		pc.profit_center_code ??
		pc.code ??
		'Profit Center'
	)
}

/* ===== coverage helpers ===== */

function clampPct(v) {
	const n = Number(v || 0)
	if (!Number.isFinite(n) || n < 0) return 0
	if (n > 100) return 100
	return n
}

function sellerCoveragePct(seller) {
	if (seller && seller.coverage_pct != null) return clampPct(seller.coverage_pct)
	const total = Number(seller?.total_cpcs ?? 0)
	const withCase = Number(seller?.with_case ?? 0)
	return total > 0 ? clampPct((withCase / total) * 100) : 0
}

function pcCoveragePct(pc) {
	if (pc && pc.coverage_pct != null) return clampPct(pc.coverage_pct)
	const total = Number(pc?.total_cpcs ?? pc?.total_cases ?? 0)
	const withCase = Number(pc?.with_case ?? pc?.saved_cases ?? 0)
	return total > 0 ? clampPct((withCase / total) * 100) : 0
}

function coverageColorClass(pct) {
	const p = Number(pct || 0)
	if (p >= 99.5) return 'cov-green'
	if (p >= 90) return 'cov-yellow'
	if (p >= 70) return 'cov-orange'
	return 'cov-red'
}

/* ========== BUDGET NACH VERKÄUFER (STATE) ========== */

const openSellers = ref({})
const openSellerPcs = ref({})

const sellerKey = (s) => s.user_id ?? s.id ?? s.full_name ?? String(Math.random())

function toggleSeller(key) {
	openSellers.value[key] = !openSellers.value[key]
}
function isSellerOpen(key) {
	return !!openSellers.value[key]
}

function pcKeySeller(sellerKeyVal, pc) {
	const code = pc.profit_center_code ?? pc.code ?? 'pc'
	return `${sellerKeyVal}__${code}`
}
function toggleSellerPc(key) {
	openSellerPcs.value[key] = !openSellerPcs.value[key]
}
function isSellerPcOpen(key) {
	return !!openSellerPcs.value[key]
}

/* ========== RESUMEN GLOBAL POR PROFIT CENTER (by_pc) ========== */

const pcsRaw = computed(() => {
	if (Array.isArray(ov.value?.by_pc) && ov.value.by_pc.length) {
		return ov.value.by_pc
	}
	const map = new Map()
	for (const seller of ov.value?.by_seller || []) {
		for (const pc of seller.pcs || []) {
			const code = pc.profit_center_code ?? pc.code
			if (code == null) continue
			const key = String(code)
			if (!map.has(key)) {
				map.set(key, {
					profit_center_code: code,
					name: pcDisplayName(pc),
					prev_m3: 0,
					best_m3: 0,
					worst_m3: 0,
					ytd_annualized_m3: 0,
					pending_cases: [],
					class_mix: {},
				})
			}
			const entry = map.get(key)
			entry.prev_m3 += Number(pc.prev_m3 || 0)
			entry.best_m3 += Number(pc.best_m3 || 0)
			entry.worst_m3 += Number(pc.worst_m3 || 0)
			entry.ytd_annualized_m3 += Number(pc.ytd_annualized_m3 || 0)
		}
	}
	for (const row of ov.value?.pending_cases || []) {
		const code = row.profit_center_code
		if (code == null) continue
		const key = String(code)
		if (!map.has(key)) {
			map.set(key, {
				profit_center_code: code,
				name: `Profit Center ${code}`,
				prev_m3: 0,
				best_m3: 0,
				worst_m3: 0,
				ytd_annualized_m3: 0,
				pending_cases: [],
				class_mix: {},
			})
		}
		map.get(key).pending_cases.push(row)
	}
	return Array.from(map.values()).sort(
		(a, b) => Number(a.profit_center_code) - Number(b.profit_center_code),
	)
})

/* PC scenarios (C/D assumptions aplicadas) */
const pcScenarioList = computed(() => {
	const list = pcsRaw.value || []
	const result = []

	for (const pc of list) {
		const mix = pc.class_mix || {}
		const items = []

		for (const key of classKeysAll) {
			const row = mix[key] || {}
			const base = Number(row.base_m3 ?? 0)
			if (!base) continue

			let best = 0
			let worst = 0

			if (key === 'C') {
				const pctBest = Number(cBestPct.value || 0)
				const pctWorst = Number(cWorstPct.value || 0)
				best = base * (1 + pctBest / 100)
				worst = base * (1 + pctWorst / 100)
			} else if (key === 'D') {
				const pctBest = Number(dBestPct.value || 0)
				const pctWorst = Number(dWorstPct.value || 0)
				best = base * (1 + pctBest / 100)
				worst = base * (1 + pctWorst / 100)
			} else if (key === 'X') {
				best = base
				worst = base
			} else {
				best = base
				worst = base
			}

			items.push({ key, base, best, worst })
		}

		const baseTotal = items.reduce((s, it) => s + it.base, 0)
		const bestTotal = items.reduce((s, it) => s + it.best, 0)
		const worstTotal = items.reduce((s, it) => s + it.worst, 0)

		const mixWithShare = items.map((it) => ({
			...it,
			share_pct: baseTotal > 0 ? (it.base / baseTotal) * 100 : 0,
		}))

		result.push({
			...pc,
			name: pc.name ?? pcDisplayName(pc),
			scenario_base: baseTotal,
			scenario_best: bestTotal,
			scenario_worst: worstTotal,
			mix: mixWithShare,
		})
	}

	return result
})

/* Estado de sub-acordeones por PC */
const openPcSummary = ref({})
const openPcCases = ref({})
const openPcMix = ref({})

function togglePcSummary(code) {
	const key = String(code)
	openPcSummary.value[key] = !openPcSummary.value[key]
}
function isPcSummaryOpen(code) {
	return !!openPcSummary.value[String(code)]
}

function togglePcCases(code) {
	const key = String(code)
	openPcCases.value[key] = !openPcCases.value[key]
}
function isPcCasesOpen(code) {
	return !!openPcCases.value[String(code)]
}

function togglePcMix(code) {
	const key = String(code)
	openPcMix.value[key] = !openPcMix.value[key]
}
function isPcMixOpen(code) {
	return !!openPcMix.value[String(code)]
}
</script>

<template>
	<div class="budget-overview">
		<!-- ==================== TOP ROW ==================== -->
		<div class="top-grid">
			<!-- === Gesamtbudget === -->
			<Card class="card gesamt-card">
				<template #title>
					<div class="card-title-row">
						<div>
							<span>Gesamtbudget</span>
							<span v-if="ov" class="fy-pill">
								WJ {{ ov.target_fiscal_year }}/{{
									String(ov.target_fiscal_year + 1).slice(-2)
								}}
							</span>
						</div>
						<i v-if="loading" class="pi pi-spin pi-spinner text-500" />
					</div>
				</template>

				<template #content>
					<div v-if="!ov && !loading" class="empty-state">
						<p>Keine Daten verfügbar.</p>
						<Button label="Neu laden" icon="pi pi-refresh" text @click="loadOverview" />
					</div>

					<div v-else class="gesamt-body">
						<!-- Basis / Best / Worst -->
						<div class="gesamt-main">
							<div class="metric-block">
								<div class="label">Basisvolumen (CY11+1)</div>
								<div class="value big">
									{{ fmtInt(baseTotalForUI) }}
									<span class="unit">m³</span>
								</div>
								<div class="sub">
									Alle Kundenklassen (A, B, C, D, PA, PB, X)
								</div>
							</div>

							<div class="metric-block best">
								<div class="label">Best Case</div>
								<div class="value big green">
									{{ fmtInt(bestTotalForUI) }}
									<span class="unit">m³</span>
								</div>
								<div class="sub">
									Δ
									{{ fmtPctDelta(bestTotalForUI, baseTotalForUI) }}
									ggü. Basis
								</div>
							</div>

							<div class="metric-block worst">
								<div class="label">Worst Case</div>
								<div class="value big red">
									{{ fmtInt(worstTotalForUI) }}
									<span class="unit">m³</span>
								</div>
								<div class="sub">
									Δ
									{{ fmtPctDelta(worstTotalForUI, baseTotalForUI) }}
									ggü. Basis
								</div>
							</div>
						</div>

						<!-- Meta -->
						<div class="gesamt-meta">
							<div>
								<span class="meta-label">Basis:</span>
								<span class="meta-val">
									Kalenderjahr {{ ov?.base_calendar_year ?? '—' }}
									bis Monat
									{{ monthName(ov?.cap_month || 0) }}
								</span>
							</div>
							<div>
								<span class="meta-label">Coverage (alle CPC):</span>
								<span class="meta-val">
									{{ fmtPct(ov?.global.coverage_pct ?? 0) }}
								</span>
							</div>
							<div>
								<span class="meta-label">CPC mit Case:</span>
								<span class="meta-val">
									{{ ov?.global.with_case ?? 0 }} /
									{{ ov?.global.total_cpcs ?? 0 }}
								</span>
							</div>
						</div>

						<div
							v-if="cdMeta.base_cd > 0"
							class="gesamt-meta cd-meta"
						>
							<div>
								<span class="meta-label">C/D-Basis:</span>
								<span class="meta-val">
									{{ fmtInt(cdMeta.base_cd) }} m³
								</span>
							</div>
							<div>
								<span class="meta-label">C/D Best:</span>
								<span class="meta-val">
									{{ fmtInt(cdMeta.best_cd) }} m³
								</span>
							</div>
							<div>
								<span class="meta-label">C/D Worst:</span>
								<span class="meta-val">
									{{ fmtInt(cdMeta.worst_cd) }} m³
								</span>
							</div>
						</div>

						<div class="gesamt-meta">
							Best/Worst Case inkl. Annahmen für C- und D-Kunden.
						</div>
					</div>
				</template>
			</Card>

			<!-- === Zusätzliche Annahmen C / D === -->
			<Card class="card cd-card">
				<template #title>
					<div class="card-title-row">
						<span>Zusätzliche Annahmen</span>
					</div>
				</template>
				<template #content>
					<div class="cd-grid">
						<!-- C-Kunden -->
						<div class="cd-group">
							<div class="cd-title">C-Kunden</div>
							<div class="cd-inputs">
								<div class="cd-field">
									<label>Best</label>
									<InputNumber
										v-model="cBestPct"
										suffix=" %"
										:step="1"
										input-class="cd-input"
									/>
								</div>
								<div class="cd-field">
									<label>Worst</label>
									<InputNumber
										v-model="cWorstPct"
										suffix=" %"
										:step="1"
										input-class="cd-input"
									/>
								</div>
							</div>
						</div>

						<!-- D-Kunden -->
						<div class="cd-group">
							<div class="cd-title">D-Kunden</div>
							<div class="cd-inputs">
								<div class="cd-field">
									<label>Best</label>
									<InputNumber
										v-model="dBestPct"
										suffix=" %"
										:step="1"
										input-class="cd-input"
									/>
								</div>
								<div class="cd-field">
									<label>Worst</label>
									<InputNumber
										v-model="dWorstPct"
										suffix=" %"
										:step="1"
										input-class="cd-input"
									/>
								</div>
							</div>
						</div>
					</div>
					<p class="cd-hint">
						Die Prozentwerte werden auf das Basisvolumen der C- und D-Kunden angewendet
						und fließen in das Gesamt-Best/Worst-Szenario oben sowie in die Profit-Center-Analyse ein.
					</p>
				</template>
			</Card>

			<!-- === Donuts A / B / PA+PB === -->
			<Card class="card donuts-card">
				<template #title>
					<div class="card-title-row">
						<span>Coverage & Volumen je Kundentyp</span>
					</div>
				</template>

				<template #content>
					<div v-if="!ov && !loading" class="empty-state">
						<p>Keine Daten.</p>
					</div>

					<div v-else class="donuts-grid">
						<!-- A-Kunden -->
						<div class="donut-block">
							<div class="donut-title">A-Kunden</div>
							<Chart
								v-if="donutA.data.datasets[0].data.length"
								type="doughnut"
								:data="donutA.data"
								:options="donutA.options"
								:plugins="[centerTextPlugin]"
								class="donut-chart"
							/>
							<div v-else class="donut-empty">Keine Daten</div>
						</div>

						<!-- B-Kunden -->
						<div class="donut-block">
							<div class="donut-title">B-Kunden</div>
							<Chart
								v-if="donutB.data.datasets[0].data.length"
								type="doughnut"
								:data="donutB.data"
								:options="donutB.options"
								:plugins="[centerTextPlugin]"
								class="donut-chart"
							/>
							<div v-else class="donut-empty">Keine Daten</div>
						</div>

						<!-- PA/PB-Kunden -->
						<div class="donut-block">
							<div class="donut-title">PA/PB-Kunden</div>
							<Chart
								v-if="donutPot.data.datasets[0].data.length"
								type="doughnut"
								:data="donutPot.data"
								:options="donutPot.options"
								:plugins="[centerTextPlugin]"
								class="donut-chart"
							/>
							<div v-else class="donut-empty">Keine Daten</div>
						</div>
					</div>
				</template>
			</Card>
		</div>

		<!-- ==================== BOTTOM ROW ==================== -->
		<div class="bottom-grid">
			<!-- ====== BUDGET NACH VERKÄUFER ====== -->
			<Card class="card seller-card">
				<template #title>
					<div class="card-title-row">
						<span>Budget nach Verkäufer</span>
					</div>
				</template>
				<template #content>
					<div v-if="!ov && !loading" class="empty-state">
						<p>Keine Daten verfügbar.</p>
					</div>

					<div v-else class="seller-list">
						<div
							v-for="seller in ov?.by_seller || []"
							:key="sellerKey(seller)"
							class="seller-block"
						>
							<!-- fila VENDEDOR -->
							<button
								type="button"
								class="seller-row"
								@click="toggleSeller(sellerKey(seller))"
							>
								<!-- col 1: flecha + nombre -->
								<div class="seller-cell seller-cell-name">
									<i
										class="pi"
										:class="isSellerOpen(sellerKey(seller)) ? 'pi-chevron-down' : 'pi-chevron-right'"
									/>
									<span class="seller-name">
										{{ seller.full_name ?? seller.name ?? 'Unbekannt' }}
									</span>
								</div>

								<!-- col 2: barra coverage CPC -->
								<div class="seller-cell seller-cell-cov">
									<div class="seller-progress">
										<div class="cov-header-row">
											<span class="cov-label">CPC</span>
											<span class="cov-pct">
												{{ fmtPct(sellerCoveragePct(seller)) }}
											</span>
										</div>
										<div class="cov-bar">
											<div
												class="cov-fill"
												:class="coverageColorClass(sellerCoveragePct(seller))"
												:style="{ width: sellerCoveragePct(seller) + '%' }"
											/>
										</div>
										<div class="cov-ratio">
											{{ seller.with_case ?? 0 }}/{{ seller.total_cpcs ?? 0 }}
											CPC
										</div>
									</div>
								</div>

								<!-- col 3: Bud 2025/26 -->
								<div class="seller-cell seller-cell-prev numeric-cell">
									<div class="cell-label">Bud 2025/26</div>
									<div class="cell-value">
										{{ fmtInt(seller.prev_m3 ?? 0) }} m³
									</div>
								</div>

								<!-- col 4: CY11+1 -->
								<div class="seller-cell seller-cell-cy numeric-cell">
									<div class="cell-label">CY11+1</div>
									<div class="cell-value">
										{{ fmtInt(seller.ytd_annualized_m3 ?? 0) }} m³
									</div>
								</div>

								<!-- col 5: Best -->
								<div class="seller-cell seller-cell-best numeric-cell">
									<div class="cell-label">Best</div>
									<div class="cell-value txt-green">
										{{ fmtInt(seller.best_m3 ?? 0) }} m³
									</div>
								</div>

								<!-- col 6: Δ (m³ arriba, % abajo) -->
								<div class="seller-cell seller-cell-delta numeric-cell">
									<div class="cell-label">Δ</div>
									<div
										class="delta-box"
										:class="deltaClass(seller.best_m3, seller.prev_m3)"
									>
										<div class="delta-main">
											{{ fmtDeltaAbs(seller.best_m3, seller.prev_m3) }} m³
										</div>
										<div class="delta-sub">
											{{ fmtDeltaPct(seller.best_m3, seller.prev_m3) }}
										</div>
									</div>
								</div>
							</button>

							<!-- PCs del vendedor -->
							<transition name="fade">
								<div
									v-if="isSellerOpen(sellerKey(seller))"
									class="seller-pc-list"
								>
									<div
										v-for="pc in seller.pcs || []"
										:key="pcKeySeller(sellerKey(seller), pc)"
										class="pc-block-seller"
									>
										<div class="pc-row-seller">
											<!-- col 1: flecha + nombre PC -->
											<div
												class="pc-cell pc-cell-name"
												@click.stop="toggleSellerPc(pcKeySeller(sellerKey(seller), pc))"
											>
												<i
													class="pi"
													:class="
														isSellerPcOpen(pcKeySeller(sellerKey(seller), pc))
															? 'pi-chevron-down'
															: 'pi-chevron-right'
													"
												/>
												<div class="pc-main-seller">
													<span class="pc-name">
														{{ pcDisplayName(pc) }}
													</span>
												</div>
											</div>

											<!-- col 2: coverage PC (barra más ancha) -->
											<div class="pc-cell pc-cell-cov">
												<div class="pc-progress">
													<div class="cov-header-row cov-small">
														<span class="cov-label">CPC</span>
														<span class="cov-pct">
															{{ fmtPct(pcCoveragePct(pc)) }}
														</span>
													</div>
													<div class="cov-bar cov-small">
														<div
															class="cov-fill"
															:class="coverageColorClass(pcCoveragePct(pc))"
															:style="{ width: pcCoveragePct(pc) + '%' }"
														/>
													</div>
													<div class="cov-ratio cov-small">
														{{ pc.with_case ?? pc.saved_cases ?? 0 }}/{{
															pc.total_cpcs ?? pc.total_cases ?? 0
														}}
													</div>
												</div>
											</div>

											<!-- col 3: Bud 2025/26 -->
											<div class="pc-cell pc-cell-prev numeric-cell">
												<div class="cell-label">Bud 2025/26</div>
												<div class="cell-value">
													{{ pc.prev_m3 != null ? fmtInt(pc.prev_m3) : '—' }} m³
												</div>
											</div>

											<!-- col 4: CY11+1 -->
											<div class="pc-cell pc-cell-cy numeric-cell">
												<div class="cell-label">CY11+1</div>
												<div class="cell-value">
													{{ fmtInt(pc.ytd_annualized_m3 ?? 0) }} m³
												</div>
											</div>

											<!-- col 5: Best -->
											<div class="pc-cell pc-cell-best numeric-cell">
												<div class="cell-label">Best</div>
												<div class="cell-value txt-green">
													{{ pc.best_m3 != null ? fmtInt(pc.best_m3) : '—' }} m³
												</div>
											</div>

											<!-- col 6: Δ (m³ y %) -->
											<div class="pc-cell pc-cell-delta numeric-cell">
												<div class="cell-label">Δ</div>
												<div
													class="delta-box"
													:class="deltaClass(pc.best_m3, pc.prev_m3)"
												>
													<div class="delta-main">
														{{ fmtDeltaAbs(pc.best_m3, pc.prev_m3) }} m³
													</div>
													<div class="delta-sub">
														{{ fmtDeltaPct(pc.best_m3, pc.prev_m3) }}
													</div>
												</div>
											</div>
										</div>

										<!-- clientes del PC -->
										<transition name="fade">
											<div
												v-if="isSellerPcOpen(pcKeySeller(sellerKey(seller), pc))"
												class="pc-client-list"
											>
												<div
													v-if="!pc.clients || !pc.clients.length"
													class="no-clients"
												>
													Keine Kundendetails für diesen Profit Center.
												</div>

												<div
													v-else
													v-for="client in pc.clients"
													:key="client.client_group_number"
													:class="['client-row', { 'client-skipped': client.skip_budget }]"
												>
													<!-- col 1: icono + nombre + skip -->
													<div class="client-cell client-cell-name">
														<i
															class="pi"
															:class="
																client.has_case
																	? 'pi-check-circle icon-ok'
																	: 'pi-times-circle icon-bad'
															"
														/>
														<div class="client-main">
															<span class="cgn">
																#{{ client.client_group_number }}
															</span>
															<div class="client-name-line">
																<span class="cname">
																	{{ client.client_name }}
																</span>
															</div>
														</div>
													</div>

													<!-- col 2: Best/Worst % -->
													<div class="client-cell client-cell-bw numeric-cell">
														<div class="cell-label">Best / Worst</div>
														<div class="bw-box">
															<div class="bw-line bw-best">
																Best {{ fmtPct(client.best_pct ?? 0) }}
															</div>
															<div class="bw-line bw-worst">
																Worst {{ fmtPct(client.worst_pct ?? 0) }}
															</div>
														</div>
													</div>

													<!-- col 3: Bud 2025/26 -->
													<div class="client-cell client-cell-prev numeric-cell">
														<div class="cell-label">Bud 2025/26</div>
														<div class="cell-value">
															{{ client.prev_m3 != null ? fmtInt(client.prev_m3) : '—' }}
															m³
														</div>
													</div>

													<!-- col 4: CY11+1 -->
													<div class="client-cell client-cell-cy numeric-cell">
														<div class="cell-label">CY11+1</div>
														<div class="cell-value">
															{{ fmtInt(client.ytd_annualized_m3 ?? 0) }} m³
														</div>
													</div>

													<!-- col 5: Best m³ -->
													<div class="client-cell client-cell-best numeric-cell">
														<div class="cell-label">Best m³</div>
														<div class="cell-value txt-green">
															{{ client.best_m3 != null ? fmtInt(client.best_m3) : '—' }}
															m³
														</div>
													</div>

													<!-- col 6: Δ (m³ y %) -->
													<div class="client-cell client-cell-delta numeric-cell">
														<div class="cell-label">Δ</div>
														<div
															class="delta-box"
															:class="deltaClass(client.best_m3, client.prev_m3)"
														>
															<div class="delta-main">
																{{ fmtDeltaAbs(client.best_m3, client.prev_m3) }} m³
															</div>
															<div class="delta-sub">
																{{ fmtDeltaPct(client.best_m3, client.prev_m3) }}
															</div>
														</div>
													</div>
												</div>
											</div>
										</transition>
									</div>
								</div>
							</transition>
						</div>
					</div>
				</template>
			</Card>

			<!-- ====== RESUMEN POR PROFIT CENTER (GLOBAL) ====== -->
			<Card class="card pc-card">
				<template #title>
					<div class="card-title-row">
						<span>Budget nach Profit Center</span>
						<span v-if="pcScenarioList.length" class="pc-count-pill">
							{{ pcScenarioList.length }} PC
						</span>
					</div>
				</template>

				<template #content>
					<div v-if="!ov && !loading" class="empty-state">
						<p>Keine Daten verfügbar.</p>
					</div>

					<div v-else class="pc-list">
						<div
							v-for="pc in pcScenarioList"
							:key="pc.profit_center_code"
							class="pc-block"
						>
							<!-- fila PC resumen -->
							<button
								type="button"
								class="pc-row"
								@click="togglePcSummary(pc.profit_center_code)"
							>
								<div class="pc-main">
									<i
										class="pi"
										:class="isPcSummaryOpen(pc.profit_center_code) ? 'pi-chevron-down' : 'pi-chevron-right'"
									/>
									<div class="pc-title">
										<span class="pc-name">{{ pc.name }}</span>
									</div>
								</div>

								<div class="pc-metrics">
									<div class="pc-metric">
										<div class="metric-label">Bud 2025/26</div>
										<div class="metric-value">
											{{ fmtInt(pc.prev_m3) }}
											<span class="unit">m³</span>
										</div>
									</div>
									<div class="pc-metric">
										<div class="metric-label">CY11+1</div>
										<div class="metric-value">
											{{ fmtInt(pc.ytd_annualized_m3 ?? 0) }}
											<span class="unit">m³</span>
										</div>
									</div>
									<div class="pc-metric">
										<div class="metric-label">Best</div>
										<div class="metric-value green">
											{{ fmtInt(pc.scenario_best) }}
											<span class="unit">m³</span>
										</div>
									</div>
									<div class="pc-metric">
										<div class="metric-label">Δ</div>
										<div
											class="metric-value"
										>
											<div
												class="delta-box"
												:class="deltaClass(pc.scenario_best, pc.prev_m3)"
											>
												<div class="delta-main">
													{{ fmtDeltaAbs(pc.scenario_best, pc.prev_m3) }} m³
												</div>
												<div class="delta-sub">
													{{ fmtDeltaPct(pc.scenario_best, pc.prev_m3) }}
												</div>
											</div>
										</div>
									</div>
								</div>
							</button>

							<!-- detalle PC -->
							<transition name="fade">
								<div
									v-if="isPcSummaryOpen(pc.profit_center_code)"
									class="pc-detail"
								>
									<!-- Sub-accordion: casos abiertos -->
									<div class="pc-subsection">
										<button
											type="button"
											class="pc-subtitle"
											@click.stop="togglePcCases(pc.profit_center_code)"
										>
											<i
												class="pi"
												:class="isPcCasesOpen(pc.profit_center_code) ? 'pi-chevron-down' : 'pi-chevron-right'"
											/>
											<span>
												Offene Budget Cases ({{ pc.pending_cases?.length ?? 0 }})
											</span>
										</button>
										<transition name="fade">
											<div
												v-if="isPcCasesOpen(pc.profit_center_code)"
												class="pc-subcontent"
											>
												<div
													v-if="!pc.pending_cases || !pc.pending_cases.length"
													class="no-pending"
												>
													Keine offenen Budget Cases für diesen Profit Center.
												</div>
												<div
													v-else
													class="pending-list"
												>
													<div
														v-for="row in pc.pending_cases"
														:key="row.client_group_number + '-' + row.profit_center_code"
														class="pending-row"
													>
														<div class="pending-main">
															<span class="pending-cgn">
																#{{ row.client_group_number }}
															</span>
															<span class="pending-name">
																{{ row.client_name }}
															</span>
														</div>
														<div class="pending-meta">
															<Tag
																:value="row.classification"
																:severity="classificationSeverity(row.classification)"
																class="tag-slim"
															/>
															<span
																v-if="row.seller_name"
																class="pending-seller"
															>
																{{ row.seller_name }}
															</span>
														</div>
													</div>
												</div>
											</div>
										</transition>
									</div>

									<!-- Sub-accordion: mix por Kundentyp -->
									<div class="pc-subsection">
										<button
											type="button"
											class="pc-subtitle"
											@click.stop="togglePcMix(pc.profit_center_code)"
										>
											<i
												class="pi"
												:class="isPcMixOpen(pc.profit_center_code) ? 'pi-chevron-down' : 'pi-chevron-right'"
											/>
											<span>Verteilung nach Kundentyp</span>
										</button>
										<transition name="fade">
											<div
												v-if="isPcMixOpen(pc.profit_center_code)"
												class="pc-subcontent pc-mix-content"
											>
												<div
													v-if="!pc.mix || !pc.mix.length"
													class="no-pending"
												>
													Keine Volumendaten nach Kundentyp.
												</div>
												<div v-else class="pc-mix-table">
													<div class="pc-mix-header">
														<span>Typ</span>
														<span>Basis m³</span>
														<span>Anteil</span>
													</div>
													<div
														v-for="row in pc.mix"
														:key="row.key"
														class="pc-mix-row"
													>
														<span class="pc-mix-type">
															{{ row.key }}
														</span>
														<span class="pc-mix-base">
															{{ fmtInt(row.base) }}
														</span>
														<span class="pc-mix-share">
															{{ fmtPct(row.share_pct) }}
														</span>
													</div>
												</div>
											</div>
										</transition>
									</div>
								</div>
							</transition>
						</div>
					</div>
				</template>
			</Card>
		</div>
	</div>
</template>

<style scoped>
.budget-overview {
	display: flex;
	flex-direction: column;
	gap: 16px;
}

/* ==== GRID LAYOUT ==== */
.top-grid {
	display: grid;
	grid-template-columns: 6fr 1fr 5fr; /* Gesamt | C/D | Donuts */
	gap: 16px;
	align-items: stretch;
}

.bottom-grid {
	display: grid;
	grid-template-columns: 6fr 6fr;
	gap: 16px;
	align-items: stretch;
}

/* ==== CARDS ==== */
.card :deep(.p-card-body) {
	padding: 0.75rem 0.9rem !important;
}

.card :deep(.p-card-content) {
	padding: 0 !important;
}

.card-title-row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	font-weight: 700;
	font-size: 0.95rem;
}

.fy-pill {
	margin-left: 8px;
	padding: 2px 8px;
	border-radius: 999px;
	background: #e5e7eb;
	font-size: 0.75rem;
	font-weight: 600;
}

/* ==== Gesamtbudget ==== */
.gesamt-body {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.gesamt-main {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	align-items: stretch;
}

.metric-block {
	background: #f9fafb;
	border-radius: 12px;
	padding: 12px 14px;
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-height: 96px;
}

.metric-block.best {
	border-left: 3px solid #16a34a;
}

.metric-block.worst {
	border-left: 3px solid #dc2626;
}

.metric-block .label {
	font-size: 0.82rem;
	color: #6b7280;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}

.metric-block .value {
	font-size: 1.1rem;
	font-weight: 800;
	color: #111827;
	text-align: left;
}

.metric-block .value.big {
	font-size: 1.35rem;
}

.metric-block .unit {
	font-size: 0.8rem;
	margin-left: 4px;
	color: #6b7280;
}

.metric-block .sub {
	font-size: 0.8rem;
	color: #6b7280;
}

.value.green {
	color: #16a34a;
}

.value.red {
	color: #dc2626;
}

.gesamt-meta {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	font-size: 0.82rem;
	color: #4b5563;
	padding-top: 6px;
	border-top: 1px dashed #e5e7eb;
}

.cd-meta {
	border-top-style: solid;
	border-top-width: 1px;
	border-top-color: #c7d2fe;
}

.meta-label {
	font-weight: 600;
	margin-right: 4px;
}

.meta-val {
	font-weight: 400;
}

/* ==== Donuts ==== */
.donuts-card {
	min-height: 260px;
}

.donuts-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
}

.donut-block {
	display: flex;
	flex-direction: column;
	gap: 6px;
	align-items: stretch;
}

.donut-title {
	font-size: 0.78rem;
	font-weight: 600;
	color: #6b7280;
	text-align: center;
}

.donut-chart {
	width: 100%;
	height: 160px;
}

.donut-empty {
	flex: 1;
	display: grid;
	place-items: center;
	font-size: 0.8rem;
	color: #9ca3af;
}

/* ==== C/D card ==== */
.cd-card {
	align-self: stretch;
}

.cd-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 8px;
}

.cd-group {
	background: #f9fafb;
	border-radius: 10px;
	padding: 8px 10px;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.cd-title {
	font-size: 0.82rem;
	font-weight: 600;
	color: #374151;
}

.cd-inputs {
	display: flex;
	flex-direction: row;
	gap: 6px;
}

.cd-field {
	flex: 1 1 0;
	display: flex;
	flex-direction: column;
	gap: 3px;
	min-width: 0;
}

.cd-field label {
	font-size: 0.75rem;
	color: #6b7280;
}

.cd-input {
	width: 100%;
	max-width: 72px;
	height: 28px;
	font-size: 0.8rem;
}

/* ajustar el input de PrimeVue */
.cd-field :deep(.p-inputtext.cd-input) {
	padding: 2px 4px;
	height: 28px;
	font-size: 0.8rem;
}

.cd-hint {
	margin-top: 8px;
	font-size: 0.78rem;
	color: #6b7280;
}

/* ==== Seller "tabla" custom ==== */

.seller-card {
	height: 100%;
	display: flex;
	flex-direction: column;
}

.seller-card :deep(.p-card-body) {
	display: flex;
	flex-direction: column;
	height: 100%;
}

.seller-card :deep(.p-card-content) {
	flex: 1;
	display: flex;
	flex-direction: column;
}

.seller-list {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 6px;
	overflow-y: auto;
	padding-right: 4px;
}

/* fila vendedor - columnas alineadas */
.seller-block {
	padding: 2px 0;
}

.seller-row {
	width: 100%;
	border: 1px solid #e5e7eb;
	background: linear-gradient(90deg, #f9fafb 0%, #f3f4f6 60%, #eef2ff 100%);
	border-radius: 10px;
	padding: 7px 10px 7px 4px;
	display: grid;
	grid-template-columns:
		minmax(0, 2.2fr) /* nombre */
		minmax(0, 2fr)   /* barra */
		repeat(4, minmax(0, 1.2fr)); /* Bud, CY, Best, Δ */
	column-gap: 12px;
	align-items: center;
	cursor: pointer;
	transition: box-shadow 0.12s ease-out, transform 0.12s ease-out, background 0.12s ease-out;
	text-align: left;
}

.seller-row:hover {
	box-shadow: 0 2px 6px rgba(15, 23, 42, 0.07);
	transform: translateY(-1px);
	background: linear-gradient(90deg, #f9fafb 0%, #e5e7eb 60%, #e0e7ff 100%);
}

.seller-cell {
	min-width: 0;
	display: flex;
	flex-direction: column;
	justify-content: start;
	align-items: flex-start;
	text-align: left;
}

/* col 1: flecha + nombre */
.seller-cell-name {
	flex-direction: row;
	align-items: center;
	gap: 6px;
}

.seller-cell-name .pi {
	font-size: 0.85rem;
	color: #4b5563;
}

.seller-name {
	font-weight: 700;
	color: #111827;
	font-size: 0.9rem;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	text-align: left;
}

/* col 2: coverage seller */
.seller-cell-cov {
	align-items: flex-start;
}

.seller-progress {
	width: 100%;
	display: flex;
	flex-direction: column;
	gap: 3px;
	margin-right: 4px;
}

/* etiquetas genéricas de celda */
.cell-label {
	font-size: 0.72rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.03em;
	color: #6b7280;
	text-align: left;
}

.cell-value {
	font-size: 0.8rem;
	font-weight: 600;
	color: #111827;
	text-align: left;
}

/* numeric cells centradas */
.numeric-cell {
	align-items: center;
	text-align: center;
}
.numeric-cell .cell-value,
.numeric-cell .cell-label {
	text-align: center;
}

/* barras coverage */
.cov-header-row {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	font-size: 0.72rem;
	color: #6b7280;
}

.cov-header-row.cov-small {
	font-size: 0.7rem;
}

.cov-label {
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.cov-pct {
	font-weight: 700;
	color: #111827;
}

.cov-bar {
	position: relative;
	width: 100%;
	height: 6px;
	border-radius: 999px;
	background: #e5e7eb;
	overflow: hidden;
}

.cov-bar.cov-small {
	height: 5px;
}

.cov-fill {
	height: 100%;
	border-radius: inherit;
	transition: width 0.15s ease-out;
}

/* colores según % */
.cov-green {
	background: #22c55e;
}

.cov-yellow {
	background: #eab308;
}

.cov-orange {
	background: #f97316;
}

.cov-red {
	background: #ef4444;
}

.cov-ratio {
	font-size: 0.7rem;
	color: #9ca3af;
	text-align: left;
}

.cov-ratio.cov-small {
	font-size: 0.68rem;
}

/* PCs del vendedor */
.seller-pc-list {
	margin-top: 5px;
	padding-left: 10px;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.pc-block-seller {
	background: #f9fafb;
	border-radius: 8px;
	padding: 4px 6px 4px;
	border: 1px solid #e5e7eb;
}

/* fila PC dentro de vendedor: misma estructura de columnas */
.pc-row-seller {
	display: grid;
	grid-template-columns:
		minmax(0, 2.2fr) /* nombre */
		minmax(0, 2fr)   /* barra */
		repeat(4, minmax(0, 1.2fr)); /* Bud, CY, Best, Δ */
	column-gap: 12px;
	align-items: center;
}

.pc-cell {
	min-width: 0;
	display: flex;
	flex-direction: column;
	justify-content: start;
	align-items: flex-start;
	text-align: left;
}


.pc-cell-name {
	flex-direction: row;
	align-items: center;
	gap: 6px;
	cursor: pointer;
}

.pc-cell-name .pi {
	font-size: 0.8rem;
	color: #6b7280;
}


.pc-main-seller {
	display: flex;
	flex-direction: column;
	gap: 1px;
	text-align: left;
}

.pc-name {
	font-weight: 600;
	color: #111827;
	font-size: 0.8rem;
	text-align: left;
}

.pc-progress {
	width: 90%;
}

/* clientes del PC (seller card) */
.pc-client-list {
	margin-top: 4px;
	border-top: 1px dashed #e5e7eb;
	padding-top: 4px;
	display: flex;
	flex-direction: column;
	gap: 3px;
}

/* ==== FILA CLIENTE ==== */
.client-row {
	display: grid;
	grid-template-columns:
		minmax(0, 2.4fr) /* nombre */
		minmax(0, 2fr)   /* Best/Worst % */
		repeat(4, minmax(0, 1.2fr)); /* Bud, CY, Best m³, Δ */
	column-gap: 12px;
	align-items: stretch;
	padding: 4px 6px;
	border-radius: 6px;
	border: 1px solid transparent;
	background: #f9fafb;
}

.client-row:nth-child(odd) {
	background: #f3f4f6;
}

/* cuando está marcado como skipped */
.client-row.client-skipped {
	background: #EBCDD0;
	border-color: #D49399;
	opacity: 0.9;
}

.client-row .client-cell {
	min-width: 0;
	display: flex;
	flex-direction: column;
	justify-content: start;
	align-items: center;
	text-align: center;
}

.client-cell-name {
	flex-direction: row !important;
	align-items: center;
	justify-content: flex-start;
	gap: 6px;
	text-align: left;
}

.client-cell-name .pi {
	font-size: 0.9rem;
}

.client-main {
	display: flex;
	flex-direction: column;
	gap: 2px;
	text-align: left;
}

.client-main .cgn {
	font-size: 0.74rem;
	color: #6b7280;
}

.client-main .cname {
	font-size: 0.8rem;
	font-weight: 600;
	color: #111827;
}

.client-name-line {
	display: flex;
	align-items: center;
	gap: 6px;
}

.skip-tag.p-tag {
	padding: 1px 6px;
	font-size: 0.7rem;
	border-radius: 999px;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

/* Best/Worst box */
.bw-box {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 2px;
	font-size: 0.75rem;
}

.bw-line {
	white-space: nowrap;
}

.bw-best {
	color: #166534;
	font-weight: 600;
}

.bw-worst {
	color: #b91c1c;
	font-weight: 600;
}

.icon-ok {
	color: #16a34a;
	font-size: 0.9rem;
}

.icon-bad {
	color: #dc2626;
	font-size: 0.9rem;
}

.no-clients {
	font-size: 0.78rem;
	color: #9ca3af;
}

/* ==== PC SUMMARY CARD (GLOBAL) ==== */

.pc-card :deep(.p-card-body) {
	display: flex;
	flex-direction: column;
	height: 100%;
}
.pc-card :deep(.p-card-content) {
	flex: 1;
	display: flex;
	flex-direction: column;
}

.pc-count-pill {
	margin-left: auto;
	padding: 2px 8px;
	border-radius: 999px;
	background: #e0f2fe;
	color: #0369a1;
	font-size: 0.75rem;
	font-weight: 600;
}

.pc-list {
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding-top: 4px;
}

.pc-block {
	border-radius: 10px;
	border: 1px solid #e5e7eb;
	background: #f9fafb;
	overflow: hidden;
}

/* fila principal del PC */
.pc-row {
	width: 100%;
	display: grid;
	grid-template-columns: minmax(0, 3.5fr) minmax(0, 4.5fr);
	column-gap: 16px;
	padding: 10px 10px;
	background: linear-gradient(90deg, #f9fafb 0%, #f3f4f6 60%, #eef2ff 100%);
	cursor: pointer;
	border: none;
	outline: none;
	text-align: left;
	transition: box-shadow 0.12s ease-out, transform 0.12s ease-out,
		background 0.12s ease-out;
}

.pc-row:hover {
	box-shadow: 0 2px 6px rgba(15, 23, 42, 0.07);
	transform: translateY(-1px);
	background: linear-gradient(90deg, #f9fafb 0%, #e5e7eb 60%, #e0e7ff 100%);
}

.pc-main {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
}

.pc-main .pi {
	font-size: 0.85rem;
	color: #4b5563;
}

.pc-title {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.pc-name {
	font-weight: 700;
	color: #111827;
	font-size: 0.9rem;
}

/* métricas lado derecho */
.pc-metrics {
	display: flex;
	justify-content: flex-end;
	gap: 14px;
	align-items: center;
	flex-wrap: wrap;
}

.pc-metric {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 2px;
	min-width: 90px;
	text-align: center;
}

.metric-label {
	font-size: 0.72rem;
	font-weight: 600;
	color: #6b7280;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}

.metric-value {
	font-size: 0.82rem;
	font-weight: 600;
	color: #111827;
}

.metric-value.green {
	color: #16a34a;
}

.metric-delta-pct {
	margin-left: 4px;
	font-weight: 500;
	font-size: 0.78rem;
	color: #4b5563;
}

/* detalle PC / casos abiertos + mix */
.pc-detail {
	border-top: 1px solid #e5e7eb;
	background: #fefefe;
	padding: 6px 10px 8px;
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.pc-subsection {
	border-radius: 8px;
	background: #f9fafb;
	border: 1px dashed #e5e7eb;
}

.pc-subtitle {
	width: 100%;
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 4px 8px;
	border: none;
	outline: none;
	background: transparent;
	font-size: 0.8rem;
	font-weight: 600;
	color: #374151;
	text-align: left;
	cursor: pointer;
}

.pc-subtitle .pi {
	font-size: 0.8rem;
	color: #4b5563;
}

.pc-subcontent {
	padding: 4px 8px 6px;
}

/* casos pendientes */
.no-pending {
	font-size: 0.78rem;
	color: #9ca3af;
}

.pending-list {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.pending-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 8px;
	padding: 3px 4px;
	border-radius: 6px;
}

.pending-row:nth-child(odd) {
	background: #f3f4f6;
}

.pending-main {
	display: flex;
	flex-direction: column;
	gap: 1px;
	text-align: left;
}

.pending-cgn {
	font-size: 0.74rem;
	color: #6b7280;
}

.pending-name {
	font-size: 0.82rem;
	font-weight: 600;
	color: #111827;
}

.pending-meta {
	display: flex;
	align-items: center;
	gap: 8px;
}

.pending-seller {
	font-size: 0.75rem;
	color: #6b7280;
}

/* mix por Kundentyp */
.pc-mix-content {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.pc-mix-table {
	display: flex;
	flex-direction: column;
	gap: 2px;
	font-size: 0.78rem;
}

.pc-mix-header,
.pc-mix-row {
	display: grid;
	grid-template-columns: 1fr 2fr 1.5fr;
	column-gap: 8px;
	align-items: center;
}

.pc-mix-header {
	font-weight: 600;
	color: #4b5563;
}

.pc-mix-row {
	padding: 2px 0;
}

.pc-mix-row:nth-child(odd) {
	background: #f3f4f6;
}

.pc-mix-type {
	font-weight: 600;
}

.pc-mix-base,
.pc-mix-share {
	text-align: right;
}

/* Tags */
.tag-slim.p-tag {
	padding: 2px 6px;
	border-radius: 999px;
	font-size: 0.72rem;
	font-weight: 500;
}

/* ==== Delta box reutilizable ==== */

.delta-box {
	display: flex;
	flex-direction: column;
	align-items: center;
	font-size: 0.78rem;
}

.delta-main {
	font-weight: 600;
}

.delta-sub {
	font-size: 0.75rem;
}

/* ==== Misc ==== */
.empty-state {
	padding: 8px 4px 10px;
	display: flex;
	flex-direction: column;
	gap: 6px;
	align-items: flex-start;
	font-size: 0.86rem;
	color: #6b7280;
}

/* animaciones simples */
.fade-enter-active,
.fade-leave-active {
	transition: all 0.12s ease-out;
}
.fade-enter-from,
.fade-leave-to {
	opacity: 0;
	transform: translateY(-2px);
}

/* colores texto delta */
.chip-positive .delta-main,
.chip-positive .delta-sub,
.chip-positive {
	color: #166534;
}

.chip-negative .delta-main,
.chip-negative .delta-sub,
.chip-negative {
	color: #b91c1c;
}

.chip-warning .delta-main,
.chip-warning .delta-sub,
.chip-warning {
	color: #b45309;
}

.chip-neutral .delta-main,
.chip-neutral .delta-sub,
.chip-neutral {
	color: #374151;
}

/* ==== Responsive ==== */
@media (max-width: 1200px) {
	.top-grid {
		grid-template-columns: 1fr;
	}
	.bottom-grid {
		grid-template-columns: 1fr;
	}

	.donuts-grid {
		grid-template-columns: 1fr;
	}

	.cd-grid {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 900px) {
	.seller-row,
	.pc-row-seller,
	.client-row {
		grid-template-columns: minmax(0, 2.5fr) minmax(0, 2fr) minmax(0, 1.5fr);
		grid-auto-rows: auto;
		row-gap: 4px;
	}
	.seller-cell-prev,
	.seller-cell-cy,
	.seller-cell-best,
	.seller-cell-delta,
	.pc-cell-prev,
	.pc-cell-cy,
	.pc-cell-best,
	.pc-cell-delta {
		margin-top: 2px;
	}

	.pc-row {
		grid-template-columns: 1fr;
		row-gap: 6px;
	}
	.pc-metrics {
		justify-content: flex-start;
	}
}

@media (max-width: 768px) {
	.donuts-grid {
		grid-template-columns: 1fr;
	}

	.donut-chart {
		height: 180px;
	}

	.gesamt-main {
		grid-template-columns: 1fr;
	}
}
</style>
