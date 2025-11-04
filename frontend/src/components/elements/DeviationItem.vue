<template>
  <div class="deviation-item-grid">
    <!-- LEFT -->
    <section class="left-col">
      <div class="metrics-row">
        <Card class="metric-card">
          <template #content>
            <div class="eyebrow">Ist</div>
            <div class="value">{{ fmtNumber(dev.sales) }}</div>
          </template>
        </Card>
        <Card class="metric-card">
          <template #content>
            <div class="eyebrow">Budget</div>
            <div class="value">{{ fmtNumber(dev.budget) }}</div>
          </template>
        </Card>
        <Card class="metric-card">
          <template #content>
            <div class="eyebrow">Forecast</div>
            <div class="value">{{ fmtNumber(dev.forecast) }}</div>
          </template>
        </Card>
        <Card class="metric-card delta-card" :class="deltaSeverityClass">
          <template #content>
            <div class="eyebrow">Delta</div>
            <div class="value">
              {{ fmtNumber(dev.deltaAbs) }}
              <span class="eyebrow">({{ fmtPct(dev.deltaPct) }})</span>
            </div>
          </template>
        </Card>
      </div>

      <Card class="chart-card">
        <template #content>
          <div class="chart-inner">
            <MiniDeviationChart
              :months="dev.months"
              :sales="dev.salesSeries ?? dev.sales"
              :budget="dev.budgetSeries ?? dev.budget"
              :forecast="dev.forecastSeries ?? dev.forecast"
              :height="'100%'"
              :value-formatter="fmtNumber"
            />
          </div>
        </template>
      </Card>
    </section>

    <!-- RIGHT -->
    <section class="right-col">
      <Card class="form-card">
        <template #content>
          <div class="eyebrow">Begründung</div>
          <Textarea
            v-model="comment"
            autoResize
            rows="5"
            class="w-full mb-4"
            placeholder="Begründung eingeben…"
          />

          <!-- PLAN: solo si forecast y delta < 0 -->
          <template v-if="isForecast && deltaNeg">
            <div class="eyebrow mt-2">Aktionsplan (Zielbeschreibung)</div>
            <Textarea
              v-model="plan"
              autoResize
              rows="5"
              class="w-full mb-3"
              placeholder="Plan kurz beschreiben…"
            />
          </template>

          <!-- Mensaje cuando forecast >= budget -->
          <template v-else-if="isForecast && !deltaNeg">
            <div class="hint-ok">
              Forecast liegt über dem Budget – kein Aktionsplan erforderlich.
            </div>
          </template>
        </template>
      </Card>

      <!-- ACTIONS: solo visibles si forecast y delta < 0 -->
      <Card v-if="isForecast && deltaNeg" class="actions-card">
        <template #content>
          <div class="eyebrow">Aktionsplan</div>

          <div v-if="actions.length === 0" class="empty-actions">
            <Button label="Plan erstellen" icon="pi pi-flag" @click="createPlan" />
          </div>

          <div v-else class="actions-list">
            <div v-for="(a, idx) in actions" :key="idx" class="action-edit-row">
              <InputText v-model="a.title" placeholder="Titel" />
              <InputText v-model="a.desc" placeholder="Beschreibung" />
              <div class="row-inline">
                <DatePicker v-model="a.due" dateFormat="yy-mm-dd" :manualInput="true" :showIcon="true" />
                <Button icon="pi pi-trash" severity="danger" outlined @click="removeAction(idx)" />
              </div>
            </div>
            <div class="flex justify-center mt-3">
              <Button icon="pi pi-plus" rounded text @click="addAction" />
            </div>
          </div>
        </template>
      </Card>
    </section>
  </div>
</template>

<script setup>
import { ref, computed, watch, watchEffect, defineExpose } from 'vue'
import MiniDeviationChart from '../charts/MiniDeviationChart.vue'

const props = defineProps({
  dev: { type: Object, required: true },
  saving: Boolean,
  readonly: Boolean,
})
const emit = defineEmits(['save', 'dirty-change', 'can-save'])

/* -------- local state (con guard) -------- */
const comment = ref('')
const plan = ref('')
const actions = ref([])
const isDirty = ref(false)
const suspendDirty = ref(false) // <- evita marcar dirty cuando sincronizamos programáticamente

function cloneActions(list) {
  return Array.isArray(list) ? list.map(a => ({ title: a.title || '', desc: a.desc || '', due: a.due ?? null })) : []
}

function setFromProps(d) {
  suspendDirty.value = true
  try {
    comment.value = d?.comment ?? ''
    plan.value = d?.plan ?? ''
    actions.value = cloneActions(d?.actions)
    isDirty.value = false
    emit('dirty-change', false)
  } finally {
    // pequeño nextTick no hace falta; basta con soltar el guard
    suspendDirty.value = false
  }
}

// init
setFromProps(props.dev)

// cambios de dev (selección)
watch(() => props.dev, (d) => setFromProps(d), { deep: false })

/* -------- helpers -------- */
function toInt(v) {
  if (typeof v === 'number' && Number.isFinite(v)) return Math.round(v)
  const s = String(v ?? '').replace(/\./g, '').split(',')[0].replace(/[^\d-]/g, '')
  return s === '' || s === '-' ? 0 : parseInt(s, 10)
}
const fmtNumber = (x) => new Intl.NumberFormat('de-DE').format(toInt(x))
const fmtPct = (p) => {
  const n = toInt(p)
  return Number.isFinite(n) ? `${n}%` : '—'
}
const deltaPctNum = computed(() => toInt(props.dev?.deltaPct))
const isForecast = computed(() => String(props.dev?.type || '').toLowerCase() === 'forecast')
const deltaNeg = computed(() => deltaPctNum.value < 0)

const deltaSeverityClass = computed(() => {
  const d = Math.abs(deltaPctNum.value)
  if (d > 10) return 'sev-red'
  if (d > 5) return 'sev-orange'
  if (d > 2) return 'sev-yellow'
  return 'sev-green'
})

/* -------- canSave -------- */
const canSave = computed(() => {
  if (props.readonly) return false
  const hasComment = comment.value.trim().length > 0
  if (!isForecast.value) return hasComment                  // IST
  if (deltaNeg.value) return hasComment && actions.value.length > 0 // Forecast con desvío negativo
  return hasComment                                         // Forecast ≥ Budget
})

// sincroniza canSave al padre
watchEffect(() => {
  emit('can-save', !!canSave.value)
})

// marca dirty cuando el usuario edita (no cuando setFromProps)
watch([comment, plan, actions], () => {
  if (suspendDirty.value) return
  isDirty.value = true
  emit('dirty-change', true)
}, { deep: true })

/* -------- acciones plan -------- */
function addAction() {
  actions.value.push({ title: '', desc: '', due: null })
}
function removeAction(i) {
  actions.value.splice(i, 1)
}
function createPlan() {
  if (actions.value.length === 0) addAction()
}

/* -------- expone save al padre -------- */
defineExpose({
  requestSave() {
    if (!canSave.value) return
    const sendPlan = isForecast.value && deltaNeg.value ? plan.value : null
    const sendActs = isForecast.value && deltaNeg.value ? actions.value : []
    emit('save', {
      id: props.dev.id,
      comment: comment.value,
      plan: sendPlan,
      actions: sendActs,
    })
    isDirty.value = false
    emit('dirty-change', false)
  },
  // opcional por si querés el fallback del padre
  getPayload() {
    const sendPlan = isForecast.value && deltaNeg.value ? plan.value : null
    const sendActs = isForecast.value && deltaNeg.value ? actions.value : []
    return { comment: comment.value, plan: sendPlan, actions: sendActs }
  }
})

</script>

<style scoped>
.deviation-item-grid {
  display: grid;
  grid-template-columns: 7fr 5fr;
  gap: 16px;
  width: 100%;
  height: 100%;
  box-sizing: border-box.
}

.left-col {
  display: grid;
  grid-template-rows: auto 1fr;
  gap: 16px;
  min-height: 0;
  width: 100%;
}

.metrics-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}

.metric-card {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: .15rem .3rem;
  min-height: 100px;
}

.chart-card {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}

/* Mantiene la estructura flex del card */
.chart-card :deep(.p-card-body),
.chart-card :deep(.p-card-content) {
  display: flex;
  flex: 1;
  flex-direction: column;
  min-height: 0;
  padding: 0; /* la card no agrega padding */
}

/* 👇 padding interno solo para el gráfico */
.chart-inner {
  flex: 1;
  display: flex;
  align-items: stretch;
  justify-content: center;
  width: calc(100% - 2rem);
  max-height: calc(100% - 2rem);
  padding: 2rem 2rem;
  box-sizing: border-box;
}

.eyebrow {
  font-size: 0.8rem;
  color: var(--text-muted);
  text-transform: uppercase;
  margin-bottom: 6px;
}

.value {
  font-size: 1.4rem;
  font-weight: 600;
}

.delta-card.sev-green { border-left: 4px solid #2ecc71; }
.delta-card.sev-yellow { border-left: 4px solid #e6b729; }
.delta-card.sev-orange { border-left: 4px solid #e88d1e; }
.delta-card.sev-red { border-left: 4px solid #b01513; }

.right-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-height: 0;
  height: 100%;
}

/* Inputs 100% ancho */
.right-col :deep(.p-inputtextarea),
.right-col :deep(textarea),
.right-col :deep(.p-inputtext),
.actions-card :deep(.p-inputtext) {
  width: 100% !important;
  max-width: none !important;
  display: block !important;
  box-sizing: border-box !important;
}

/* Espaciado en card del form */
.form-card :deep(.p-card-body),
.form-card :deep(.p-card-content) {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.form-card :deep(.mb-3),
.form-card :deep(.mb-4) {
  margin-bottom: 0.5rem !important;
}

.form-card,
.actions-card {
  display: flex;
  flex-direction: column;
}

/* La actions-card ocupa el resto del alto de la columna, sin crecer por contenido */
.actions-card {
  flex: 1 1 0;
  min-height: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.actions-card :deep(.p-card-body),
.actions-card :deep(.p-card-content) {
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}

.actions-card .actions-list,
.actions-card .empty-actions {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;
  padding-right: 4px;
}

/* Fila acción editable */
.actions-card .action-edit-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-bottom: 10px;
  border-bottom: 1px dashed var(--surface-border);
  min-width: 0;
}

.row-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  min-width: 0;
}

.row-inline :deep(.p-datepicker),
.row-inline :deep(.p-inputtext) {
  max-width: 100%;
}

/* Nota informativa */
.info-note {
  display: flex;
  align-items: flex-start;
  gap: .5rem;
  padding: .5rem .75rem;
  border-radius: 8px;
  font-size: .875rem;
  color: var(--text);
  background: color-mix(in oklab, var(--surface) 80%, transparent);
  border: 1px solid var(--surface-border, #e5e7eb);
}

.info-ico {
  color: var(--primary-color, #3b82f6);
  margin-top: .125rem;
}

/* Pequeño ajuste: scroll h no aparezca por 1-2px */
.actions-card :deep(.p-card-content) {
  padding-right: 2px;
  box-sizing: border-box;
}

.empty-actions {
  padding: 16px 0;
  text-align: center;
}
</style>
