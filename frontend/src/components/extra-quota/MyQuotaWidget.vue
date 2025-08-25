<template>
  <div>
    <h3 class="section-title">Meine zugewiesene Extra-Quote</h3>

    <div class="controls">
      <span class="lbl">Geschäftsjahr</span>
      <InputNumber
        v-model="localFY"
        :min="2000"
        :max="2100"
        showButtons
        buttonLayout="horizontal"
        inputId="fy"
        @update:modelValue="fetch"
      />
    </div>

    <div v-if="loading" class="local-loader">
      <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
      <div class="caption">Wird geladen…</div>
    </div>

    <div v-else>
      <div class="metric-card">
        <div class="metric-label">Gesamt (Einheiten)</div>
        <div class="metric-value">{{ total.toLocaleString('de-DE') }}</div>
      </div>

      <DataTable
        v-if="rows.length"
        :value="rows"
        size="small"
        dataKey="id"
        :rowHover="true"
        class="p-datatable-sm dark-table"
      >
        <Column field="profit_center_code" header="Profitcenter"></Column>
        <Column header="Volumen">
          <template #body="{ data }">{{ Number(data.volume || 0).toLocaleString('de-DE') }}</template>
        </Column>
        <Column field="is_published" header="Veröffentlicht">
          <template #body="{ data }">{{ data.is_published ? 'Ja' : 'Nein' }}</template>
        </Column>
        <Column field="assignment_date" header="Datum"></Column>
      </DataTable>

      <div v-else class="empty">Keine Zuweisungen gefunden.</div>
    </div>
  </div>
</template>

<script setup>
// Code in English; UI German.
import { ref, watch, onMounted } from 'vue'
import api from '@/plugins/axios'
import { useToast } from 'primevue/usetoast'
import InputNumber from 'primevue/inputnumber'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const props = defineProps({
  userId: { type: [Number, String], default: null },
  fiscalYear: { type: Number, default: new Date().getFullYear() }
})

const toast = useToast()
const localFY = ref(props.fiscalYear)
const rows = ref([])
const total = ref(0)
const loading = ref(false)

async function fetch() {
  if (!props.userId) return
  loading.value = true
  try {
    const { data } = await api.get('/api/extra-quota/assignments', {
      params: { fiscal_year: localFY.value, user_id: props.userId }
    })
    rows.value = (data || []).filter(r => r.is_published)
    total.value = rows.value.reduce((s, r) => s + Number(r.volume || 0), 0)
  } catch {
    rows.value = []
    total.value = 0
    toast.add({ severity: 'error', summary: 'Fehler', detail: 'Zuweisungen konnten nicht geladen werden', life: 2000 })
  } finally { loading.value = false }
}

watch(() => [props.userId, props.fiscalYear], () => { localFY.value = props.fiscalYear; fetch() }, { immediate: true })
onMounted(fetch)
</script>

<style scoped>
.section-title { margin: 4px 0 10px; }
.controls { display:flex; gap:10px; align-items:center; margin-bottom:10px; }
.lbl { color:#cbd5e1; }

.metric-card {
  border: 1px solid rgba(255,255,255,0.08);
  background: rgba(255,255,255,0.03);
  border-radius: 10px; padding: 10px 12px; margin-bottom: 10px;
}
.metric-label { color:#93c5fd; font-size:12px; }
.metric-value { font-weight:700; font-size:22px; color:#e5e7eb; }

.dark-table :deep(.p-datatable-wrapper) { background: transparent; }
.dark-table :deep(.p-datatable) { background: transparent; color: #e5e7eb; }
.dark-table :deep(.p-datatable-thead > tr > th) { background: rgba(255,255,255,0.04); color:#e5e7eb; border: 1px solid rgba(255,255,255,0.08); }
.dark-table :deep(.p-datatable-tbody > tr > td) { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); }

/* Loader (copied from your snippet) */
.local-loader { position: fixed; inset: 0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; }
.dots { display:flex; gap:10px; align-items:center; justify-content:center; }
.dot { width:10px; height:10px; border-radius:50%; opacity:0.9; animation:bounce 1s infinite ease-in-out; box-shadow:0 2px 6px rgba(0,0,0,0.25); }
.dot.g{ background:#22c55e; animation-delay:0s; } .dot.r{ background:#ef4444; animation-delay:.15s; } .dot.b{ background:#3b82f6; animation-delay:.3s; }
@keyframes bounce { 0%,80%,100%{ transform:translateY(0) scale(1); opacity:.8 } 40%{ transform:translateY(-8px) scale(1.05); opacity:1 } }
.caption { font-size:.9rem; color:#e5e7eb; opacity:.9; }
.empty { padding: 12px; text-align:center; color:#e5e7eb; opacity:.9; }
</style>