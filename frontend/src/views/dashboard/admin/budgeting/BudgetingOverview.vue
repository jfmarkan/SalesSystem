<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

import Card from 'primevue/card'
import Button from 'primevue/button'
import Chart from 'primevue/chart'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'

const loading = ref(false)
const overview = ref(null)

const ov = computed(() => overview.value)

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
function deltaClass(curr, prev) {
	const d = relDelta(curr, prev)
	if (!prev) return 'chip-neutral'
	if (d > 5) return 'chip-positive'
	if (d < -5) return 'chip-negative'
	return 'chip-neutral'
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
onMounted(loadOverview)

/* ========== DONUTS ========== */
function baseDonutOptions() {
	return {
		maintainAspectRatio: false,
		cutout: '60%',
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

/* ========== BUDGET NACH VERKÄUFER (STATE) ========== */

const openSellers = ref({})
const openPcs = ref({})

const sellerKey = (s) => s.user_id ?? s.id ?? s.full_name ?? String(Math.random())

function toggleSeller(key) {
	openSellers.value[key] = !openSellers.value[key]
}
function isSellerOpen(key) {
	return !!openSellers.value[key]
}

function pcKey(sellerKeyVal, pc) {
	const code = pc.profit_center_code ?? pc.code ?? 'pc'
	return `${sellerKeyVal}__${code}`
}
function togglePc(key) {
	openPcs.value[key] = !openPcs.value[key]
}
function isPcOpen(key) {
	return !!openPcs.value[key]
}

/* ===== coverage helpers para barras ===== */

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

/* nombre del Profit Center */
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

/* ========== OFFENE BUDGET CASES AGRUPADOS POR CLIENTE ========== */
const pendingByClient = computed(() => {
	const list = ov.value?.pending_cases || []
	const map = new Map()

	for (const r of list) {
		const key = r.client_group_number
		if (!map.has(key)) {
			map.set(key, {
				client_group_number: r.client_group_number,
				client_name: r.client_name,
				classification: r.classification,
				pcs: [],
				sellerSet: new Set(),
			})
		}
		const entry = map.get(key)
		entry.pcs.push(r.profit_center_code)
		if (r.seller_name) entry.sellerSet.add(r.seller_name)
	}

	return Array.from(map.values()).map((e) => ({
		client_group_number: e.client_group_number,
		client_name: e.client_name,
		classification: e.classification,
		pcs: e.pcs,
		seller_name: Array.from(e.sellerSet).join(', '),
	}))
})
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
								WJ {{ ov.target_fiscal_year }}/{{ String(ov.target_fiscal_year + 1).slice(-2) }}
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
						<!-- Primera fila: Basis / Best / Worst (más grandes) -->
						<div class="gesamt-main">
							<div class="metric-block">
								<div class="label">Basisvolumen</div>
								<div class="value big">
									{{ fmtInt(ov?.global.base_m3_with_case ?? 0) }}
									<span class="unit">m³</span>
								</div>
								<div class="sub">nur CPC mit Budget Case</div>
							</div>

							<div class="metric-block best">
								<div class="label">Best Case</div>
								<div class="value big green">
									{{ fmtInt(ov?.global.best_m3 ?? 0) }}
									<span class="unit">m³</span>
								</div>
								<div class="sub">
									Δ {{ fmtPctDelta(ov?.global.best_m3, ov?.global.base_m3_with_case) }}
									ggü. Basis
								</div>
							</div>

							<div class="metric-block worst">
								<div class="label">Worst Case</div>
								<div class="value big red">
									{{ fmtInt(ov?.global.worst_m3 ?? 0) }}
									<span class="unit">m³</span>
								</div>
								<div class="sub">
									Δ {{ fmtPctDelta(ov?.global.worst_m3, ov?.global.base_m3_with_case) }}
									ggü. Basis
								</div>
							</div>
						</div>

						<!-- Segunda fila: contexto -->
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
						<div class="gesamt-meta">
							Nur A, B, PA, und PB Kunden berücksichtigt.
						</div>
					</div>
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
			<!-- ====== BUDGET NACH VERKÄUFER (custom “tabla”) ====== -->
			<Card class="card seller-card">
				<template #title>Budget nach Verkäufer</template>
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
							<!-- fila VENDEDOR (5 columnas alineadas) -->
							<button
								type="button"
								class="seller-row"
								@click="toggleSeller(sellerKey(seller))"
							>
								<!-- col 1: flecha + nombre -->
								<div class="seller-cell seller-cell-name">
									<i
										class="pi"
										:class="
											isSellerOpen(sellerKey(seller))
												? 'pi-chevron-down'
												: 'pi-chevron-right'
										"
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

								<!-- col 3: Vorjahr -->
								<div class="seller-cell seller-cell-prev">
									<div class="cell-label">Vorjahr</div>
									<div class="cell-value">
										{{ fmtInt(seller.prev_m3 ?? 0) }} m³
									</div>
								</div>

								<!-- col 4: Best -->
								<div class="seller-cell seller-cell-best">
									<div class="cell-label">Best</div>
									<div class="cell-value txt-green">
										{{ fmtInt(seller.best_m3 ?? 0) }} m³
									</div>
								</div>

								<!-- col 5: Δ -->
								<div class="seller-cell seller-cell-delta">
									<div class="cell-label">Δ</div>
									<div
										class="cell-value"
										:class="deltaClass(seller.best_m3, seller.prev_m3)"
									>
										{{ fmtDeltaAbs(seller.best_m3, seller.prev_m3) }} m³
										({{ fmtDeltaPct(seller.best_m3, seller.prev_m3) }})
									</div>
								</div>
							</button>

							<!-- PCs del vendedor (también 5 columnas alineadas) -->
							<transition name="fade">
								<div
									v-if="isSellerOpen(sellerKey(seller))"
									class="seller-pc-list"
								>
									<div
										v-for="pc in seller.pcs || []"
										:key="pcKey(sellerKey(seller), pc)"
										class="pc-block"
									>
										<div class="pc-row">
											<!-- col 1: flecha + nombre PC -->
											<div
												class="pc-cell pc-cell-name"
												@click.stop="
													togglePc(pcKey(sellerKey(seller), pc))
												"
											>
												<i
													class="pi"
													:class="
														isPcOpen(pcKey(sellerKey(seller), pc))
															? 'pi-chevron-down'
															: 'pi-chevron-right'
													"
												/>
												<div class="pc-main">
													<span class="pc-name">
														{{ pcDisplayName(pc) }}
													</span>
													<span
														v-if="pc.profit_center_code"
														class="pc-code-small"
													>
														(#{{ pc.profit_center_code }})
													</span>
												</div>
											</div>

											<!-- col 2: barra coverage PC -->
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

											<!-- col 3: Vorjahr -->
											<div class="pc-cell pc-cell-prev">
												<div class="cell-label">Vorjahr</div>
												<div class="cell-value">
													{{
														pc.prev_m3 != null
															? fmtInt(pc.prev_m3)
															: '—'
													}}
													m³
												</div>
											</div>

											<!-- col 4: Best -->
											<div class="pc-cell pc-cell-best">
												<div class="cell-label">Best</div>
												<div class="cell-value txt-green">
													{{
														pc.best_m3 != null
															? fmtInt(pc.best_m3)
															: '—'
													}}
													m³
												</div>
											</div>

											<!-- col 5: Δ -->
											<div class="pc-cell pc-cell-delta">
												<div class="cell-label">Δ</div>
												<div
													class="cell-value"
													:class="deltaClass(pc.best_m3, pc.prev_m3)"
												>
													{{ fmtDeltaAbs(pc.best_m3, pc.prev_m3) }}
													m³
													({{ fmtDeltaPct(pc.best_m3, pc.prev_m3) }})
												</div>
											</div>
										</div>

										<!-- clientes del PC -->
										<transition name="fade">
											<div
												v-if="isPcOpen(pcKey(sellerKey(seller), pc))"
												class="pc-client-list"
											>
												<div
													v-if="!pc.clients || !pc.clients.length"
													class="no-clients"
												>
													Keine Kundendetails für diesen Profit
													Center.
												</div>

												<!-- clientes: 5 columnas alineadas -->
												<div
													v-else
													v-for="client in pc.clients"
													:key="client.client_group_number"
													class="client-row"
												>
													<!-- col 1: icono + nombre -->
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
															<span class="cname">
																{{ client.client_name }}
															</span>
														</div>
													</div>

													<!-- col 2: Vorjahr -->
													<div class="client-cell client-cell-prev">
														<div class="cell-label">Vorjahr</div>
														<div class="cell-value">
															{{
																client.prev_m3 != null
																	? fmtInt(client.prev_m3)
																	: '—'
															}}
															m³
														</div>
													</div>

													<!-- col 3: Best -->
													<div class="client-cell client-cell-best">
														<div class="cell-label">Best</div>
														<div class="cell-value txt-green">
															{{
																client.best_m3 != null
																	? fmtInt(client.best_m3)
																	: '—'
															}}
															m³
														</div>
													</div>

													<!-- col 4: Δ m³ -->
													<div class="client-cell client-cell-delta-m3">
														<div class="cell-label">Δ m³</div>
														<div
															class="cell-value"
															:class="
																deltaClass(
																	client.best_m3,
																	client.prev_m3,
																)
															"
														>
															{{
																fmtDeltaAbs(
																	client.best_m3,
																	client.prev_m3,
																)
															}}
														</div>
													</div>

													<!-- col 5: Δ % -->
													<div class="client-cell client-cell-delta-pct">
														<div class="cell-label">Δ %</div>
														<div
															class="cell-value"
															:class="
																deltaClass(
																	client.best_m3,
																	client.prev_m3,
																)
															"
														>
															{{
																fmtDeltaPct(
																	client.best_m3,
																	client.prev_m3,
																)
															}}
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

			<!-- Offene Budget Cases (agrupados por cliente) -->
			<Card class="card table-card">
				<template #title>
					Offene Budget Cases ({{ pendingByClient.length }})
				</template>
				<template #content>
					<DataTable
						:value="pendingByClient"
						:rows="10"
						paginator
						dataKey="client_group_number"
						:rowHover="true"
						:loading="loading"
						responsiveLayout="scroll"
						:emptyMessage="'Keine offenen Budget Cases'"
					>
						<Column header="Kunde" style="min-width: 220px">
							<template #body="{ data }">
								<div class="client-cell">
									<span class="cgn">#{{ data.client_group_number }}</span>
									<span class="cname">{{ data.client_name }}</span>
								</div>
							</template>
						</Column>

						<Column header="Klassifikation" style="width: 130px">
							<template #body="{ data }">
								<Tag
									:value="data.classification"
									:severity="classificationSeverity(data.classification)"
									class="tag-slim"
								/>
							</template>
						</Column>

						<Column header="Profit Center" style="min-width: 180px">
							<template #body="{ data }">
								<div class="pc-chip-row">
									<Tag
										v-for="pc in data.pcs"
										:key="pc"
										:value="pc"
										class="tag-slim pc-tag"
										severity="info"
									/>
								</div>
							</template>
						</Column>

						<Column header="Verkäufer" style="min-width: 160px">
							<template #body="{ data }">
								{{ data.seller_name }}
							</template>
						</Column>
					</DataTable>
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
	grid-template-columns: 6fr 6fr;
	gap: 16px;
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

.meta-label {
	font-weight: 600;
	margin-right: 4px;
}

.meta-val {
	font-weight: 400;
}

/* ==== Donuts ==== */
.donuts-card {
	min-height: 220px;
}

.donuts-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
}

.donut-block {
	display: flex;
	flex-direction: column;
	gap: 4px;
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

/* ==== Tabla genérica (pending cases) ==== */
.table-card :deep(.p-datatable-thead > tr > th) {
	padding: 0.4rem 0.6rem;
	font-size: 0.8rem;
}

.table-card :deep(.p-datatable-tbody > tr > td) {
	padding: 0.35rem 0.6rem;
	font-size: 0.8rem;
}

.client-cell {
	display: flex;
	flex-direction: column;
	gap: 2px;
	text-align: left;
}

.client-cell .cgn {
	font-size: 0.75rem;
	color: #6b7280;
}

.client-cell .cname {
	font-weight: 600;
	color: #111827;
}

.pc-chip-row {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

/* Tags */
.tag-slim.p-tag {
	padding: 2px 6px;
	border-radius: 999px;
	font-size: 0.72rem;
	font-weight: 500;
}

.pc-tag {
	background: #e0f2fe;
	color: #0369a1;
	border: 0;
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

/* fila vendedor - 5 columnas */
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
	grid-template-columns: minmax(0, 2.5fr) minmax(0, 2fr) minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1.7fr);
	column-gap: 16px;
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
	gap: 8px;
}

.seller-cell-name .pi {
	font-size: 0.85rem;
	color: #4b5563;
}

.seller-name {
	font-weight: 700;
	color: #111827;
	font-size: 0.92rem;
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
	margin-right: 10px;
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

/* ===== barras de coverage (seller + PC) ===== */

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


/* PCs del vendedor - también en 5 columnas */

.seller-pc-list {
	margin-top: 5px;
	padding-left: 10px;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.pc-block {
	background: #f9fafb;
	border-radius: 8px;
	padding: 4px 6px 4px;
	border: 1px solid #e5e7eb;
}

.pc-row {
	display: grid;
	grid-template-columns: minmax(0, 2.5fr) minmax(0, 2fr) minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1.7fr);
	column-gap: 16px;
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

/* col 1: nombre PC + flecha */
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

.pc-main {
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

.pc-code-small {
	font-size: 0.72rem;
	color: #9ca3af;
}

/* col 2: coverage PC */
.pc-cell-cov {
	align-items: flex-start;
}

.pc-progress {
	width: 100%;
	display: flex;
	flex-direction: column;
	gap: 2px;
}

/* ===== Clientes dentro del PC (alineados a columnas) ===== */

.pc-client-list {
	margin-top: 4px;
	border-top: 1px dashed #e5e7eb;
	padding-top: 4px;
	display: flex;
	flex-direction: column;
	gap: 3px;
}

.client-row {
	display: grid;
	grid-template-columns: minmax(0, 2.5fr) minmax(0, 2fr) minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 1.7fr);
	column-gap: 16px;
	align-items: center;
	padding: 3px 2px;
	border-radius: 4px;
}

.client-row:nth-child(odd) {
	background: #f3f4f6;
}

.client-row .client-cell {
	min-width: 0;
	display: flex;
	flex-direction: column;
	justify-content: start;
	align-items: flex-start;
	text-align: left;
}

/* col 1: icono + nombre cliente */
.client-cell-name {
	flex-direction: row !important;
	align-items: center;
	gap: 6px;
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

/* iconos para clientes */
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

/* colores texto extra */
.txt-green {
	color: #16a34a;
}

.chip-positive .cell-value,
.chip-positive {
	color: #166534;
}

.chip-negative .cell-value,
.chip-negative {
	color: #b91c1c;
}

.chip-neutral .cell-value,
.chip-neutral {
	color: #374151;
}

@media (max-width: 1200px) {
	.top-grid,
	.bottom-grid {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 900px) {
	.seller-row,
	.pc-row,
	.client-row {
		grid-template-columns: minmax(0, 2.5fr) minmax(0, 2fr) minmax(0, 1.5fr);
		grid-auto-rows: auto;
		row-gap: 4px;
	}
	.seller-cell-best,
	.seller-cell-delta,
	.pc-cell-best,
	.pc-cell-delta,
	.client-cell-best,
	.client-cell-delta-m3,
	.client-cell-delta-pct {
		margin-top: 2px;
	}
}

@media (max-width: 768px) {
	.donuts-grid {
		grid-template-columns: 1fr;
	}

	.donut-chart {
		height: 140px;
	}

	.gesamt-main {
		grid-template-columns: 1fr;
	}
}
</style>
