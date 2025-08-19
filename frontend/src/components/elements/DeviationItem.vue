<script setup>
// Code in English; UI text in German.
import { ref, computed, watch } from 'vue'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import InputText from 'primevue/inputtext'
import Calendar from 'primevue/calendar'
import MiniDeviationChart from '../charts/MiniDeviationChart.vue'

const props = defineProps({
  dev: { type: Object, required: true },
  saving: { type: Boolean, default: false },
  readonly: { type: Boolean, default: false },
})
const emit = defineEmits(['save'])

const open = ref(false)
const comment = ref(props.dev.comment || '')
const plan = ref(props.dev.plan || '') // objective text

// Editable actions (only for open forecast)
const actions = ref([]) // [{title,desc,due}]
const planCreated = ref(false)

watch(
  () => props.dev.comment,
  (v) => {
    if (!props.readonly) comment.value = v || ''
  },
)
watch(
  () => props.dev.plan,
  (v) => {
    if (!props.readonly) plan.value = v || ''
  },
)

const DE_ABBR = ['Jän', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez']
function ym(y, m) {
  return `${y}-${String(m).padStart(2, '0')}`
}
function formatMonthDE(key) {
  const m = String(key).match(/^(\d{4})-(\d{2})/)
  if (!m) return key
  const y = m[1],
    mm = Math.max(1, Math.min(12, parseInt(m[2], 10)))
  return `${DE_ABBR[mm - 1]} ${y.slice(2)}`
}
function fmtNumber(x) {
  return (Number(x) || 0).toLocaleString('de-DE')
}
function fmtPct(p) {
  return p == null || isNaN(p) ? '—' : `${Math.round(Number(p))}%`
}

// Header labels: Forecast / Ist
function typeLabel(t) {
  return String(t).toLowerCase() === 'forecast' ? 'Forecast' : 'Ist'
}

const monthKey = computed(() => ym(props.dev.year, props.dev.month))
const isForecast = computed(() => String(props.dev.type).toLowerCase() === 'forecast')

// Delta severity → color on Delta card
const deltaSeverityClass = computed(() => {
  const d = Number(props.dev?.deltaPct) || 0
  const ad = Math.abs(d)
  if (ad > 10) return 'sev-red'
  if (ad > 5) return 'sev-orange'
  if (ad > 2) return 'sev-yellow'
  return 'sev-green'
})

// Header status pill
const statusPillClass = computed(() => (props.dev.justified ? 'status-saved' : 'status-open'))
const statusPillLabel = computed(() => (props.dev.justified ? 'Begründet' : 'Offen'))

// Forecast notes logic
const forecastRatio = computed(() => {
  const b = Number(props.dev.budget || 0)
  const f = Number(props.dev.forecast || 0)
  return b > 0 ? f / b : null
})
const belowBudget = computed(() => forecastRatio.value != null && forecastRatio.value < 1)
const aboveBudget = computed(() => forecastRatio.value != null && forecastRatio.value > 1)
const needsPlan = computed(() => isForecast.value && belowBudget.value)

function addAction() {
  actions.value.push({ title: '', desc: '', due: null })
}
function removeAction(idx) {
  actions.value.splice(idx, 1)
}
function createPlan() {
  if (props.readonly) return
  planCreated.value = true
  if (actions.value.length === 0) actions.value.push({ title: '', desc: '', due: null })
}
function doSave() {
  if (props.readonly) return
  emit('save', {
    id: props.dev.id,
    type: props.dev.type,
    comment: comment.value,
    plan: isForecast.value ? plan.value || null : null,
    actions:
      isForecast.value && planCreated.value
        ? actions.value.map((a) => ({
            title: a.title?.trim() || '',
            desc: a.desc?.trim() || '',
            due: a.due ? new Date(a.due).toISOString().slice(0, 10) : null,
          }))
        : [],
  })
}
</script>

<template>
  <div :class="['item', { open }]">
    <div class="header" @click="open = !open">
      <span class="band" :class="isForecast ? 'forecast' : 'sales'"></span>
      <div class="head-main">
        <div class="top-row">
          <!-- Title = Profit Center name -->
          <span class="pc-title">{{ dev.pcName || 'PC ' + dev.pcCode }}</span>
          <span class="dot">•</span>
          <!-- Type text: Forecast-Abweichung / Ist-Abweichung -->
          <span class="type">{{ typeLabel(dev.type) }}-Abweichung</span>
        </div>
        <div class="meta">{{ formatMonthDE(monthKey) }}</div>
      </div>
      <span class="pill" :class="statusPillClass">{{ statusPillLabel }}</span>
    </div>

    <transition name="fade">
      <div v-if="open" class="body">
        <!-- FORECAST (3 columns) -->
        <div v-if="isForecast" class="content-grid-3">
          <div class="col left">
            <div class="metrics-row">
              <div class="box">
                <div class="k">Ist</div>
                <div class="v">{{ fmtNumber(dev.sales) }}</div>
              </div>
              <div class="box">
                <div class="k">Budget</div>
                <div class="v">{{ fmtNumber(dev.budget) }}</div>
              </div>
              <div class="box">
                <div class="k">Forecast</div>
                <div class="v">{{ fmtNumber(dev.forecast) }}</div>
              </div>
              <div class="box delta" :class="deltaSeverityClass">
                <div class="k">Delta</div>
                <div class="v">{{ fmtNumber(dev.deltaAbs) }} ({{ fmtPct(dev.deltaPct) }})</div>
              </div>
            </div>
            <div class="chart-wrap">
              <MiniDeviationChart
                :months="dev.months"
                :sales="dev.salesSeries ?? dev.sales"
                :budget="dev.budgetSeries ?? dev.budget"
                :forecast="dev.forecastSeries ?? dev.forecast"
                :height="500"
              />
            </div>
          </div>

          <div class="col middle">
            <div class="form">
              <label class="lbl">Begründung</label>
              <Textarea
                v-model="comment"
                :disabled="readonly"
                autoResize
                rows="5"
                class="w-full"
                placeholder="Begründung eingeben…"
              />
            </div>

            <div class="form">
              <label class="lbl">Aktionsplan (Zielbeschreibung)</label>
              <Textarea
                v-if="!readonly"
                v-model="plan"
                autoResize
                rows="6"
                class="w-full"
                placeholder="Plan kurz beschreiben…"
              />
              <div v-else class="plan-readonly">{{ plan || '—' }}</div>

              <div v-if="!readonly && belowBudget && !planCreated" class="note warn my-3">
                Aktionsplan empfohlen: Forecast &lt; Budget.
              </div>
              <div v-if="!readonly && aboveBudget" class="note ok my-3">
                Kein Aktionsplan erforderlich: Forecast &gt; Budget. Bitte nur die Begründung
                angeben.
              </div>
            </div>

            <div v-if="!readonly" class="btns">
              <Button
                :label="saving ? 'Speichern…' : 'Begründung speichern'"
                icon="pi pi-save"
                class="p-button-sm"
                :loading="saving"
                @click.stop="doSave"
              />
            </div>
          </div>

          <div
            class="col right"
            v-if="
              (readonly && (dev.actions?.length || 0) > 0) ||
              (!readonly && (planCreated || needsPlan))
            "
          >
            <div class="plan-panel">
              <div v-if="readonly" class="panel-actions ro">
                <div class="title-ro">Aktionen</div>
                <div v-if="(dev.actions || []).length === 0" class="muted">Keine Aktionen.</div>
                <ul v-else class="ro-list">
                  <li v-for="(a, idx) in dev.actions" :key="idx">
                    <div class="ro-title">{{ a.title || '—' }}</div>
                    <div class="ro-desc" v-if="a.desc">{{ a.desc }}</div>
                    <div class="ro-due" v-if="a.due">Fällig: {{ a.due }}</div>
                  </li>
                </ul>
              </div>

              <template v-else>
                <div v-if="!planCreated && actions.length === 0" class="panel-empty">
                  <Button
                    label="Plan erstellen"
                    icon="pi pi-flag"
                    class="p-button-sm"
                    @click.stop="createPlan"
                  />
                </div>
                <div v-else class="panel-actions">
                  <div class="actions-list">
                    <div v-for="(a, idx) in actions" :key="idx" class="action-row">
                      <InputText v-model="a.title" class="w-12" placeholder="Titel" />
                      <InputText v-model="a.desc" class="w-12" placeholder="Beschreibung" />
                      <div class="row-inline">
                        <Calendar
                          v-model="a.due"
                          dateFormat="yy-mm-dd"
                          class="w-12"
                          :manualInput="true"
                          :showIcon="true"
                        />
                        <Button
                          icon="pi pi-trash"
                          class="p-button-text p-button-danger"
                          @click.stop="removeAction(idx)"
                          :aria-label="`Aktion ${idx + 1} löschen`"
                          title="Löschen"
                        />
                      </div>
                    </div>
                  </div>
                  <div class="add-wrap">
                    <Button
                      icon="pi pi-plus"
                      class="p-button-rounded p-button-text add-btn"
                      @click.stop="addAction"
                      aria-label="Aktion hinzufügen"
                      title="Aktion hinzufügen"
                    />
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- SALES (2 columns) -->
        <div v-else class="content-grid-2">
          <div class="col left">
            <div class="metrics-row">
              <div class="box">
                <div class="k">Ist</div>
                <div class="v">{{ fmtNumber(dev.sales) }}</div>
              </div>
              <div class="box">
                <div class="k">Budget</div>
                <div class="v">{{ fmtNumber(dev.budget) }}</div>
              </div>
              <div class="box delta" :class="deltaSeverityClass">
                <div class="k">Delta</div>
                <div class="v">{{ fmtNumber(dev.deltaAbs) }} ({{ fmtPct(dev.deltaPct) }})</div>
              </div>
            </div>
            <div class="chart-wrap">
              <MiniDeviationChart
                :months="dev.months"
                :sales="dev.salesSeries ?? dev.sales"
                :budget="dev.budgetSeries ?? dev.budget"
                :forecast="dev.forecastSeries ?? dev.forecast"
                :height="500"
              />
            </div>
          </div>

          <div class="col middle">
            <div class="form">
              <label class="lbl">Begründung</label>
              <Textarea
                v-model="comment"
                :disabled="readonly"
                autoResize
                rows="10"
                class="w-full"
                placeholder="Begründung eingeben…"
              />
            </div>

            <div v-if="!readonly" class="btns">
              <Button
                :label="saving ? 'Speichern…' : 'Begründung speichern'"
                icon="pi pi-save"
                class="p-button-sm"
                :loading="saving"
                @click.stop="doSave"
              />
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.item {
  position: relative;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.3);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
  color: #fff;
  overflow: visible; /* NO inner scroll */
}
.header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  cursor: pointer;
}
.band {
  width: 6px;
  align-self: stretch;
  border-radius: 6px;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.12) inset;
}
.band.sales {
  background: #749c75;
}
.band.forecast {
  background: #6a5d7b;
}
.head-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.top-row {
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.pc-title {
  font-weight: 700;
} /* New: PC name as title */
.pc-code {
  opacity: 0.9;
}
.type {
  font-weight: 600;
  opacity: 0.95;
} /* "Forecast-Abweichung" / "Ist-Abweichung" */
.dot {
  opacity: 0.5;
}
.meta {
  font-size: 0.9rem;
  opacity: 0.9;
}

.pill {
  min-width: 90px;
  text-align: center;
  padding: 4px 10px;
  border-radius: 999px;
  font-weight: 700;
  font-size: 0.85rem;
}
.status-open {
  background: #9ca3af;
  color: #0a0a0a;
}
.status-saved {
  background: #909C44;
  color: #0a0a0a;
}

.body {
  padding: 12px;
  overflow: visible;
}

.content-grid-3 {
  display: grid;
  grid-template-columns: 1.2fr 1fr 0.8fr;
  gap: 12px;
}
.content-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}
@media (max-width: 1200px) {
  .content-grid-3,
  .content-grid-2 {
    grid-template-columns: 1fr;
  }
}

.metrics-row {
  display: grid;
  gap: 10px;
}
.content-grid-3 .metrics-row {
  grid-template-columns: repeat(4, minmax(120px, 1fr));
  margin-bottom: 10px;
}
.content-grid-2 .metrics-row {
  grid-template-columns: repeat(3, minmax(140px, 1fr));
  margin-bottom: 10px;
}

.box {
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 10px 12px;
  min-height: 76px;
}
.k {
  font-size: 0.8rem;
  opacity: 0.85;
  margin-bottom: 4px;
}
.v {
  font-weight: 700;
  font-size: 1.05rem;
}

.chart-wrap {
  height: 500px;
}

.box.delta {
  position: relative;
}
.box.delta::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 6px;
  border-radius: 8px 0 0 8px;
  opacity: 0.9;
}
.sev-green {
  border-color: rgba(46, 204, 113, 0.35);
  background: rgba(46, 204, 113, 0.12);
}
.sev-green::before {
  background: #2ecc71;
}
.sev-yellow {
  border-color: rgba(230, 183, 41, 0.35);
  background: rgba(230, 183, 41, 0.14);
}
.sev-yellow::before {
  background: #e6b729;
}
.sev-orange {
  border-color: rgba(232, 141, 30, 0.35);
  background: rgba(232, 141, 30, 0.12);
}
.sev-orange::before {
  background: #e88d1e;
}
.sev-red {
  border-color: rgba(176, 21, 19, 0.35);
  background: rgba(176, 21, 19, 0.12);
}
.sev-red::before {
  background: #b01513;
}

.form .lbl {
  font-weight: 700;
  margin-bottom: 6px;
  display: block;
}
.plan-readonly {
  white-space: pre-wrap;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 10px;
}
.note.warn {
  background: rgba(232, 141, 30, 0.12);
  border: 1px solid rgba(232, 141, 30, 0.35);
  padding: 8px 10px;
  border-radius: 8px;
}
.note.ok {
  background: rgba(5, 164, 111, 0.18);
  border: 1px solid rgba(5, 164, 111, 0.35);
  padding: 8px 10px;
  border-radius: 8px;
}

.plan-panel {
  height: 100%;
  border: 1px solid #fff;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.06);
  padding: 12px;
  display: flex;
  flex-direction: column;
}
.panel-empty {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}
.panel-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  height: 100%;
}
.panel-actions.ro {
  gap: 6px;
}
.title-ro {
  font-weight: 700;
  margin-bottom: 4px;
}
.muted {
  opacity: 0.8;
}
.ro-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  list-style: none;
  padding: 0;
  margin: 0;
}
.ro-title {
  font-weight: 700;
}
.ro-desc {
  opacity: 0.95;
}
.ro-due {
  opacity: 0.9;
  font-size: 0.9rem;
}
.actions-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.action-row {
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 10px;
}
.row-inline {
  display: flex;
  align-items: center;
  gap: 8px;
}
.add-wrap {
  display: flex;
  justify-content: center;
  padding-top: 4px;
}
.add-btn {
  color: #fff;
}
.btns{
  display: flex;
  justify-content: flex-end; /* 👉 alinea a la derecha */
  margin-top: 8px;
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
