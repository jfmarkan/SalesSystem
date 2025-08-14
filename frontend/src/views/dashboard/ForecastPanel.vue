<script setup>
/* German UI; English code/comments */
import { ref, computed, watch, onMounted } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import ForecastTitle from '@/components/titles/ComponentTitle.vue'
import ForecastFilters from '@/components/filters/ComponentFilter.vue'
import ForecastChart from '@/components/charts/LineChart.vue'
import ForecastTable from '@/components/tables/ComponentTable.vue'
import GlassCard from '@/components/ui/GlassCard.vue'

const toast = useToast()
const API = '/api'

/** Master data */
const clients = ref([])               // [{ id, name }]
const profitCenters = ref([])         // [{ id, code, name }]
const mapClientToPC = ref({})         // { [clientId:number]: number[] }
const mapPcToClient = ref({})         // { [pcId:number]: number[] }

/** Filters — start COMPLETELY empty */
const mode = ref(null)                // 'kunde' | 'pc' | null  ← radios unselected
const primaryId = ref(null)           // user selects
const secondaryId = ref(null)         // user selects
const loading = ref(false)

/** Series (empty until valid selection) */
const months = ref([])
const sales = ref([])
const budget = ref([])
const forecast = ref([])
const orders = ref([])

/** Options depend on mode; return [] when mode is null */
const primaryOptions = computed(() => {
  if (mode.value === 'kunde') {
    return clients.value.map(c => ({ label: c.name, value: c.id }))
  }
  if (mode.value === 'pc') {
    return profitCenters.value.map(p => ({ label: `${p.code} — ${p.name}`, value: p.id }))
  }
  return []
})
const secondaryOptions = computed(() => {
  if (!mode.value || primaryId.value == null) return []
  if (mode.value === 'kunde') {
    const ids = mapClientToPC.value[primaryId.value] || []
    return ids.map(id => {
      const p = profitCenters.value.find(x => x.id === id)
      return p && { label: `${p.code} — ${p.name}`, value: p.id }
    }).filter(Boolean)
  } else {
    const ids = mapPcToClient.value[primaryId.value] || []
    return ids.map(id => {
      const c = clients.value.find(x => x.id === id)
      return c && { label: c.name, value: c.id }
    }).filter(Boolean)
  }
})

/** Only fetch when user selected mode + both IDs */
const canQuery = computed(() =>
  !!mode.value && primaryId.value != null && secondaryId.value != null
)

/** Master fetch (no auto-select) */
async function loadMaster() {
  try {
    await ensureCsrf()
    const [{ data: c }, { data: p }, { data: m }] = await Promise.all([
      api.get(`${API}/me/clients`),
      api.get(`${API}/me/profit-centers`),
      api.get(`${API}/me/assignments`),
    ])
    clients.value = Array.isArray(c) ? c : []
    profitCenters.value = Array.isArray(p) ? p : []
    mapClientToPC.value = m?.clientToPc ?? {}
    mapPcToClient.value = m?.pcToClient ?? {}
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Stammdaten nicht verfügbar', life:2500 })
  }
}

/** Clear graph/table to blank */
function clearSeries() {
  months.value = []
  sales.value = []
  budget.value = []
  forecast.value = []
  orders.value = []
}

/** Load series only when canQuery is true */
async function loadSeries() {
  if (!canQuery.value) { clearSeries(); return }
  loading.value = true
  try {
    await ensureCsrf()
    const clientId = mode.value === 'kunde' ? primaryId.value : secondaryId.value
    const profitCenterId = mode.value === 'kunde' ? secondaryId.value : primaryId.value
    const { data } = await api.get(`${API}/forecast/series`, { params: { clientId, profitCenterId } })
    months.value   = data?.months   ?? []
    sales.value    = data?.sales    ?? []
    budget.value   = data?.budget   ?? []
    forecast.value = data?.forecast ?? []
    orders.value   = data?.orders   ?? []
  } catch (e) {
    console.error('loadSeries failed', e)
    clearSeries()
    // Optional user toast:
    // toast.add({ severity:'error', summary:'Fehler', detail:'Datenabruf fehlgeschlagen', life:2500 })
  } finally {
    loading.value = false
  }
}

/** React only when user sets something; do not auto-select anything */
watch([mode, primaryId, secondaryId], () => {
  if (!canQuery.value) { clearSeries(); return }
  loadSeries()
})

/** Mode change from radio: keep everything empty until user selects IDs */
function handleModeChange(v) {
  mode.value = v ?? null
  primaryId.value = null
  secondaryId.value = null
  clearSeries()
}

/** “Weiter” from filter: rotate secondary within current list */
function handleNext() {
  const list = secondaryOptions.value
  if (!list.length) return
  const idx = list.findIndex(o => o.value === secondaryId.value)
  const n = (idx >= 0 ? idx + 1 : 0) % list.length
  secondaryId.value = list[n].value
}

onMounted(loadMaster)

/** Layout (visual) */
const layout = ref([
  { i:'filters', x:0,  y:0,  w:2,  h:27, static:true },
  { i:'title',   x:2,  y:0,  w:10, h:3,  static:true },
  { i:'chart',   x:2,  y:3,  w:10, h:24, static:true },
  { i:'table',   x:2,  y:27, w:10, h:15, static:true }
])
</script>

<template>
  <div class="forecast-wrapper">
    <Toast />
    <GridLayout :layout="layout" :col-num="12" :row-height="8" :is-draggable="false" :is-resizable="false" :margin="[10,10]" :use-css-transforms="true">
      <GridItem v-for="item in layout" :key="item.i" :i="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h">
        <GlassCard>
          <div v-if="item.i==='filters'" class="h-full p-3">
            <ForecastFilters
            :mode="mode"                               
            :primary-options="primaryOptions"          
            :primary-id="primaryId ?? null"
            :secondary-options="secondaryOptions" 
            :secondary-id="secondaryId ?? null"
              @update:mode="handleModeChange"
              @update:primary-id="v => primaryId = (v ?? null)"
              @update:secondary-id="v => secondaryId = (v ?? null)"
              @next="handleNext">
            </ForecastFilters>
            <div class="mt-2 text-500 text-sm" v-if="loading">Lädt…</div>
          </div>

          <div v-else-if="item.i==='title'" class="h-full p-3 flex align-items-center">
            <ForecastTitle
              :kunde="mode==='kunde' ? (clients.find(c=>c.id===primaryId)?.name || '') : (clients.find(c=>c.id===secondaryId)?.name || '')"
              :pc="(() => { const id = mode==='kunde' ? secondaryId : primaryId; const pc = profitCenters.find(p=>p.id===id); return pc ? `${pc.code} — ${pc.name}` : '' })()"
            />
          </div>

          <div v-else-if="item.i==='chart'" class="h-full">
            <ForecastChart :labels="months" :ventas="sales" :budget="budget" :forecast="forecast" :orders="orders" />
          </div>

          <div v-else-if="item.i==='table'" class="h-full">
            <ForecastTable
              :months="months"
              :ventas="sales"
              :budget="budget"
              :forecast="forecast"
              @edit-forecast="({index,value}) => { const n=Number(value); forecast[index]=isNaN(n)?0:n }"
            />
          </div>
        </GlassCard>
      </GridItem>
    </GridLayout>
  </div>
</template>

<style scoped>
.forecast-wrapper{ height:100vh; width:100%; overflow:hidden; }
</style>