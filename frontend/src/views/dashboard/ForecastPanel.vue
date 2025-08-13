<script setup>
/* Uses your axios plugin + Sanctum CSRF bootstrap */
import { ref, computed, watch, onMounted } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import Button from 'primevue/button'
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

const kunden = ref([])
const profitCenters = ref([])
const mapKundeToPC = ref({})
const mapPCToKunde = ref({})

const mode = ref('kunde')
const primaryId = ref(null)
const secondaryId = ref(null)
const loading = ref(false)

function genMonths(n){
  const out=[], base=new Date(); base.setDate(1)
  for(let i=0;i<n;i++){ const d=new Date(base.getFullYear(), base.getMonth()+i, 1)
    out.push(`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`) }
  return out
}
const months = ref(genMonths(18))
const ventas = ref(Array(18).fill(0))
const budget = ref(Array(18).fill(0))
const forecast = ref(Array(18).fill(0))
const orders = ref(Array(18).fill(0))

const primaryOptions = computed(() =>
  mode.value==='kunde'
    ? kunden.value.map(c=>({ label:c.name, value:c.id }))
    : profitCenters.value.map(p=>({ label:`${p.code} — ${p.name}`, value:p.id }))
)
const secondaryOptions = computed(()=>{
  if(primaryId.value==null) return []
  if(mode.value==='kunde'){
    const ids = mapKundeToPC.value[primaryId.value]||[]
    return ids.map(id=>{ const p=profitCenters.value.find(x=>x.id===id); return p&&{label:`${p.code} — ${p.name}`, value:p.id} }).filter(Boolean)
  } else {
    const ids = mapPCToKunde.value[primaryId.value]||[]
    return ids.map(id=>{ const c=kunden.value.find(x=>x.id===id); return c&&{label:c.name, value:c.id} }).filter(Boolean)
  }
})

/* Nombres seleccionados para el título */
const selectedClienteName = computed(()=>{
  return mode.value==='kunde'
    ? (kunden.value.find(c=>c.id===primaryId.value)?.name || '')
    : (kunden.value.find(c=>c.id===secondaryId.value)?.name || '')
})
const selectedPCName = computed(()=>{
  const pcId = mode.value==='kunde' ? secondaryId.value : primaryId.value
  const pc = profitCenters.value.find(p=>p.id===pcId)
  return pc ? `${pc.code} — ${pc.name}` : ''
})

async function loadMaster(){
  try{
    await ensureCsrf()
    const [{data:c},{data:p},{data:m}] = await Promise.all([
      api.get(`${API}/me/clients`),
      api.get(`${API}/me/profit-centers`),
      api.get(`${API}/me/assignments`)
    ])
    kunden.value = c
    profitCenters.value = p
    mapKundeToPC.value = m.clientToPc || {}
    mapPCToKunde.value = m.pcToClient || {}
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Stammdaten nicht verfügbar', life:2500 })
  }
}

async function loadSeries(){
  if(primaryId.value==null || secondaryId.value==null) return
  loading.value = true
  try{
    await ensureCsrf()
    const kundeId = mode.value==='kunde' ? primaryId.value : secondaryId.value
    const pcId    = mode.value==='kunde' ? secondaryId.value : primaryId.value
    const { data } = await api.get(`${API}/forecast/series`, { params: { kundeId, pcId } })
    months.value   = data.months   ?? months.value
    ventas.value   = data.ventas   ?? ventas.value
    budget.value   = data.budget   ?? budget.value
    forecast.value = data.forecast ?? forecast.value
    orders.value   = data.orders   ?? orders.value
  } finally { loading.value = false }
}

async function saveForecast(){
  try{
    await ensureCsrf()
    const kundeId = mode.value==='kunde' ? primaryId.value : secondaryId.value
    const pcId    = mode.value==='kunde' ? secondaryId.value : primaryId.value
    await api.put(`${API}/forecast/series`, { kundeId, pcId, months: months.value, forecast: forecast.value })
    toast.add({ severity:'success', summary:'Gespeichert', detail:'Forecast aktualisiert', life:2000 })
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Speichern fehlgeschlagen', life:2500 })
  }
}
function resetForecastToBudget(){ forecast.value = budget.value.slice() }

watch([mode, primaryId], ()=>{
  secondaryId.value = secondaryOptions.value[0]?.value ?? null
  loadSeries()
})
watch(secondaryId, loadSeries)

onMounted(async ()=>{
  await loadMaster()
  primaryId.value = primaryOptions.value[0]?.value ?? null
  secondaryId.value = secondaryOptions.value[0]?.value ?? null
  await loadSeries()
})

/* Grid con título */
const layout = ref([
  { i:'filters', x:0,  y:0,  w:2,  h:27, static:true },
  { i:'title',   x:2,  y:0,  w:10, h:3,  static:true },
  { i:'chart',   x:2,  y:3,  w:7, h:24,  static:true },
  { i:'chart',   x:9,  y:3,  w:3, h:24,  static:true },
  { i:'table',   x:2,  y:27, w:10, h:15, static:true }
])
</script>

<template>
  <div class="forecast-wrapper">
    <Toast />
    <GridLayout
      :layout="layout"
      :col-num="12"
      :row-height="8"
      :is-draggable="false"
      :is-resizable="false"
      :margin="[12,12]"
      :use-css-transforms="true"
    >
      <GridItem v-for="item in layout" :key="item.i" :i="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h">
        <GlassCard>
          <div v-if="item.i==='filters'" class="h-full p-3">
            <ForecastFilters
              :mode="mode"
              :primary-options="primaryOptions"
              :primary-id="primaryId"
              :secondary-options="secondaryOptions"
              :secondary-id="secondaryId"
              @update:mode="v=>mode=v"
              @update:primary-id="v=>primaryId=v"
              @update:secondary-id="v=>secondaryId=v"
              @next="() => { if (secondaryOptions.length) { const idx = secondaryOptions.findIndex(o=>o.value===secondaryId); const n=(idx+1)%secondaryOptions.length; secondaryId = secondaryOptions[n].value } }"
            />
            <div class="mt-3 text-500 text-sm" v-if="loading">Lädt…</div>
          </div>

          <div v-else-if="item.i==='title'" class="h-full p-3 flex align-items-center">
            <!-- Ajusta las props si tu ComponentTitle tiene API distinta -->
            <ForecastTitle :kunde="selectedClienteName" :pc="selectedPCName" />
          </div>

          <div v-else-if="item.i==='chart'" class="h-full">
            <ForecastChart :labels="months" :ventas="ventas" :budget="budget" :forecast="forecast" :orders="orders" />
          </div>

          <div v-else-if="item.i==='table'" class="h-full">
            <ForecastTable
              :months="months"
              :ventas="ventas"
              :budget="budget"
              :forecast="forecast"
              @edit-forecast="({index,value}) => { const n=Number(value); forecast[index]=isNaN(n)?0:n }"
            />
            <div class="mt-3 flex gap-2 justify-content-end">
              <Button label="Zurücksetzen (Budget)" icon="pi pi-refresh" severity="secondary" @click="resetForecastToBudget" />
              <Button label="Speichern" icon="pi pi-save" @click="saveForecast" />
            </div>
          </div>
        </GlassCard>
      </GridItem>
    </GridLayout>
  </div>
</template>

<style scoped>
.forecast-wrapper{ 
  height: 100vh;
  width: 100%; 
  overflow: hidden; 
}
</style>
