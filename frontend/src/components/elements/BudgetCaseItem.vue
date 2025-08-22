<template>
  <div class="panel">
    <!-- Prozentfelder -->
    <div class="cols">
      <div class="col">
        <label for="best" class="lbl best">Best Case</label>
        <InputNumber
          inputId="best"
          v-model="bestCase"
          :min="-100" :max="100" :step="1"
          suffix=" %"
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
          fluid
          class="tall worst-input"
        />
      </div>
    </div>

    <!-- Aktionen -->
    <div class="actions">
      <Button :disabled="!canRun || loading" label="Simulieren" icon="pi pi-play" @click="simulate" />
    </div>

    <div class="sep"></div>

    <!-- Ergebnis/Status -->
    <div v-if="loading" class="loading">Wird berechnet…</div>
    <template v-else>
      <div v-if="summary" class="summary">
        <div class="kpi"><span class="k">Sales YTD (Basis)</span><span class="v">{{ fmt(summary.totalSalesYTD) }}</span></div>
        <div class="kpi"><span class="k">Saison-Anteil YTD</span><span class="v">{{ fmt(summary.ytdPct) }} %</span></div>
        <div class="kpi"><span class="k">Basis-Prognose (0%)</span><span class="v">{{ fmt(summary.baseForecast) }}</span></div>
        <div class="kpi"><span class="k">Best Case (gesamt)</span><span class="v good">{{ fmt(summary.totalBest) }}</span></div>
        <div class="kpi"><span class="k">Worst Case (gesamt)</span><span class="v bad">{{ fmt(summary.totalWorst) }}</span></div>
      </div>
      <div v-else class="placeholder">
        <em>Wähle Client & Profit Center, gib die Prozentwerte ein und klicke „Simulieren“.</em>
      </div>
    </template>

    <p v-if="error" class="err">Fehler: {{ error }}</p>
  </div>
</template>

<script setup>
import { ref, computed, watch, defineExpose } from 'vue'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const props = defineProps({
  clientId: { type: [Number, String], default: null },
  profitCenterId: { type: [Number, String], default: null } // CPC id
})
const emit = defineEmits(['dirty-change','simulated'])

const toast = useToast()
const loading = ref(false)
const error = ref('')

const bestCase = ref(0)
const worstCase = ref(0)
const origBest = ref(0)
const origWorst = ref(0)

const canRun = computed(() => !!props.profitCenterId)
const dirty = computed(() => Number(bestCase.value)!==Number(origBest.value) || Number(worstCase.value)!==Number(origWorst.value))
watch(dirty, v => emit('dirty-change', v))

const summary = ref(null)
function fmt(n){ return (Number(n)||0).toLocaleString('de-DE') }

/* Prefill only if your /api/budget-cases?client_profit_center_id=... returns last saved pct.
   If you don't have that endpoint, just set zeros and skip this call. */
async function loadExistingCase(){
  if (!canRun.value) return
  error.value = ''
  try{
    await ensureCsrf()
    const { data } = await api.get('/api/budget-cases', {
      params: { client_profit_center_id: Number(props.profitCenterId) },
      withCredentials: true
    })
    const row = data?.data || null
    bestCase.value  = Number(row?.best_case  ?? 0)
    worstCase.value = Number(row?.worst_case ?? 0)
    origBest.value  = bestCase.value
    origWorst.value = worstCase.value
  } catch {
    bestCase.value = 0; worstCase.value = 0
    origBest.value = 0; origWorst.value = 0
  } finally {
    emit('dirty-change', dirty.value)
  }
}

/* Save ONLY CPC + percentages */
async function save(){
  if (!canRun.value) return
  loading.value = true; error.value = ''
  try{
    await ensureCsrf()
    await api.post('/api/budget-cases', {
      client_profit_center_id: Number(props.profitCenterId),
      best_case: Number(bestCase.value),
      worst_case: Number(worstCase.value)
    }, { withCredentials: true })
    origBest.value  = Number(bestCase.value)
    origWorst.value = Number(worstCase.value)
    emit('dirty-change', false)
  } catch(e){
    error.value = e?.response?.data?.message || 'Speichern fehlgeschlagen'
    toast.add({ severity:'error', summary:'Fehler', detail:error.value, life:3000 })
    throw e
  } finally { loading.value = false }
}

/* Simulate ONLY from CPC + percentages (controller computes YTD + seasonality) */
async function simulate(){
  if (!canRun.value) return
  loading.value = true; error.value = ''
  try{
    await ensureCsrf()
    const { data } = await api.post('/api/budget-cases/simulate', {
      client_profit_center_id: Number(props.profitCenterId),
      best_case: Number(bestCase.value),
      worst_case: Number(worstCase.value),
      compare_current: true
    }, { withCredentials: true })

    const b = data?.basis || {}
    summary.value = {
      totalSalesYTD: b.totalSalesYTD ?? 0,
      ytdPct:        b.ytdPct ?? 0,
      baseForecast:  b.baseForecast ?? 0,
      totalBest:     b.totalBest ?? 0,
      totalWorst:    b.totalWorst ?? 0
    }

    const seriesTarget = Array.isArray(data?.seriesTarget) ? data.seriesTarget : []
    const bestMonthly  = seriesTarget.map(r => Number(r?.best || 0))
    const worstMonthly = seriesTarget.map(r => Number(r?.worst || 0))
    emit('simulated', { seriesTarget, bestMonthly, worstMonthly })
  } catch(e){
    summary.value = null
    error.value = e?.response?.data?.message || 'Fehler bei der Vorschau'
  } finally { loading.value = false }
}

function reset(){
  bestCase.value = origBest.value
  worstCase.value = origWorst.value
  emit('dirty-change', false)
}

watch(() => props.profitCenterId, async () => {
  summary.value = null
  await loadExistingCase() // optional
}, { immediate:true })

defineExpose({ save, reset })
</script>

<style scoped>
.panel{ display:flex; flex-direction:column; gap:12px; height:100%; }

/* Inputs side by side */
.cols{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
.col{ display:flex; flex-direction:column; gap:6px; }

/* Double height input */
.tall :deep(input){ height: 56px; font-size: 1.05rem; }

/* Color-coded labels */
.lbl.best{ color:#16a34a; font-weight:600; }
.lbl.worst{ color:#ef4444; font-weight:600; }

/* Color-coded borders for inputs */
.best-input :deep(.p-inputtext){
  border-color:#16a34a !important;
  box-shadow: 0 0 0 0 rgba(22,163,74,0);
}
.best-input :deep(.p-inputtext:focus){
  border-color:#16a34a !important;
  box-shadow: 0 0 0 3px rgba(22,163,74,.15) !important;
}
.worst-input :deep(.p-inputtext){
  border-color:#ef4444 !important;
  box-shadow: 0 0 0 0 rgba(239,68,68,0);
}
.worst-input :deep(.p-inputtext:focus){
  border-color:#ef4444 !important;
  box-shadow: 0 0 0 3px rgba(239,68,68,.15) !important;
}

/* Buttons to the right */
.actions{ display:flex; justify-content:flex-end; }

/* Rest */
.sep{ height:1px; background: rgba(0,0,0,.08); margin:4px 0; }
.loading{ color:#475569; }
.summary{ display:grid; gap:8px; }
.kpi{
  display:flex; align-items:center; justify-content:space-between;
  padding:6px 8px; background:#f8fafc; border-radius:8px;
  border:1px solid rgba(0,0,0,.06);
}
.kpi .k{ color:#334155; }
.kpi .v{ font-weight:700; }
.kpi .v.good{ color:#16a34a; }
.kpi .v.bad{ color:#dc2626; }
.placeholder{ color:#6b7280; }
.err{ color:#dc2626; }
</style>