<template>
	<div class="analytics-grid">
		<!-- === COLUMNA IZQUIERDA === -->
		<aside class="aside-col">
			<Card class="flat-card tree-card">
				<template #content>
					<Tree
						:value="nodes"
						:expandedKeys="expandedKeys"
						v-model:selectionKeys="selectionKeys"
						selectionMode="single"
						filter
						filterMode="lenient"
						:filterBy="'label'"
						v-model:filterValue="treeFilter"
						class="w-full p-0"
						@node-expand="onNodeExpand"
						@node-select="onNodeSelect"
						@node-unselect="onNodeUnselect"
						@update:selectionKeys="onSelectionUpdate"
					>
						<template #default="{ node }">
  <div class="flex align-items-center gap-2">
    <i v-if="node.data?.type === 'company'" class="pi pi-home text-primary"></i>
    <i v-else-if="node.data?.type === 'team'" class="pi pi-sitemap text-500"></i>
    <i v-else-if="node.data?.type === 'user'" class="pi pi-user"></i>
    <i v-else-if="node.data?.type === 'pc'" class="pi pi-database text-500"></i>
    <i v-else-if="node.data?.type === 'client'" class="pi pi-building"></i>
    <span>{{ node.label }}</span>
  </div>
</template>

					</Tree>
				</template>
			</Card>
		</aside>

		<!-- === COLUMNA DERECHA === -->
		<main class="main-col">
			<div class="header-grid">
				<Card class="flat-card header-card">
					<template #content>
						<AnalyticsBreadcrumb
							:nodes="nodes"
							:selectedKey="selectedKey"
							@navigate="selectByKey"
							class="w-full p-0"
						/>
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
								:disabled="fyStart >= currentFYStart"
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

				<Card v-if="series && showStacked" class="flat-card stack-card">
					<template #content>
						<Chart
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
import { useAuthStore } from '@/stores/auth'
import Card from 'primevue/card'
import Tree from 'primevue/tree'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import Chart from 'primevue/chart'
import api from '@/plugins/axios'
import AnalyticsBreadcrumb from '@/components/analytics/CompanyBreadcrumb.vue'
import ForecastTable from '@/components/analytics/AnalyticsTable.vue'

// === Auth ===
const auth = useAuthStore()
const userRole = computed(() => auth.user?.role_id ?? 99)
const userId = computed(() => auth.user?.id ?? null)

// === Tree and selection ===
const nodes = ref([])
const expandedKeys = ref({})
const selectionKeys = ref({})
const selectedKey = ref('')
const treeFilter = ref('')

// === Fecha y año fiscal ===
const now = new Date()
const initialFYStart = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
const currentFYStart = initialFYStart
const fyStart = ref(initialFYStart)
const fyLabel = computed(() => `WJ ${fyStart.value}/${String(fyStart.value + 1).slice(-2)}`)

// === Unidad de medida ===
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

// === Utilidades numéricas ===
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

// === Datos y cálculos ===
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

// === Cargar árbol ===
async function loadRoot() {
	const role = userRole.value
	const id = userId.value
	let node_id = 'root'
	if (role >= 4 && id) node_id = `user:${id}`
	else if (role === 3) node_id = 'company_main'

	const { data } = await api.get('/api/analytics/tree', { params: { node_id } })
	nodes.value = (data || []).map(toNode)

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

async function loadChildren(key) {
	const { data } = await api.get('/api/analytics/tree', { params: { node_id: key } })
	return (data || []).map(toNode)
}

function toNode(item) {
  const classification = item.meta?.classification?.toUpperCase() ?? ''
  const isClient = item.type === 'client'
  const label = isClient && classification
    ? `${classification} - ${item.label}`
    : item.label

  return {
    key: item.id,
    label,
    leaf: !item.has_children,
    data: {
      type: item.type,
      classification,
      volume: item.meta?.volume ?? 0,
      ...(item.meta || {}),
    },
    children: Array.isArray(item.children) ? item.children.map(toNode) : undefined,
  }
}


async function onNodeExpand({ node }) {
	if (!node) return
	if (!node.children) {
		const children = await loadChildren(node.key)
		if (node.data?.type === 'pc') {
			children.sort((a, b) => (b.data.volume ?? 0) - (a.data.volume ?? 0))
		}
		node.children = children
		nodes.value = [...nodes.value]
	}
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
	if (fyStart.value > 2024) {
		fyStart.value--
		fetchSeries()
	}
}
function nextFY() {
	if (fyStart.value < currentFYStart) {
		fyStart.value++
		fetchSeries()
	}
}

async function fetchSeries() {
	if (!selectedKey.value) return
	const { data } = await api.get('/api/analytics/series', {
		params: { node_id: selectedKey.value, fiscal_year: fyStart.value },
	})
	if (!data.forecasts) data.forecasts = { units: [], m3: [], euro: [] }
	series.value = data
	if (!data?.unit_mode_allowed && unitMode.value === 'units') unitMode.value = 'm3'
}

// === Gráficos ===
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
				borderColor: '#2563eb',
				backgroundColor: '#2563eb33',
				fill: false,
				tension: 0.3,
			},
			{
				label: 'Budget (acum.)',
				data: cum(budget),
				borderColor: '#16a34a',
				backgroundColor: '#16a34a33',
				fill: false,
				tension: 0.4,
			},
			{
				label: 'Forecast (acum.)',
				data: cum(fcst),
				borderColor: '#f59e0b',
				backgroundColor: '#f59e0b33',
				fill: false,
				tension: 0.4,
			},
			{
				label: 'Budget FY',
				data: fyLine,
				borderColor: '#64748b',
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
	scales: {
		y: { beginAtZero: true, ticks: { callback: (v) => fmtThousand(v) } },
	},
}))

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
			{ label: 'Budget base', data: [base], backgroundColor: '#16a34a' },
			{ label: 'Extra quota', data: [extra], backgroundColor: '#7c3aed' },
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
	height: 100vh;
	padding: 12px 16px;
	box-sizing: border-box;
}

/* Aside (árbol) */
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

/* Main */
.main-col {
	display: flex;
	flex-direction: column;
	gap: 12px;
	min-height: 0;
}

/* Header */
.header-grid {
	display: grid;
	grid-template-columns: 8fr 2fr 2fr;
	gap: 12px;
}
.header-card,
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

/* Charts */
.charts-grid {
	display: grid;
	grid-template-columns: 9fr 3fr;
	gap: 12px;
	min-height: 0;
}
.line-card.wide {
	grid-column: span 2;
}
.chart {
	height: 480px;
	width: 100%;
}
@media (max-width: 1024px) {
	.chart {
		height: 320px;
	}
	.charts-grid {
		grid-template-columns: 1fr;
	}
}

/* Tabla */
.table-card {
	flex: 1 1 auto;
	overflow: auto;
	padding-bottom: 4px;
}
.table-card :deep(.p-card-content) {
	padding: 0.4rem 0.6rem !important;
}

/* Card UI */
.flat-card :deep(.p-card-body) {
	padding: 0 !important;
}
.flat-card :deep(.p-card-content) {
	padding: 0.5rem 0.6rem !important;
}

/* Clasificación visual para clientes */
.classification-badge {
	width: 1.5rem;
	height: 1.5rem;
	border-radius: 50%;
	font-weight: bold;
	color: white;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 0.9rem;
}
.class-a {
	background-color: #1d4ed8;
}
.class-b {
	background-color: #10b981;
}
.class-c {
	background-color: #f59e0b;
}
.class-d {
	background-color: #ef4444;
}
.class-x {
	background-color: #6b7280;
}
.class-pa {
	background-color: #7c3aed;
}
.class-pb {
	background-color: #db2777;
}
</style>
