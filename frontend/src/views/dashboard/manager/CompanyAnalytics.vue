<template>
  <div class="p-2">
    <div class="grid">
      <!-- Árbol (izq) tal como ya lo tenés ... -->
      <div class="col-12 md:col-3">
        <Card class="thin-card">
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
                    <i v-if="node.data?.type==='company'" class="pi pi-home text-primary"></i>
                    <i v-else-if="node.data?.type==='team'" class="pi pi-sitemap text-500"></i>
                    <i v-else-if="node.data?.type==='user'" class="pi pi-user"></i>
                    <i v-else-if="node.data?.type==='pc'" class="pi pi-database text-500"></i>
                    <i v-else-if="node.data?.type==='client'" class="pi pi-building"></i>
                    <span>{{ node.label }}</span>
                  </div>
                </template>
              </Tree>
            </div>
          </template>
        </Card>
      </div>
      <!-- Derecha -->
      <div class="col-12 md:col-9">
        <!-- Breadcrumb -->
        <Card class="thin-card mb-2">
          <template #content>
            <AnalyticsBreadcrumb :nodes="nodes" :selectedKey="selectedKey" @navigate="selectByKey" class="w-full p-0"/>
          </template>
        </Card>

        <!-- Controles FY (Abr–Mar) + unidad -->
        <Card class="thin-card mb-2">
          <template #content>
            <div class="flex align-items-center justify-content-between">
              <div class="flex align-items-center gap-2">
                <Button icon="pi pi-angle-left" text @click="prevFY" />
                <span class="font-bold">{{ fyLabel }}</span>
                <Button icon="pi pi-angle-right" text @click="nextFY" :disabled="fyStart>=currentFYStart" />
              </div>
              <SelectButton v-model="unitMode" :options="unitOptions" optionLabel="label" optionValue="value" />
            </div>
          </template>
        </Card>

        <!-- Gráfico (ACUMULADO) -->
        <Card class="thin-card mb-2">
          <template #content>
            <Chart v-if="series" type="line" :data="chartData" :options="chartOptions" class="w-full" />
          </template>
        </Card>

        <!-- Tu tabla -->
        <Card class="thin-card">
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

// ----- Árbol
const nodes = ref([])
const expandedKeys = ref({})
const selectionKeys = ref({})
const selectedKey = ref('company_main')
const treeFilter = ref('')

// ----- FY (Abr–Mar)
const now = new Date()
const initialFYStart = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1 // Abril=3
const currentFYStart = initialFYStart
const fyStart = ref(initialFYStart)
const fyLabel = computed(() => `WJ ${fyStart.value}/${String(fyStart.value + 1).slice(-2)}`)

// ----- Unidad
const unitMode = ref('m3') // 'm3' | 'euro' | 'units' (units solo pc/cliente)
const unitOptions = computed(() => series.value?.unit_mode_allowed
  ? [{ label:'m³', value:'m3' }, { label:'€', value:'euro' }, { label:'Units', value:'units' }]
  : [{ label:'m³', value:'m3' }, { label:'€', value:'euro' }]
)

// ----- Series
const series = ref(null)

function toNode(item){
  return {
    key: item.id,
    label: item.label,
    leaf: !item.has_children,
    data: { type: item.type, ...(item.meta || {}) },
    children: Array.isArray(item.children) ? item.children.map(toNode) : undefined
  }
}
async function loadRoot(){
  const { data } = await api.get('/api/analytics/tree', { params: { node_id: 'root' } })
  nodes.value = (data || []).map(toNode)
}
async function loadChildren(key){
  const { data } = await api.get('/api/analytics/tree', { params: { node_id: key } })
  return (data || []).map(toNode)
}
async function onNodeExpand({ node }){
  if (!node) return
  if (!node.children) {
    node.children = await loadChildren(node.key)
    nodes.value = [...nodes.value]
  }
  expandedKeys.value = { ...expandedKeys.value, [node.key]: true }
}
async function onNodeSelect({ node }){
  if (!node) return
  selectedKey.value = node.key
  selectionKeys.value = { [node.key]: true }
  await fetchSeries()
}
function onNodeUnselect(){
  selectionKeys.value = {}
  selectedKey.value = ''
}
function onSelectionUpdate(val){
  const ks = Object.keys(val || {})
  if (ks.length) {
    selectedKey.value = ks[0]
    fetchSeries()
  }
}
function selectByKey(key){
  if (!key) return
  selectedKey.value = key
  selectionKeys.value = { [key]: true }
  fetchSeries()
}

// ----- FY nav
function prevFY(){ if (fyStart.value > 2024) { fyStart.value--; fetchSeries() } }
function nextFY(){ if (fyStart.value < currentFYStart) { fyStart.value++; fetchSeries() } }

// ----- Fetch
async function fetchSeries(){
  if (!selectedKey.value) return
  const { data } = await api.get('/api/analytics/series', {
    params: { node_id: selectedKey.value, fiscal_year: fyStart.value }
  })
  // fallback si forecasts no existe (por tu nueva tabla): dejo arrays vacíos
  if (!data.forecasts) data.forecasts = { units:[], m3:[], euro:[] }
  series.value = data
  if (!data?.unit_mode_allowed && unitMode.value === 'units') unitMode.value = 'm3'
  console.debug('[analytics.series]', { node: selectedKey.value, fy: fyStart.value, months: data?.months?.length, unitModeAllowed: data?.unit_mode_allowed })
}

// ----- Helpers
const months    = computed(() => series.value?.months || [])
const salesArr  = computed(() => series.value ? (series.value.sales[unitMode.value]     || []) : [])
const budgetArr = computed(() => series.value ? (series.value.budgets[unitMode.value]   || []) : [])
const fcstArr   = computed(() => series.value ? (series.value.forecasts[unitMode.value] || []) : [])

// ----- Chart ACUMULADO
function arrOrZero12(a){ const out=[]; for(let i=0;i<12;i++){ out.push(Number(a?.[i]||0)) } return out }
function cum(arr){ const out=[]; let s=0; for(let i=0;i<12;i++){ s += Number(arr?.[i]||0); out.push(s) } return out }
const chartData = computed(() => {
  if (!series.value) return { labels: [], datasets: [] }
  const s = series.value, k = unitMode.value
  const sales = arrOrZero12(s.sales[k])
  const budget = arrOrZero12(s.budgets[k])
  const fcst = arrOrZero12(s.forecasts[k])

  const salesCum = cum(sales)
  const budgetCum = cum(budget)
  const fcstCum = cum(fcst)
  const budgetFY = budget.reduce((a,b)=>a+Number(b||0),0)
  const fyLine = Array(12).fill(budgetFY)

  return {
    labels: s.months,
    datasets: [
      { label:'Sales (cum)',    data:salesCum,  borderColor:'#2563eb', backgroundColor:'#2563eb33', fill:false, tension:.3 },
      { label:'Budget (cum)',   data:budgetCum, borderColor:'#16a34a', backgroundColor:'#16a34a33', fill:false, tension:.4 },
      { label:'Forecast (cum)', data:fcstCum,   borderColor:'#f59e0b', backgroundColor:'#f59e0b33', fill:false, tension:.4 },
      { label:'Budget FY',      data:fyLine,    borderColor:'#64748b', borderDash:[6,6], fill:false, tension:0 }
    ]
  }
})
const chartOptions = {
  plugins: { legend: { position: 'bottom' } },
  scales: { y: { beginAtZero: true } }
}

// ----- Mount
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
.thin-card :deep(.p-card-content){ padding:5px !important; }
.tree-wrap{ height:calc(100vh - 70px - 1rem); overflow-y:auto; }
</style>