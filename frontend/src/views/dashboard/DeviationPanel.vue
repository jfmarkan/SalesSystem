<script setup>
/* Accordion of open deviations for a Profit Center (no filters).
   UI: German; comments: English; uses your axios plugin + Sanctum CSRF. */
import { ref, onMounted } from 'vue'
import Accordion from 'primevue/accordion'
import AccordionTab from 'primevue/accordiontab'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Tag from 'primevue/tag'

import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const props = defineProps({
  pcId: { type: [Number, String], required: true },   // current Profit Center id
  apiPrefix: { type: String, default: '/api' }        // adjust if no /api prefix
})

const loading = ref(false)
/* Expected row shape from API:
   { id, month:'YYYY-MM', type:'ventas'|'forecast', venta, budget, forecast, note } */
const rows = ref([])

/* Load open deviations for the given Profit Center */
async function loadOpen() {
  loading.value = true
  try {
    await ensureCsrf()
    const { data } = await api.get(`${props.apiPrefix}/deviations/open`, { params: { pcId: props.pcId } })
    rows.value = (data || []).map(d => ({
      ...d,
      _note: d.note || '',
      _forecast: Number.isFinite(+d.forecast) ? +d.forecast : 0
    }))
  } finally { loading.value = false }
}

/* Percent helper */
function pct(num, den) {
  const n = Number(num), d = Number(den)
  if (!d) return '0%'
  return Math.round((n / d) * 100) + '%'
}

/* Save a single deviation changes (note + forecast) */
async function saveRow(row) {
  await ensureCsrf()
  await api.put(`${props.apiPrefix}/deviations/${row.id}`, {
    note: row._note,
    forecast: row._forecast
  })
  row.note = row._note
  row.forecast = row._forecast
}

onMounted(loadOpen)
</script>

<template>
  <div class="deviation-panel">
    <div class="panel-header">
      <h3 class="m-0">Offene Abweichungen</h3>
      <Button label="Aktualisieren" icon="pi pi-refresh" size="small" @click="loadOpen" :disabled="loading" />
    </div>

    <div v-if="loading" class="loading">Wird geladen…</div>
    <div v-else-if="!rows.length" class="empty">Keine offenen Abweichungen im Profit Center.</div>

    <Accordion v-else multiple :activeIndex="[0]">
      <AccordionTab v-for="row in rows" :key="row.id">
        <template #header>
          <div class="tab-header">
            <span :class="['marker', row.type === 'ventas' ? 'verkauf' : 'prognose']"></span>
            <div class="title">
              <div class="line1">
                <strong>{{ row.month }}</strong>
                <Tag :value="row.type === 'ventas' ? 'Verkauf' : 'Prognose'"
                     :class="['pill', row.type === 'ventas' ? 'verkauf' : 'prognose']" />
              </div>
              <div class="line2">
                <span>Verkauf: <b>{{ row.venta }}</b></span>
                <span>Budget: <b>{{ row.budget }}</b></span>
                <span>Prognose: <b>{{ row._forecast }}</b></span>
                <span>Δ V/Budget: <b>{{ pct(row.venta, row.budget) }}</b></span>
                <span>Δ P/Budget: <b>{{ pct(row._forecast, row.budget) }}</b></span>
              </div>
            </div>
          </div>
        </template>

        <div class="tab-body">
          <div class="form-grid">
            <div class="field">
              <label>Begründung</label>
              <Textarea v-model="row._note" rows="3" class="w-full" />
            </div>
            <div class="field">
              <label>Prognose-Anpassung ({{ row.month }})</label>
              <InputNumber v-model="row._forecast" inputClass="w-full text-right" :min="0" :useGrouping="false" />
            </div>
          </div>
          <div class="actions">
            <Button label="Speichern" icon="pi pi-save" @click="saveRow(row)" />
          </div>
        </div>
      </AccordionTab>
    </Accordion>
  </div>
</template>

<style scoped>
.deviation-panel { width: 100%; }
.panel-header{
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 8px;
}
.loading, .empty { padding: 12px; opacity: .8; }

/* Accordion header with left color marker */
.tab-header{ display: flex; align-items: center; gap: 12px; }
.marker{ width: 6px; height: 32px; border-radius: 3px; }
.marker.verkauf{ background: #3B82F6; }      /* blue for sales deviations */
.marker.prognose{ background: #F59E0B; }     /* amber for forecast deviations */

.title{ display: flex; flex-direction: column; gap: 4px; }
.line1{ display: flex; align-items: center; gap: 8px; }
.line2{ display: flex; flex-wrap: wrap; gap: 16px; font-size: .85rem; opacity: .9; }

/* Body */
.tab-body{ padding: 8px 4px 4px; }
.form-grid{ display: grid; grid-template-columns: 1fr 280px; gap: 12px; }
.field label{ display:block; font-size: .85rem; color: #6b7280; margin-bottom: 6px; }
.actions{ margin-top: 8px; display: flex; justify-content: flex-end; }

/* Fixed-color pills independent of theme */
:deep(.p-tag.pill){
  border-radius: 9999px; padding: 0.1rem 0.5rem; font-weight: 600; font-size: .75rem;
  border: 1px solid transparent;
}
:deep(.p-tag.pill.verkauf){
  background: rgba(59,130,246,.18); color: #1e40af; border-color: rgba(59,130,246,.28);
}
:deep(.p-tag.pill.prognose){
  background: rgba(245,158,11,.22); color: #92400e; border-color: rgba(245,158,11,.30);
}

/* Glassy look to match page cards */
:deep(.p-accordion){ background: transparent; border: 0; }
:deep(.p-accordion .p-accordion-header .p-accordion-header-link){
  background: rgba(255,255,255,0.45);
  backdrop-filter: blur(8px);
}
:deep(.p-accordion .p-accordion-content){
  background: rgba(255,255,255,0.30);
  backdrop-filter: blur(8px);
}
</style>