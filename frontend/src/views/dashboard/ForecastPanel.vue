<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import ForecastFilters from '@/components/filters/ComponentFilter.vue'
import ForecastTitle from '@/components/titles/ComponentTitle.vue'
import ForecastChart from '@/components/charts/LineChart.vue'
import ForecastTable from '@/components/tables/ComponentTable.vue'

const userName = ref('Sales Rep')

const clientes = ref([
  { id: 1, name: 'Cliente A' }, { id: 2, name: 'Cliente B' }, { id: 3, name: 'Cliente C' }
])
const profitCenters = ref([
  { id: 10, code: 'PC10', name: 'PC Norte' },
  { id: 20, code: 'PC20', name: 'PC Sur' },
  { id: 30, code: 'PC30', name: 'PC Centro' }
])
const mapClienteToPC = { 1: [10,30], 2: [20], 3: [10,20,30] }
const mapPCToCliente = { 10: [1,3], 20: [2,3], 30: [1,3] }

const mode = ref('cliente')     // 'cliente' | 'pc'
const primaryId = ref(null)     // dropdown
const secondaryId = ref(null)   // lista asociada
const secondaryIndex = ref(0)

function genMonths(n){
  const out=[], base=new Date(); base.setDate(1)
  for(let i=0;i<n;i++){ const d=new Date(base.getFullYear(), base.getMonth()+i, 1)
    out.push(`${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`) }
  return out
}
const months = ref(genMonths(18))

const ventas   = ref(Array(18).fill(0))
const budget   = ref(Array(18).fill(0))
const forecast = ref(Array(18).fill(0))
const orders   = ref(Array(18).fill(0))

function loadSeriesMock(){
  const seed = (primaryId.value||0)*31 + (secondaryId.value||0)*17 + (mode.value==='cliente'?5:11)
  const rng = i => { const x=Math.sin(seed+i)*10000; return x-Math.floor(x) }
  for(let i=0;i<18;i++){
    const b=Math.round(80+rng(i)*120)
    const v=Math.max(0,Math.round(b*(0.8+rng(i+1)*0.5)))
    const f=Math.round(b*(0.85+rng(i+2)*0.4))
    const o=Math.round(b*(0.6+rng(i+3)*0.6))
    budget.value[i]=b; ventas.value[i]=v; forecast.value[i]=f; orders.value[i]=o
  }
}

const primaryOptions = computed(() =>
  mode.value==='cliente'
    ? clientes.value.map(c=>({label:c.name, value:c.id}))
    : profitCenters.value.map(p=>({label:`${p.code} — ${p.name}`, value:p.id}))
)
const secondaryOptions = computed(()=>{
  if(primaryId.value==null) return []
  if(mode.value==='cliente'){
    const ids = mapClienteToPC[primaryId.value]||[]
    return ids.map(id=>{ const p=profitCenters.value.find(x=>x.id===id); return p&&{label:`${p.code} — ${p.name}`, value:p.id} }).filter(Boolean)
  } else {
    const ids = mapPCToCliente[primaryId.value]||[]
    return ids.map(id=>{ const c=clientes.value.find(x=>x.id===id); return c&&{label:c.name, value:c.id} }).filter(Boolean)
  }
})

watch([mode, primaryId], ()=>{
  secondaryIndex.value=0
  secondaryId.value = secondaryOptions.value[0]?.value ?? null
  if(primaryId.value!=null && secondaryId.value!=null) loadSeriesMock()
})
watch(secondaryId, ()=>{
  if(primaryId.value!=null && secondaryId.value!=null) loadSeriesMock()
})

function goNext(){
  if(!secondaryOptions.value.length) return
  secondaryIndex.value = (secondaryIndex.value + 1) % secondaryOptions.value.length
  secondaryId.value = secondaryOptions.value[secondaryIndex.value].value
}

const selectedClienteName = computed(()=>{
  if(mode.value==='cliente'){
    return clientes.value.find(c=>c.id===primaryId.value)?.name || ''
  } else {
    return clientes.value.find(c=>c.id===secondaryId.value)?.name || ''
  }
})
const selectedPCName = computed(()=>{
  if(mode.value==='cliente'){
    const pc = profitCenters.value.find(p=>p.id===secondaryId.value)
    return pc ? `${pc.code} — ${pc.name}` : ''
  } else {
    const pc = profitCenters.value.find(p=>p.id===primaryId.value)
    return pc ? `${pc.code} — ${pc.name}` : ''
  }
})

function updateForecastAt({ index, value }){
  const n = Number(value)
  forecast.value[index] = isNaN(n) ? 0 : n
}

/* Init */
onMounted(()=>{
  primaryId.value = primaryOptions.value[0]?.value ?? null
  secondaryId.value = secondaryOptions.value[0]?.value ?? null
  if(primaryId.value!=null && secondaryId.value!=null) loadSeriesMock()
})

/* Grid estático: 12 columnas */
const layout = ref([
  { i:'filters', x:0,  y:0, w:2,  h:27, static:true },
  { i:'title',   x:2,  y:0, w:10, h:3,  static:true },
  { i:'chart',   x:2,  y:3, w:10, h:24,  static:true },
  { i:'table',   x:2,  y:27, w:10, h:12, static:true }
])

/* Forzar re-render del chart cuando cambian filtros para respuesta instantánea */
const chartKey = computed(() => `${mode.value}:${primaryId.value ?? ''}:${secondaryId.value ?? ''}`)
</script>

<template>
  <div class="forecast-wrapper">
    <GridLayout
      :layout="layout"
      :col-num="12"
      :row-height="8"
      :is-draggable="false"
      :is-resizable="false"
      :margin="[12,12]"
      :use-css-transforms="true"
      style="min-height: calc(100vh - 140px);"
    >
      <GridItem v-for="item in layout" :key="item.i" :i="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h">
        <div v-if="item.i==='filters'" class="glass-card h-full">
          <ForecastFilters
            :mode="mode"
            :primary-options="primaryOptions"
            :primary-id="primaryId"
            :secondary-options="secondaryOptions"
            :secondary-id="secondaryId"
            @update:mode="v=>mode=v"
            @update:primary-id="v=>primaryId=v"
            @update:secondary-id="v=>secondaryId=v"
            @next="goNext"
          />
        </div>

        <div v-else-if="item.i==='title'" class="glass-card h-full p-3 flex align-items-center">
          <ForecastTitle :user="userName" :cliente="selectedClienteName" :pc="selectedPCName" />
        </div>

        <div v-else-if="item.i==='chart'" class="glass-card h-full">
          <ForecastChart
            :key="chartKey"
            :labels="months"
            :ventas="ventas"
            :budget="budget"
            :forecast="forecast"
            :orders="orders"
          />
        </div>

        <div v-else-if="item.i==='table'" class="glass-card h-full">
          <ForecastTable
            :months="months"
            :ventas="ventas"
            :budget="budget"
            :forecast="forecast"
            @edit-forecast="updateForecastAt"
          />
        </div>
      </GridItem>
    </GridLayout>
  </div>
</template>

<style scoped>
.forecast-wrapper{
  width: calc(100vw - 70px); /* ancho total menos barra */
  overflow: hidden;
}
.h-full{ height: 100%; }

.glass-card{
  background: rgba(0,0,0,0.4);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  box-shadow: 0 2px 4px 0 rgba(0,0,0,0.4);
  border-radius: 10px;
  padding: 12px;
  height: 100%;
}
</style>
