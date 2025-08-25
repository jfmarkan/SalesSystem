<template>
  <div class="panel">
    <div class="cols">
      <div class="col">
        <label for="best" class="lbl best">Best Case</label>
        <InputNumber
          inputId="best"
          v-model="bestCase"
          :min="-100" :max="100" :step="1"
          suffix=" %"
          placeholder="0 %"
          fluid
          class="tall best-input"
        />
      </div>
      <div class="col">
        <label for="worst" class="lbl worst">Worst Case</label>
        <InputNumber
          inputId="worst"
          v-model="worstCase"
          :min="-100" :max="100" :step="1"
          suffix=" %"
          placeholder="0 %"
          fluid
          class="tall worst-input"
        />
      </div>
    </div>

    <div class="actions">
      <Button :disabled="!canRun || loading || !canSimulate" label="Simulieren" icon="pi pi-play" @click="simulate" />
    </div>

    <div class="sep"></div>

    <div v-if="loading" class="loading">Wird berechnet…</div>
    <template v-else>
      <div v-if="summary" class="summary">
        <div class="kpi"><span class="k">Sales YTD (Basis)</span><span class="v">{{ fmt(summary.totalSalesYTD) }}</span></div>
        <div class="kpi"><span class="k">Saison-Anteil YTD</span><span class="v">{{ fmt(summary.ytdPct) }} %</span></div>
        <div class="kpi"><span class="k">Basis-Prognose (0%)</span><span class="v">{{ fmt(summary.baseForecast) }}</span></div>
        <div class="kpi"><span class="k">Best Case (gesamt)</span><span class="v good">{{ fmt(summary.totalBest) }}</span></div>
        <div class="kpi"><span class="k">Worst Case (gesamt)</span><span class="v bad">{{ fmt(summary.totalWorst) }}</span></div>
      </div>
      <div v-else class="placeholder"><em>Wähle Client & Profit Center und klicke „Simulieren“.</em></div>
    </template>

    <p v-if="error" class="err">Fehler: {{ error }}</p>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, defineExpose } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const props = defineProps({
  clientGroupNumber: { type: Number, default: null },
  profitCenterCode:  { type: Number, default: null }
})
const emit = defineEmits(['dirty-change','simulated','values-change'])

const toast = useToast()
const loading = ref(false)
const error = ref('')

/* valores */
const bestCase = ref(null)  // null para mostrar placeholder
const worstCase = ref(null)
const origBest = ref(0)
const origWorst = ref(0)

const touchedBest = ref(false)
watch(bestCase, () => { touchedBest.value = true })

const canRun = computed(() =>
  Number.isFinite(props.clientGroupNumber) && Number.isFinite(props.profitCenterCode)
)
const canSimulate = computed(() =>
  touchedBest.value && bestCase.value !== null && !Number.isNaN(Number(bestCase.value))
)

const dirty = computed(() =>
  Number(bestCase.value ?? 0) !== Number(origBest.value) ||
  Number(worstCase.value ?? 0) !== Number(origWorst.value)
)
watch(dirty, v => emit('dirty-change', v))

const summary = ref(null)
function fmt(n){ return (Number(n)||0).toLocaleString('de-DE') }

function currentFY(){ const now=new Date(); const fy = (now.getMonth()+1)<4 ? now.getFullYear()-1 : now.getFullYear(); return fy }
function nextFY(){ return currentFY() + 1 }

/* Cargar valores guardados (show) */
async function loadSavedCase(){
  if (!canRun.value) return
  loading.value = true; error.value = ''
  try{
    await ensureCsrf()
    const params = {
      client_group_number: Number(props.clientGroupNumber),
      profit_center_code:  Number(props.profitCenterCode),
      fiscal_year:         nextFY()
    }
    const { data } = await api.get('/api/budget-cases', { params, withCredentials:true })
    const row = data?.data || null
    if (row) {
      bestCase.value  = Number(row.best_case ?? 0)
      worstCase.value = Number(row.worst_case ?? 0)
      origBest.value  = bestCase.value
      origWorst.value = worstCase.value
      emit('dirty-change', false)
      emit('values-change', { best_case: bestCase.value, worst_case: worstCase.value })
    } else {
      // no guardado aún
      bestCase.value = null
      worstCase.value = null
      origBest.value = 0
      origWorst.value = 0
      emit('dirty-change', false)
      emit('values-change', { best_case: 0, worst_case: 0 })
    }
    summary.value = null // limpiar resultados previos de simulación
  } catch(e){
    // si falla el show, dejamos inputs en null
    bestCase.value = null; worstCase.value = null
    origBest.value = 0;    origWorst.value = 0
  } finally { loading.value = false }
}

/* Simulación */
async function simulate(){
  if (!canRun.value || !canSimulate.value) return
  loading.value = true; error.value = ''
  try{
    await ensureCsrf()
    const payload = {
      client_group_number: Number(props.clientGroupNumber),
      profit_center_code:  Number(props.profitCenterCode),
      best_case: Number(bestCase.value ?? 0),
      worst_case: Number(worstCase.value ?? 0),
      compare_current: true
    }
    const { data } = await api.post('/api/budget-cases/simulate', payload, { withCredentials:true })
    const b = data?.basis || {}
    summary.value = {
      totalSalesYTD: b.totalSalesYTD ?? 0,
      ytdPct:        b.ytdPct ?? 0,
      baseForecast:  b.baseForecast ?? 0,
      totalBest:     b.totalBest ?? 0,
      totalWorst:    b.totalWorst ?? 0
    }
    emit('dirty-change', dirty.value)
    emit('simulated', { seriesTarget: data?.seriesTarget || [] })
  } catch(e){
    summary.value = null
    error.value = e?.response?.data?.message || 'Fehler bei der Vorschau'
    toast.add({ severity:'error', summary:'Fehler', detail:error.value, life:3000 })
  } finally { loading.value = false }
}

/* Resets */
function toNumSafe(v){ const n=Number(v); return Number.isFinite(n)?n:0 }
function getValues(){ return { best_case: toNumSafe(bestCase.value), worst_case: toNumSafe(worstCase.value) } }
function markSaved(){ origBest.value=toNumSafe(bestCase.value); origWorst.value=toNumSafe(worstCase.value); emit('dirty-change', false) }
function hardReset(){
  bestCase.value=null; worstCase.value=null; origBest.value=0; origWorst.value=0
  summary.value=null; error.value=''
  emit('dirty-change', false)
  emit('values-change', { best_case:0, worst_case:0 })
}

watch([bestCase, worstCase], ([b,w]) => {
  emit('dirty-change', true)
  emit('values-change', { best_case: toNumSafe(b), worst_case: toNumSafe(w) })
})

onMounted(loadSavedCase)
watch(() => [props.clientGroupNumber, props.profitCenterCode], loadSavedCase)

defineExpose({ getValues, markSaved, hardReset })
</script>

<style scoped>
.panel{ display:flex; flex-direction:column; gap:12px; height:100%; }
.cols{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
.col{ display:flex; flex-direction:column; gap:6px; }
.tall :deep(input){ height: 56px; font-size: 1.05rem; }
.lbl.best{ color:#16a34a; font-weight:600; }
.lbl.worst{ color:#ef4444; font-weight:600; }
.best-input :deep(.p-inputtext){ border-color:#16a34a !important; }
.worst-input :deep(.p-inputtext){ border-color:#ef4444 !important; }
.actions{ display:flex; justify-content:flex-end; }
.sep{ height:1px; background: rgba(0,0,0,.08); margin:4px 0; }
.loading{ color:#475569; }
.summary{ display:grid; gap:8px; }
.kpi{ display:flex; align-items:center; justify-content:space-between; padding:6px 8px; background:rgba(255,255,255,.3); border:solid 1px ;border-radius:8px; }
.kpi .k{ color:#334155; }
.kpi .v{ font-weight:700; }
.kpi .v.good{ color:#16a34a; }
.kpi .v.bad{ color:#dc2626; }
.placeholder{ color:#6b7280; }
.err{ color:#dc2626; }
</style>
