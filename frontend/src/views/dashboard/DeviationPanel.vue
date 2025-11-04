<template>
  <div class="deviation-grid">
    <Toast />

    <Dialog v-model:visible="confirmVisible" modal dismissable-mask header="Ungespeicherte Änderungen" :style="{ width: '520px' }">
      <p class="mb-3">Es gibt nicht gespeicherte Änderungen. Möchtest du sie speichern?</p>
      <div class="flex justify-content-end gap-2">
        <Button label="Abbrechen" severity="secondary" @click="confirmVisible = false" />
        <Button label="Verwerfen" severity="danger" icon="pi pi-trash" @click="discardAndApply" />
        <Button label="Speichern" severity="success" icon="pi pi-save" @click="saveAndApply" />
      </div>
    </Dialog>

    <!-- Sidebar -->
    <aside class="deviation-sidebar">
      <Card class="sidebar-card">
        <template #content>
          <div class="sidebar-grid">
            <div class="tab-buttons-row">
              <Button label="Offen" :outlined="tab !== 'open'" :severity="tab === 'open' ? 'primary' : null" class="w-1/2" @click="tab = 'open'" />
              <Button label="Begründet" :outlined="tab !== 'just'" :severity="tab === 'just' ? 'primary' : null" class="w-1/2" @click="tab = 'just'" />
            </div>

            <Listbox
              :model-value="selectedId"
              :options="currentList"
              optionLabel="pcName"
              optionValue="id"
              dataKey="id"
              class="deviation-listbox"
              @update:modelValue="guardedSelect"
            >
              <template #option="{ option, selected }">
                <div :class="['list-item', { selected }]">
                  <div class="list-item-title">
                    {{ option.pcName || ('PC ' + option.pcCode) }}
                  </div>
                  <div class="list-item-meta">
                    {{ option.year }}-{{ String(option.month).padStart(2, '0') }}
                    &nbsp;|&nbsp;
                    {{ option.type === 'forecast' ? 'Forecast' : 'Ist' }}
                  </div>
                </div>
              </template>
            </Listbox>

            <div class="list-footer">Total: {{ currentList.length }}</div>
          </div>
        </template>
      </Card>
    </aside>

    <!-- Content -->
    <main class="deviation-content">
      <Card class="topbar-card">
        <template #content>
          <div class="topbar-inner">
            <div class="title-left">
              <div class="eyebrow">Abweichung</div>
              <div class="title-line">
                <strong class="kunde">{{ selectedDevFull?.pcName || '—' }}</strong>
                <span class="sep" aria-hidden="true"> | </span>
                <span class="pc" v-if="selectedDevFull">
                  {{ selectedDevFull.type === 'forecast' ? 'Forecast' : 'Ist' }}
                  &middot;
                  {{ selectedDevFull.year }}-{{ String(selectedDevFull.month).padStart(2, '0') }}
                </span>
              </div>
            </div>

            <div class="actions">
  <Button
    label="Speichern"
    icon="pi pi-save"
    :disabled="!canSaveActive"
    :outlined="savingId !== selectedDevFull?.id"
    :loading="savingId === selectedDevFull?.id"
    @click="saveFromTopbar"
  />
</div>
          </div>
        </template>
      </Card>

<DeviationItem
  v-if="selectedDevFull"
  ref="devItemRef"
  :key="selectedDevFull.id"
  :dev="selectedDevFull"
  :saving="savingId === selectedDevFull.id"
  :readonly="selectedDevFull.justified"
  @save="onSave"
  @dirty-change="onChildDirty"
  @can-save="onChildCanSave"
/>

      <div v-if="loading" class="local-loader mt-4">
        <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
        <div class="caption">Wird geladen…</div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'

import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import DeviationItem from '@/components/elements/DeviationItem.vue'

const toast = useToast()

/* ======================== Estado principal ======================== */
const deviations = ref([])
const tab = ref('open')
const loading = ref(false)
const savingId = ref(null)

const selectedId = ref(null)
const hasUnsaved = ref(false)
const confirmVisible = ref(false)
const pendingChange = ref(null)
const canSaveActive = ref(false)

/* Ref al hijo para invocar requestSave() desde el topbar */
const devItemRef = ref(null)
function saveFromTopbar() {
  devItemRef.value?.requestSave?.()
}

/* ======================== Derivados ======================== */
const openList = computed(() => deviations.value.filter((d) => !d.justified))
const closedList = computed(() => deviations.value.filter((d) => d.justified))
const currentList = computed(() => (tab.value === 'open' ? openList.value : closedList.value))

/* Selección coherente ante cambios de tab/lista */
watch([tab, currentList], () => {
  if (!currentList.value.length) {
    selectedId.value = null
  } else if (!currentList.value.some((d) => d.id === selectedId.value)) {
    selectedId.value = currentList.value[0].id
  }
})

/* Objeto completo actual */
const selectedDevFull = computed(() => {
  const id = selectedId.value
  if (id == null) return null
  const d = deviations.value.find((x) => x.id === id)
  if (!d) return null
  return {
    sales: 0,
    budget: 0,
    forecast: 0,
    deltaAbs: 0,
    deltaPct: 0,
    ...d,
  }
})

/* Reset flags al cambiar selección efectiva */
watch(selectedId, () => {
  hasUnsaved.value = false
  canSaveActive.value = false
})

/* ======================== Handlers del hijo ======================== */
function onChildDirty(v) {
  hasUnsaved.value = !!v
}
function onChildCanSave(v) {
  canSaveActive.value = !!v
}

/* ======================== Confirmación de cambios ======================== */
function guardedSelect(id) {
  if (hasUnsaved.value) {
    pendingChange.value = id
    confirmVisible.value = true
  } else {
    selectedId.value = id
  }
}

function saveAndApply() {
  if (!selectedDevFull.value) return
  // dispara el guardado del hijo (doSave expuesto)
  saveFromTopbar()
  // el onSave pondrá hasUnsaved=false; por las dudas:
  hasUnsaved.value = false
  confirmVisible.value = false
  if (pendingChange.value != null) {
    selectedId.value = pendingChange.value
    pendingChange.value = null
  }
}

function discardAndApply() {
  hasUnsaved.value = false
  confirmVisible.value = false
  if (pendingChange.value != null) {
    selectedId.value = pendingChange.value
    pendingChange.value = null
  }
}

/* ======================== Carga desde API ======================== */
function parseMaskedInt(v) {
  if (typeof v === 'number' && Number.isFinite(v)) return Math.round(v)
  if (typeof v !== 'string') return 0
  const noDots = v.replace(/\./g, '')
  const beforeComma = noDots.split(',')[0]
  const onlyDigits = beforeComma.replace(/[^\d-]/g, '')
  return onlyDigits === '' || onlyDigits === '-' ? 0 : parseInt(onlyDigits, 10)
}
const toNumArray = (arr) => (Array.isArray(arr) ? arr.map(parseMaskedInt) : null)

function normalizeDev(d) {
  return {
    id: d.id,
    type: String(d.type || 'sales').toLowerCase(),
    clientName: d.clientName || '',
    pcCode: d.pcCode || '',
    pcName: d.pcName || '',
    year: parseMaskedInt(d.year),
    month: parseMaskedInt(d.month),
    sales: parseMaskedInt(d.sales),
    budget: parseMaskedInt(d.budget),
    forecast: parseMaskedInt(d.forecast),
    deltaAbs: parseMaskedInt(d.deltaAbs),
    deltaPct: parseMaskedInt(d.deltaPct),
    comment: d.comment || '',
    plan: d.plan ?? null,
    actions: Array.isArray(d.actions) ? d.actions : [],
    justified: !!d.justified,
    months: Array.isArray(d.months) ? d.months : null,
    salesSeries: toNumArray(d.salesSeries),
    budgetSeries: toNumArray(d.budgetSeries),
    forecastSeries: toNumArray(d.forecastSeries),
  }
}

async function loadDeviations() {
  loading.value = true
  try {
    await ensureCsrf()
    const { data } = await api.get('/api/deviations')
    deviations.value = Array.isArray(data) ? data.map(normalizeDev) : []
    selectedId.value = currentList.value.length ? currentList.value[0].id : null
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: 'Abweichungen konnten nicht geladen werden',
      life: 2500,
    })
  } finally {
    loading.value = false
  }
}

/* ======================== Normalización de fechas (Y-m-d) ======================== */
function pad2(n) {
  return String(n).padStart(2, '0')
}
function formatYMDFromDate(d) {
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`
}
function toYMD(value) {
  if (!value) return null
  if (typeof value === 'string') {
    if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value
    const tmp = new Date(value)
    if (!isNaN(tmp)) return formatYMDFromDate(tmp)
    return null
  }
  if (value instanceof Date) {
    if (isNaN(value)) return null
    return formatYMDFromDate(value)
  }
  return null
}

/* ======================== Guardado (recibe payload del hijo) ======================== */
async function onSave(payload) {
  const { id, comment, plan, actions } = payload
  savingId.value = id

  // Normalizamos fechas de acciones a 'YYYY-MM-DD' o null
  const normActions = Array.isArray(actions)
    ? actions.map((a) => ({
        ...a,
        due: toYMD(a?.due),
      }))
    : []

  // Marcamos limpio ANTES de tocar listas/selección
  hasUnsaved.value = false
  canSaveActive.value = false

  try {
    await ensureCsrf()
    await api.put(`/api/deviations/${id}/justify`, {
      comment,
      plan,
      actions: normActions,
    })

    // actualizamos el dev local para no perder los textos en UI
    const idx = deviations.value.findIndex((d) => d.id === id)
    if (idx >= 0) {
      deviations.value[idx] = {
        ...deviations.value[idx],
        justified: true,
        comment,
        plan,
        actions: normActions,
      }
    }

    toast.add({
      severity: 'success',
      summary: 'Gespeichert',
      detail: 'Begründung gespeichert',
      life: 1600,
    })

    // si estamos en "open", pasamos al siguiente abierto (no cambiamos de tab)
    if (tab.value === 'open') {
      const next = openList.value[0]?.id ?? null
      selectedId.value = next
    }
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: 'Begründung konnte nicht gespeichert werden',
      life: 2500,
    })
  } finally {
    savingId.value = null
  }
}

onMounted(loadDeviations)
</script>



<style scoped>
/* idéntico layout al anterior */
.deviation-grid {
	--gap: 16px;
	display: grid;
	grid-template-columns: repeat(12, minmax(0, 1fr));
	gap: var(--gap);
	height: 100%;
	overflow: hidden;
}

.deviation-sidebar {
	grid-column: span 2;
	min-width: 0;
	display: flex;
	height: 100%;
	overflow: auto;
}

.sidebar-card {
	flex: 1;
	display: flex;
	flex-direction: column;
	min-height: 0;
}

.sidebar-grid {
	display: grid;
	grid-template-rows: auto 1fr auto;
	height: 100%;
	min-height: 0;
}

.tab-buttons-row {
	display: flex;
	gap: 8px;
	padding: 12px;
}

.tab-buttons-row :deep(.p-button) {
	flex: 1;
}

.deviation-listbox {
	min-height: 0;
	height: 100%;
	padding: 0 12px;
}

.deviation-listbox :deep(.p-listbox-list-wrapper) {
	height: 100%;
	overflow-y: auto;
}

.list-footer {
	padding: 10px 12px;
	font-size: 0.75rem;
	color: var(--text-muted);
	border-top: 1px solid var(--surface-border);
	text-align: right;
}

.list-item {
	padding: 8px;
	border-radius: 6px;
	font-size: 0.875rem;
	background: var(--surface-100);
	border: 1px solid transparent;
	cursor: pointer;
	transition: background 0.15s ease;
}

.list-item:hover {
	background: var(--surface-200);
}

.list-item.selected {
	border: 1px solid var(--primary);
	background: var(--primary-light);
}

.list-item-title {
	font-weight: 600;
}

.list-item-meta {
	font-size: 0.75rem;
	color: var(--text-muted);
}

.deviation-content {
	grid-column: span 10;
	display: flex;
	flex-direction: column;
	gap: var(--gap);
	min-height: 0;
}

.topbar-card {
	flex: 0 0 auto;
}

.topbar-inner {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.eyebrow {
	font-size: 0.75rem;
	color: var(--text-muted);
	text-transform: uppercase;
	margin-bottom: 0.25rem;
}

.title-line {
	display: flex;
	align-items: center;
	gap: 0.5rem;
}

.sep {
	opacity: 0.5;
}
</style>
