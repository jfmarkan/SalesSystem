<template>
  <div class="dash-wrapper">
    <grid-layout
      v-model:layout="layout"
      :col-num="12"
      :row-height="30"
      :is-draggable="isEditable"
      :is-resizable="isEditable"
      :margin="[10,10]"
      :use-css-transforms="true"
    >
      <grid-item v-for="item in layout" :key="item.i" :x="item.x" :y="item.y" :w="item.w" :h="item.h" :i="item.i">
        <GlassCard :title="getTitle(item)">
          <template #header-extra v-if="item.type==='chart' || item.type==='teamTable'">
            <div class="unit-toggle">
              <button :class="['u-btn', unit==='VKEH' && 'active']" @click="changeUnit('VKEH')">VK-EH</button>
              <button :class="['u-btn', unit==='M3'   && 'active']" @click="changeUnit('M3')">m³</button>
              <button :class="['u-btn', unit==='EUR'  && 'active']" @click="changeUnit('EUR')">€</button>
            </div>
          </template>

          <component :is="getWidgetComponent(item.type)" v-bind="getPropsForType(item)" class="grid-widget" />
        </GlassCard>
      </grid-item>
    </grid-layout>
    <div v-if="errorMsg" class="err">{{ errorMsg }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { GridLayout, GridItem } from 'vue3-grid-layout'
import api from '@/plugins/axios'

import GlassCard from '@/components/ui/GlassCard.vue'
import KpiCard from '@/components/widgets/KpiCard.vue'
import ChartCard from '@/components/widgets/ChartCard.vue'
import SalesRepsTable from '@/components/widgets/sales/SalesRepsTable.vue'
import RepsListCard from '@/components/widgets/sales/RepsListCard.vue'

const isEditable = ref(false)
const unit = ref('EUR')
const loading = ref(false)
const errorMsg = ref('')

const rows = ref([])       // team members
const teamKpis = ref(null) // team aggregate KPIs
const selectedId = ref('ALL')

// Derived
const options = computed(() => [{ label:'Team gesamt', value:'ALL' }]
  .concat((rows.value||[]).map(r => ({ label:r.name, value:String(r.user_id ?? r.id ?? r.name) })) )
)

function changeUnit(next){ if (unit.value!==next) unit.value = next }

// Fetch data
async function loadTeam(){
  loading.value = true
  try{
    const [membersRes, kpiRes] = await Promise.all([
      api.get('/api/manager/team/members'),
      api.get('/api/manager/team/overview'),
    ])
    rows.value = membersRes.data || []
    teamKpis.value = kpiRes.data || null
  }catch(e){
    console.error(e); errorMsg.value='Fehler beim Laden.'
  }finally{ loading.value=false }
}
onMounted(loadTeam)

// Layout
const layout = ref([
  { i:'rep', x:0, y:0, w:3, h:20, type:'reps' },
  { i:'k0',  x:3, y:0, w:3, h:5,  type:'kpi', kpiId:'umsatz_eur' },
  { i:'k1',  x:6, y:0, w:3, h:5,  type:'kpi', kpiId:'ist_vs_budget' },
  { i:'k2',  x:9, y:0, w:3, h:5,  type:'kpi', kpiId:'ist_vs_prognose' },
  { i:'ch',  x:3, y:5, w:9, h:8,  type:'chart' },
  { i:'tb',  x:3, y:13,w:9, h:7,  type:'teamTable' },
])

// KPI map
const kpisById = computed(()=>({
  umsatz_eur:         { label:'Gesamtumsatz', value: teamKpis.value?.totals?.sales?.euros ?? 0, unit:'EUR' },
  ist_vs_budget:      { label:'Ist vs Budget', value: teamKpis.value?.achievement?.vs_budget_pct ?? 0, unit:'%' },
  ist_vs_prognose:    { label:'Ist vs Forecast', value: teamKpis.value?.achievement?.vs_forecast_pct ?? 0, unit:'%' },
}))

// Trend for chart
const labels = computed(() => (teamKpis.value?.trend || []).map(p => p.period?.slice(0,7)))
const series = computed(() => [{ label:'Umsatz (€)', color:'#2563eb', data:(teamKpis.value?.trend || []).map(p=>p.euros||0) }])

// Titles
function getTitle(item){
  if (item.type==='reps') return 'Mitarbeiter'
  if (item.type==='kpi')  return kpisById.value[item.kpiId]?.label || 'KPI'
  if (item.type==='chart')return 'Tendenz (12 Monate)'
  if (item.type==='teamTable') return 'Sales-Team (YTD)'
  return 'Widget'
}

// Registry
function getWidgetComponent(type){
  return { reps: RepsListCard, kpi:KpiCard, chart:ChartCard, teamTable:SalesRepsTable }[type] || null
}

function getPropsForType(item){
  if (item.type==='reps') return {
    options: options.value,
    selected: selectedId.value,
    onSelect: (val)=> selectedId.value = val
  }
  if (item.type==='kpi')   return { modelValue:item.kpiId, kpis:kpisById.value, unit:unit.value }
  if (item.type==='chart') return { labels:labels.value, series:series.value, unit:unit.value }
  if (item.type==='teamTable') return { rows:rows.value, unit:unit.value }
  return {}
}
</script>

<style scoped>
.dash-wrapper{ width:100%; }
.grid-widget{ height:100%; width:100%; }
.err{ margin-top:8px; padding:6px 10px; border-radius:8px; background: rgba(239,68,68,.08); color:#7f1d1d; border:1px solid rgba(239,68,68,.35); }

.unit-toggle{ display:flex; gap:6px; background: rgba(255,255,255,.35); border:1px solid rgba(0,0,0,.08); border-radius:8px; padding:2px; }
.u-btn{ border:0; background:transparent; padding:.25rem .5rem; font-size:.8rem; cursor:pointer; border-radius:6px; }
.u-btn.active{ background: rgba(31,86,115,.8); color:#fff; font-weight:700; }
</style>