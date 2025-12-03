<template>
	<div class="analytics-grid">
		<!-- === COLUMNA IZQUIERDA === -->
		<aside class="pane left">
			<!-- Lista ocupa todo el alto -->
			<div class="list-wrap">
				<Tree
					:value="nodes"
					:expandedKeys="expandedKeys"
					v-model:selectionKeys="selectionKeys"
					selectionMode="single"
					:filter="true"
					:filterValue="treeFilter"
					filterMode="lenient"
					:filterBy="'label'"
					class="w-full"
					@node-expand="onNodeExpand"
					@node-select="onNodeSelect"
					@node-unselect="onNodeUnselect"
					@update:selectionKeys="onSelectionUpdate"
				>
					<template #default="{ node }">
						<div class="tree-node-content">
							<i v-if="node.data?.type === 'company'" class="pi pi-home text-primary" />
							<i v-else-if="node.data?.type === 'team'" class="pi pi-sitemap text-500" />
							<i v-else-if="node.data?.type === 'user'" class="pi pi-user" />
							<i v-else-if="node.data?.type === 'pc'" class="pi pi-database text-500" />
							<template v-else-if="node.data?.type === 'client'">
								<span
									class="classification-badge"
									:class="'class-' + (node.data.classification || 'x').toLowerCase()"
								>
									{{ node.data.classification }}
								</span>
							</template>
							<span class="node-label">{{ node.label }}</span>
						</div>
					</template>
				</Tree>
			</div>
		</aside>

		<!-- === COLUMNA DERECHA === -->
		<main class="main-col">
			<div class="header-grid">
				<Card class="flat-card header-card">
					<template #content>
						<div class="breadcrumb-wrapper">
							<AnalyticsBreadcrumb
								:nodes="nodes"
								:selectedKey="selectedKey"
								@navigate="selectByKey"
							/>
						</div>
					</template>
				</Card>

				<Card class="flat-card fy-card">
					<template #content>
						<div class="fy-switch">
							<Button icon="pi pi-angle-left" text @click="prevFY" />
							<span class="fy-text">{{ fyLabel }}</span>
							<Button
								icon="pi pi-angle-right"
								text
								@click="nextFY"
								:disabled="fyStart >= maxFYStart"
							/>
						</div>
					</template>
				</Card>

				<Card class="flat-card unit-card">
					<template #content>
						<SelectButton
							v-model="unitMode"
							:options="unitOptions"
							optionLabel="label"
							optionValue="value"
						/>
					</template>
				</Card>
			</div>

			<div class="charts-grid">
				<!-- Gráfico de líneas -->
				<Card class="flat-card line-card" :class="{ wide: !showStacked }">
					<template #content>
						<Chart
							v-if="series"
							type="line"
							:data="chartData"
							:options="chartOptions"
							class="chart"
						/>
					</template>
				</Card>

				<!-- Gráfico stacked (extra cuota) -->
				<Card class="flat-card stack-card" v-show="showStacked">
					<template #content>
						<Chart
							v-if="series"
							type="bar"
							:data="stackedData"
							:options="stackedOptions"
							class="chart"
						/>
					</template>
				</Card>
			</div>

			<Card class="flat-card table-card">
				<template #content>
					<ForecastTable
						v-if="series"
						:months="months"
						:sales="salesArr"
						:budget="budgetArr"
						:forecast="fcstArr"
					/>
				</template>
			</Card>
		</main>
	</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'
import AnalyticsBreadcrumb from '@/components/analytics/CompanyBreadcrumb.vue'
import ForecastTable from '@/components/analytics/AnalyticsTable.vue'

const nodes = ref([])
const expandedKeys = ref({})
const selectionKeys = ref({})
const selectedKey = ref('')
const treeFilter = ref('')

// === LÓGICA AÑO FISCAL ===
const now = new Date()
const month = now.getMonth() // 0 = enero, 11 = diciembre

// Año fiscal actual: empieza en abril (mes 3)
const initialFYStart = month >= 3 ? now.getFullYear() : now.getFullYear() - 1

// Entre octubre (mes 9) y marzo (0–2) se permite ver el siguiente FY
const canSeeNextFY = month >= 9 || month <= 2
const maxFYStart = canSeeNextFY ? initialFYStart + 1 : initialFYStart

const fyStart = ref(initialFYStart)
const fyLabel = computed(() => `WJ ${fyStart.value}/${String(fyStart.value + 1).slice(-2)}`)

const unitMode = ref('m3')
const series = ref(null)
const unitOptions = computed(() =>
	series.value?.unit_mode_allowed
		? [
				{ label: 'm³', value: 'm3' },
				{ label: '€', value: 'euro' },
				{ label: 'VK-EH', value: 'units' },
		  ]
		: [
				{ label: 'm³', value: 'm3' },
				{ label: '€', value: 'euro' },
		  ],
)

// helpers numéricos
const toNum = (v) => {
	if (v == null) return 0
	if (typeof v === 'number') return v
	const n = Number(String(v).replace(/\./g, ''))
	return Number.isFinite(n) ? n : 0
}
const toNums12 = (arr) => Array.from({ length: 12 }, (_, i) => toNum(arr?.[i]))
const fmtThousand = (n) =>
	Math.round(n)
		.toString()
		.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

function distributeProportional(base, extra) {
	const out = base.slice()
	const total = base.reduce((a, b) => a + (b || 0), 0)
	if (extra === 0) return out
	if (total <= 0) {
		const per = extra / 12
		return out.map(() => per)
	}
	return out.map((v) => v + (v / total) * extra)
}

const months = computed(() => series.value?.months || [])
const salesArr = computed(() => (series.value ? series.value.sales[unitMode.value] || [] : []))

const budgetArr = computed(() => {
	const s = series.value
	if (!s) return []

	const k = unitMode.value
	const ctx = s.context?.type
	const base = toNums12(s.budgets?.[k] || [])

	if (['company', 'team', 'user', 'pc'].includes(ctx)) return base

	const assignedTotal = toNum(s?.extra_breakdown?.assigned?.[k]) || 0
	const wonTotal = toNum(s?.extra_breakdown?.won?.[k]) || 0
	const remaining = toNum(s?.extra_quotas?.[k] ?? 0)

	const delta = assignedTotal > 0 || wonTotal > 0 ? assignedTotal - wonTotal : remaining

	return distributeProportional(base, delta)
})

const fcstArr = computed(() => {
	if (!series.value) return []
	const s = series.value
	const k = unitMode.value
	const base = toNums12(s.forecasts?.[k] || [])
	if (s?.meta?.forecasts_includes_eqf === true) return base
	const eqf = toNums12(s.extra_quota_forecasts?.[k] || [])
	return base.map((v, i) => v + (eqf[i] || 0))
})

// Cargar árbol
async function loadRoot() {
	const { data } = await api.get('/api/analytics/tree', { params: { node_id: 'root' } })
	// 🔥 filtramos clientes X en el mapeo
	nodes.value = (data || []).map(toNode).filter(Boolean)

	const rootNode = nodes.value?.[0]
	const ek = {}
	if (rootNode) {
		ek[rootNode.key] = true
		if (Array.isArray(rootNode.children)) {
			for (const t of rootNode.children) ek[t.key] = true
		}
		selectedKey.value = rootNode.key
		selectionKeys.value = { [rootNode.key]: true }
	}
	expandedKeys.value = ek
	await fetchSeries()
}

// No lazy load, pero por si acaso, ordenamos clientes por volumen €
async function onNodeExpand({ node }) {
	if (!node) return
	if (!node.children) {
		const { data } = await api.get('/api/analytics/tree', { params: { node_id: node.key } })
		// 🔥 también aquí filtramos clientes X
		node.children = (data || []).map(toNode).filter(Boolean)
	}
	if (node.data?.type === 'pc' && Array.isArray(node.children)) {
		node.children.sort((a, b) => (b.data.volume ?? 0) - (a.data.volume ?? 0))
	}
	nodes.value = [...nodes.value]
	expandedKeys.value = { ...expandedKeys.value, [node.key]: true }
}

async function onNodeSelect({ node }) {
	if (!node) return
	selectedKey.value = node.key
	selectionKeys.value = { [node.key]: true }
	await fetchSeries()
}
function onNodeUnselect() {
	selectionKeys.value = {}
	selectedKey.value = ''
}
function onSelectionUpdate(val) {
	const ks = Object.keys(val || {})
	if (ks.length) {
		selectedKey.value = ks[0]
		fetchSeries()
	}
}
function selectByKey(key) {
	if (!key) return
	selectedKey.value = key
	selectionKeys.value = { [key]: true }
	fetchSeries()
}

function prevFY() {
	// límite inferior hardcodeado por ahora (2024)
	if (fyStart.value > 2024) {
		fyStart.value--
		fetchSeries()
	}
}

function nextFY() {
	// solo avanzamos si no superamos el máximo permitido según la fecha actual
	if (fyStart.value < maxFYStart) {
		fyStart.value++
		fetchSeries()
	}
}

async function fetchSeries() {
	if (!selectedKey.value) return

	const { data } = await api.get('/api/analytics/series', {
		params: { node_id: selectedKey.value, fiscal_year: fyStart.value },
	})

	// fallback por si algún backend viejo no trae forecasts
	if (!data.forecasts) data.forecasts = { units: [], m3: [], euro: [] }

	series.value = data

	// --- gestionar unidad por defecto / unidades permitidas ---
	const allowedModes = data.unit_mode_allowed
		? ['m3', 'euro', 'units']
		: ['m3', 'euro']

	const apiDefault = data.meta?.unit_mode_default || 'm3'

	// Si el modo actual no es válido en este contexto, forzamos uno válido
	if (!allowedModes.includes(unitMode.value)) {
		unitMode.value = allowedModes.includes(apiDefault) ? apiDefault : allowedModes[0]
	}
}

// Mapeo de items a TreeNode (agregamos clasificación + volume)
// 🔥 Aquí hacemos que los clientes clasificación X NO se mapeen
function toNode(item) {
	const t = item.type
	const classification = item.meta?.classification ?? ''
	const volume = item.meta?.volume ?? 0

	// Si es cliente tipo X -> no lo mostramos en el árbol
	if (t === 'client' && String(classification).toLowerCase() === 'x') {
		return null
	}

	return {
		key: item.id,
		label: item.label, // ya viene "LETTER - Nombre" desde backend
		leaf: !item.has_children,
		data: { type: t, classification, volume, ...(item.meta || {}) },
		children: Array.isArray(item.children)
			? item.children.map(toNode).filter(Boolean)
			: undefined,
	}
}

// Gráficos
const cum = (arr) =>
	arr.reduce((acc, v, i) => {
		acc.push((acc[i - 1] || 0) + v)
		return acc
	}, [])

const chartData = computed(() => {
	if (!series.value) return { labels: [], datasets: [] }
	const s = series.value
	const k = unitMode.value
	const sales = toNums12(s.sales[k])
	const budget = toNums12(s.budgets[k])
	const fcst = fcstArr.value
	const fyLine = Array(12).fill(budget.reduce((a, b) => a + b, 0))
	return {
		labels: s.months,
		datasets: [
			{
				label: 'Sales (acum.)',
				data: cum(sales),
				borderColor: '#6E8DA8',
				backgroundColor: '#2563eb33',
				fill: false,
				tension: 0.3,
			},
			{
				label: 'Budget (acum.)',
				data: cum(budget),
				borderColor: '#7AA488',
				backgroundColor: '#16a34a33',
				fill: false,
				tension: 0.4,
			},
			{
				label: 'Forecast (acum.)',
				data: cum(fcst),
				borderColor: '#B3A45B',
				backgroundColor: '#f59e0b33',
				fill: false,
				tension: 0.4,
			},
			{
				label: 'Budget FY',
				data: fyLine,
				borderColor: '#64748B',
				borderDash: [6, 6],
				fill: false,
				tension: 0,
			},
		],
	}
})

const chartOptions = computed(() => ({
	maintainAspectRatio: false,
	plugins: {
		legend: { position: 'bottom' },
		tooltip: {
			callbacks: { label: (ctx) => `${ctx.dataset.label}: ${fmtThousand(ctx.parsed.y)}` },
		},
	},
	scales: { y: { beginAtZero: true, ticks: { callback: (v) => fmtThousand(v) } } },
}))

// Stacked
const showStacked = computed(() => {
	const t = series.value?.context?.type
	return ['company', 'team', 'user', 'pc'].includes(t)
})

const stackedData = computed(() => {
	if (!series.value) return { labels: [], datasets: [] }
	const k = unitMode.value
	const totalBudget = toNum(series.value?.totals?.budgets?.[k] ?? 0)
	const extra = toNum(series.value?.extra_quotas?.[k] ?? 0)
	const base = Math.max(0, totalBudget - extra)
	return {
		labels: ['FY'],
		datasets: [
			{ label: 'Budget base', data: [base], backgroundColor: '#7AA488' },
			{ label: 'Extra quota', data: [extra], backgroundColor: '#A47A96' },
		],
	}
})

const stackedOptions = computed(() => ({
	maintainAspectRatio: false,
	responsive: true,
	plugins: {
		legend: { position: 'bottom' },
		tooltip: {
			callbacks: { label: (ctx) => `${ctx.dataset.label}: ${fmtThousand(ctx.parsed.y)}` },
		},
	},
	scales: {
		x: { stacked: true },
		y: { stacked: true, beginAtZero: true, ticks: { callback: (v) => fmtThousand(v) } },
	},
}))

onMounted(() => {
	loadRoot()
})
</script>

<style scoped>
.analytics-grid {
	display: grid;
	grid-template-columns: 3fr 9fr;
	gap: 16px;
	min-height: 0;
	height: 100%;
	box-sizing: border-box;
}

/* === ASIDE === */
.aside-col {
	display: flex;
	flex-direction: column;
	overflow: hidden;
}

.tree-card {
	flex: 1 1 auto;
	overflow: hidden;
}

.tree-card :deep(.p-card-content) {
	padding: 6px 8px !important;
}

.tree-card .p-tree {
	height: calc(100vh - 120px);
	overflow-y: auto;
}

.tree-node-content {
	display: flex;
	align-items: start;
	gap: 0.25rem;
	min-width: 0;
}

.tree-node-content i,
.tree-node-content .classification-badge {
	flex: 0 0 auto;
}

.tree-node-content .node-label {
	flex: 1 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

:deep(.p-tree .p-treenode-content) {
	min-width: 0;
}
:deep(.p-tree .p-treenode-label) {
	min-width: 0;
}

/* Evita cortes raros en palabras largas */
.tree-node-content .node-label {
	word-break: normal;
}

/* === MAIN === */
.main-col {
	display: grid;
	flex-direction: column;
	grid-template-rows: auto 1fr auto;
	gap: 12px;
	min-height: 0;
}

.header-grid {
	display: grid;
	grid-template-columns: 8fr 2fr 2fr;
	gap: 12px;
}

.header-card {
	font-size: 0.85rem;
}

.fy-card,
.unit-card {
	min-height: 46px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.fy-switch {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
}

.fy-text {
	font-weight: 600;
	font-size: 0.9rem;
}

.charts-grid {
	display: grid;
	grid-template-columns: 9fr 3fr;
	gap: 16px;
	min-height: 0;
	max-width: 100%;
	overflow: hidden;
}

.line-card.wide {
	grid-column: span 2;
}

.chart {
	height: 480px;
	width: 100%;
	max-width: 100%;
	padding: 1rem;
}

@media (max-width: 1024px) {
	.chart {
		height: 320px;
	}

	.charts-grid {
		grid-template-columns: 1fr;
	}
}

.table-card {
	flex: 0 0 auto;
	overflow: visible;
	padding: 0.5rem;
}

.flat-card :deep(.p-card-body) {
	padding: 0 !important;
}

.flat-card :deep(.p-card-content) {
	padding: 0.5rem 0.6rem !important;
}

/* Badge de clasificación */
.classification-badge {
	width: 1.4rem;
	height: 1.4rem;
	border-radius: 50%;
	font-weight: bold;
	color: white;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 0.8rem;
}

.class-a {
	background-color: #668c73;
}

/* Azul */
.class-b {
	background-color: #59768e;
}

/* Verde */
.class-c {
	background-color: #978b4b;
}

/* Amarillo */
.class-d {
	background-color: #a3535b;
}

/* Rojo */
.class-x {
	background-color: #8c8c8c;
}

/* Gris */
.class-pa {
	background-color: #91b79d;
}

/* Violeta */
.class-pb {
	background-color: #86a2bd;
}

.pane.left {
	display: flex;
	flex-direction: column;
	padding: 10px;
	background: var(--surface-card, #fff);
	border-radius: 10px;
	box-shadow: 0 1px 8px rgba(0, 0, 0, 0.06);
	min-height: 0;
}

.pane-head {
	display: flex;
	gap: 8px;
	align-items: center;
	margin-bottom: 8px;
}

.list-wrap {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	height: 100%;
	overflow-y: auto;
	overflow-x: hidden;
}

.p-tree {
	flex: 1 1 auto;
	min-height: 0;
	height: auto;
	overflow-y: auto;
	overflow-x: hidden;
}
</style>
