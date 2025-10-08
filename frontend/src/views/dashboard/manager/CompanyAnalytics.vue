<template>
  <div class="p-1">
    <div class="grid">
      <!-- Árbol (izq) 3 cols -->
      <div class="col-12 md:col-3">
        <Card class="thin-card micro-card full-card">
          <template #content>
            <div class="tree-wrap">
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
            </div>
          </template>
        </Card>
      </div>

      <!-- Derecha 9 cols -->
      <div class="col-12 md:col-9 rhs">
        <!-- Fila compacta: Breadcrumbs 8 | FY 2 | Unidad 2 -->
        <div class="grid align-stretch mb-2 header-row">
          <div class="col-12 md:col-8">
            <Card class="thin-card ultra-compact header-card full-card rhs-card">
              <template #content>
                <div class="center-v">
                  <AnalyticsBreadcrumb
                    :nodes="nodes"
                    :selectedKey="selectedKey"
                    @navigate="selectByKey"
                    class="w-full p-0"
                  />
                </div>
              </template>
            </Card>
          </div>

          <!-- Año fiscal -->
          <div class="col-6 md:col-2">
            <Card class="thin-card ultra-compact header-card full-card rhs-card">
              <template #content>
                <div class="center-v justify-center gap-2">
                  <Button icon="pi pi-angle-left" text @click="prevFY" />
                  <span class="font-bold fy-text">{{ fyLabel }}</span>
                  <Button
                    icon="pi pi-angle-right"
                    text
                    @click="nextFY"
                    :disabled="fyStart >= currentFYStart"
                  />
                </div>
              </template>
            </Card>
          </div>

          <!-- Unidad -->
          <div class="col-6 md:col-2">
            <Card class="thin-card ultra-compact header-card full-card rhs-card">
              <template #content>
                <div class="content-fill flex align-items-center justify-content-center h-full">
                  <SelectButton
                    v-model="unitMode"
                    :options="unitOptions"
                    optionLabel="label"
                    optionValue="value"
                  />
                </div>
              </template>
            </Card>
          </div>
        </div>

        <!-- Gráficos -->
        <div class="grid mb-2">
          <div :class="showStacked ? 'col-12 md:col-9' : 'col-12'">
            <Card class="thin-card micro-card full-card rhs-card">
              <template #content>
                <div class="content-fill w-full">
                  <Chart
                    v-if="series"
                    type="line"
                    :data="chartData"
                    :options="chartOptions"
                    class="w-full line-chart"
                  />
                </div>
              </template>
            </Card>
          </div>

          <div class="col-12 md:col-3" v-if="series && showStacked">
            <Card class="thin-card micro-card full-card rhs-card">
              <template #content>
                <div class="content-fill w-full">
                  <div class="flex align-items-center justify-content-between mb-1"></div>
                  <Chart
                    type="bar"
                    :data="stackedData"
                    :options="stackedOptions"
                    class="w-full stacked-chart"
                  />
                </div>
              </template>
            </Card>
          </div>
        </div>

        <!-- Tabla (card más pequeña) -->
        <Card class="thin-card ultra-compact table-card rhs-card">
          <template #content>
            <div class="content-fill w-full">
              <ForecastTable
                v-if="series"
                :months="months"
                :sales="salesArr"
                :budget="budgetArr"
                :forecast="fcstArr"
              />
            </div>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Card from 'primevue/card'
import Tree from 'primevue/tree'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import Chart from 'primevue/chart'
import api from '@/plugins/axios'
import AnalyticsBreadcrumb from '@/components/analytics/CompanyBreadcrumb.vue'
import ForecastTable from '@/components/analytics/AnalyticsTable.vue'

const nodes = ref([])
const expandedKeys = ref({})
const selectionKeys = ref({})
const selectedKey = ref('company_main')
const treeFilter = ref('')

const now = new Date()
const initialFYStart = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
const currentFYStart = initialFYStart
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

// Distribuye un total `extra` sobre el array `base` proporcionalmente a sus valores;
// si todo `base` es 0, reparte uniforme.
function distributeProportional(base, extra) {
  const out = base.slice()
  const total = base.reduce((a, b) => a + (b || 0), 0)
  if (extra === 0) return out
  if (total <= 0) {
    const per = extra / 12
    return out.map(() => per)
  }
  return out.map(v => v + (v / total) * extra)
}

const months   = computed(() => series.value?.months || [])
const salesArr = computed(() => (series.value ? series.value.sales[unitMode.value] || [] : []))

// Budget final: para company/team/user/pc el backend ya incluye EQ restante → usar tal cual.
// Para client: base + (assigned - won) distribuido proporcionalmente; fallback a remaining total.
const budgetArr = computed(() => {
  const s = series.value
  if (!s) return []

  const k   = unitMode.value
  const ctx = s.context?.type
  const base = toNums12(s.budgets?.[k] || [])

  return base
  

  const assignedTotal = toNum(s?.extra_breakdown?.assigned?.[k]) || 0  // opcional
  const wonTotal      = toNum(s?.extra_breakdown?.won?.[k])      || 0  // opcional
  let delta = 0

  if (assignedTotal > 0 || wonTotal > 0) {
    delta = assignedTotal - wonTotal
  } else {
    delta = toNum(s?.extra_quotas?.[k] ?? 0) // remaining total
  }

  return distributeProportional(base, delta)
})

// Forecast final = forecast base + EQF (máx versión por oportunidad y mes).
// Si el backend ya lo incluyó (meta.forecasts_includes_eqf === true), no sumamos.
const fcstArr = computed(() => {
  if (!series.value) return []
  const s = series.value
  const k = unitMode.value
  const base = toNums12(s.forecasts?.[k] || [])
  if (s?.meta?.forecasts_includes_eqf === true) {
    return base
  }
  const eqf = toNums12(s.extra_quota_forecasts?.[k] || [])
  return base.map((v, i) => v + (eqf[i] || 0))
})

async function loadRoot() {
  const { data } = await api.get('/api/analytics/tree', { params: { node_id: 'root' } })
  nodes.value = (data || []).map(toNode)
}
async function loadChildren(key) {
  const { data } = await api.get('/api/analytics/tree', { params: { node_id: key } })
  return (data || []).map(toNode)
}
function toNode(item) {
  return {
    key: item.id,
    label: item.label,
    leaf: !item.has_children,
    data: { type: item.type, ...(item.meta || {}) },
    children: Array.isArray(item.children) ? item.children.map(toNode) : undefined,
  }
}
async function onNodeExpand({ node }) {
  if (!node) return
  if (!node.children) {
    node.children = await loadChildren(node.key)
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

// Line chart (usa Forecast combinado con EQF)
const cum = (arr) =>
  arr.reduce((acc, v, i) => {
    acc.push((acc[i - 1] || 0) + v)
    return acc
  }, [])
const chartData = computed(() => {
  if (!series.value) return { labels: [], datasets: [] }
  const s = series.value
  const k = unitMode.value
  const sales  = toNums12(s.sales[k])
  const budget = toNums12(s.budgets[k])
  const fcst   = fcstArr.value // ya combinado (base + EQF si hace falta)
  const salesCum  = cum(sales)
  const budgetCum = cum(budget)
  const fcstCum   = cum(fcst)
  const budgetFY  = budget.reduce((a, b) => a + b, 0)
  const fyLine = Array(12).fill(budgetFY)
  return {
    labels: s.months,
    datasets: [
      {
        label: 'Sales (acum.)',
        data: salesCum,
        borderColor: '#2563eb',
        backgroundColor: '#2563eb33',
        fill: false,
        tension: 0.3,
      },
      {
        label: 'Budget (acum.)',
        data: budgetCum,
        borderColor: '#16a34a',
        backgroundColor: '#16a34a33',
        fill: false,
        tension: 0.4,
      },
      {
        label: 'Forecast (acum.)',
        data: fcstCum,
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
  scales: { y: { beginAtZero: true, ticks: { callback: (v) => fmtThousand(v) } } },
}))

// Stacked: mostrar en company, team, user y pc; ocultar en client
const showStacked = computed(() => {
  const t = series.value?.context?.type
  return ['company','team','user','pc'].includes(t)
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

onMounted(async () => {
  await loadRoot()
  const company = nodes.value?.[0]
  const ek = {}
  if (company) {
    ek[company.key] = true
    if (Array.isArray(company.children)) for (const t of company.children) ek[t.key] = true
    selectedKey.value = company.key
    selectionKeys.value = { [company.key]: true }
  }
  expandedKeys.value = ek
  await fetchSeries()
})
</script>

<style scoped>
/* Base existentes */
.thin-card :deep(.p-card-content) {
  padding: 5px 8px !important;
}
.micro-card :deep(.p-card-content) {
  padding: 4px 6px !important;
}
.full-card {
  height: 100%;
}
.align-stretch {
  align-items: stretch;
}

/* RHS: padding lateral .5rem y centrado vertical */
.rhs .rhs-card :deep(.p-card-body) {
  padding: 0 !important;
}
.rhs .rhs-card :deep(.p-card-content) {
  padding: 0.25rem 0.5rem !important; /* .25 vertical, .5 lateral */
  display: flex;
  align-items: center;
}

.center-v {
  display: flex;
  align-items: center;
  min-height: 40px;
  height: 100%;
  width: 100%;
}
.justify-center {
  justify-content: center;
}
.content-fill {
  width: 100%;
}

/* Header bajo */
.header-row {
  row-gap: 0.25rem;
}
.header-card {
  min-height: 40px;
}
.header-card :deep(.p-button.p-button-text) {
  padding: 0.15rem 0.25rem !important;
}
.header-card :deep(.p-selectbutton .p-button) {
  padding: 0.2rem 0.4rem !important;
}
.fy-text {
  font-size: 0.9rem;
  line-height: 1;
}

/* Tabla compacta */
.table-card :deep(.p-card-content) {
  padding: 0.25rem 0.5rem !important;
}

/* Árbol */
.tree-wrap {
  height: calc(100vh - 4rem - 70px);
  overflow-y: auto;
}

/* Charts */
.line-chart {
  height: 540px;
}
.stacked-chart {
  height: 540px;
}
.small-title {
  font-size: 0.85rem;
  margin: 0;
}

@media (max-width: 960px) {
  .line-chart,
  .stacked-chart {
    height: 300px;
  }
}
</style>