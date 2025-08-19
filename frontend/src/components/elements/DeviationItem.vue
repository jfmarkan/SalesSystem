<template>
  <div :class="['item', { open }]">
    <div class="header" @click="open = !open">
      <span class="band" :class="bandClass"></span>
      <div class="head-main">
        <div class="top-row">
          <span class="type">{{ typeLabel(dev.type) }}-Abweichung</span>
          <span class="dot">•</span>
          <span class="pc">{{ dev.pcCode }} — {{ dev.pcName }}</span>
        </div>
        <div class="meta">
          {{ formatMonthDE(monthKey) }}
        </div>
      </div>
      <span class="pill" :class="statusPillClass">{{ statusPillLabel }}</span>
    </div>

    <transition name="fade">
      <div v-if="open" class="body">
        <!-- 3-column layout -->
        <div class="content-grid-3">
          <!-- LEFT: Chart (taller) + metric cards below -->
          <div class="col left">
            <div class="chart-wrap">
              <MiniDeviationChart
                :months="dev.months"
                :sales="dev.salesSeries ?? dev.sales"
                :budget="dev.budgetSeries ?? dev.budget"
                :forecast="dev.forecastSeries ?? dev.forecast"
                :height="360"
              />
            </div>

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
                <div class="k">Prognose</div>
                <div class="v">{{ fmtNumber(dev.forecast) }}</div>
              </div>
              <div class="box delta" :class="deltaSeverityClass">
                <div class="k">Delta</div>
                <div class="v">{{ fmtNumber(dev.deltaAbs) }} ({{ fmtPct(dev.deltaPct) }})</div>
              </div>
            </div>
          </div>

          <!-- MIDDLE: Text inputs (explanation + plan text) and Save -->
          <div class="col middle">
            <div class="form">
              <label class="lbl">Begründung</label>
              <Textarea
                v-model="comment"
                autoResize
                rows="5"
                class="w-full"
                placeholder="Begründung eingeben…"
                :disabled="readonly"
              />
            </div>

            <div class="form">
              <label class="lbl">Aktionsplan (Zielbeschreibung)</label>
              <Textarea
                v-model="plan"
                autoResize
                rows="6"
                class="w-full"
                placeholder="Plan kurz beschreiben…"
                :disabled="readonly"
              />
              <div v-if="String(dev.type).toLowerCase()==='forecast' && !planCreated && needsPlan" class="note warn">
                Aktionsplan empfohlen: Prognose < Budget.
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

          <!-- RIGHT: Plan panel (create + actions list with + below last) -->
          <div
            v-if="String(dev.type).toLowerCase()==='forecast'"
            class="col right"
          >
            <div class="plan-panel">
              <!-- Empty state: centered create button -->
              <div v-if="!planCreated && actions.length === 0" class="panel-empty">
                <Button
                  label="Plan erstellen"
                  icon="pi pi-flag"
                  class="p-button-sm"
                  @click.stop="createPlan"
                />
              </div>

              <!-- After creation: actions list + add button below the last action -->
              <div v-else class="panel-actions">
                <div class="actions-list">
                  <div v-for="(a, idx) in actions" :key="idx" class="action-row">
                    <InputText
                      v-model="a.title"
                      class="w-12"
                      placeholder="Titel"
                      :disabled="readonly"
                    />
                    <InputText
                      v-model="a.desc"
                      class="w-12"
                      placeholder="Beschreibung"
                      :disabled="readonly"
                    />
                    <div class="row-inline">
                      <Calendar
                        v-model="a.due"
                        dateFormat="yy-mm-dd"
                        class="w-12"
                        :manualInput="true"
                        :showIcon="true"
                        :disabled="readonly"
                      />
                      <Button
                        v-if="!readonly"
                        icon="pi pi-trash"
                        class="p-button-text p-button-danger"
                        @click.stop="removeAction(idx)"
                        :aria-label="`Aktion ${idx+1} löschen`"
                        title="Löschen"
                      />
                    </div>
                  </div>
                </div>

                <!-- '+' button sits after the last action (moves up/down automatically) -->
                <div v-if="!readonly" class="add-wrap">
                  <Button
                    icon="pi pi-plus"
                    class="p-button-rounded p-button-text add-btn"
                    @click.stop="addAction"
                    aria-label="Aktion hinzufügen"
                    title="Aktion hinzufügen"
                  />
                </div>
              </div>
            </div>
          </div>
        </div> <!-- /content-grid-3 -->
      </div>
    </transition>
  </div>
</template>
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
  readonly: { type: Boolean, default: false }
})
const emit = defineEmits(['save'])

const open = ref(false)
const comment = ref(props.dev.comment || '')

// Textual plan (objective) lives in the middle column
const plan = ref('')

// Action items live in the right panel
const actions = ref([]) // [{title,desc,due}]
const planCreated = ref(false) // explicit toggle via "Plan erstellen"

watch(() => props.dev.comment, v => { if (!props.readonly) comment.value = v || '' })

const DE_ABBR = ['Jän','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez']
function ym(y,m){ return `${y}-${String(m).padStart(2,'0')}` }
function formatMonthDE(key){
  const m = String(key).match(/^(\d{4})-(\d{2})/)
  if (!m) return key
  const y = m[1], mm = Math.max(1, Math.min(12, parseInt(m[2],10)))
  return `${DE_ABBR[mm-1]} ${y.slice(2)}`
}
function fmtNumber(x){ return (Number(x)||0).toLocaleString('de-DE') }
function fmtPct(p){ return (p==null||isNaN(p)) ? '—' : `${Math.round(Number(p))}%` }
function typeLabel(t){ return String(t).toLowerCase()==='forecast' ? 'Prognose' : 'Vertrieb' }

const monthKey = computed(() => ym(props.dev.year, props.dev.month))
const bandClass = computed(() => String(props.dev.type).toLowerCase()==='forecast' ? 'forecast' : 'sales')

// Delta severity drives color of the Delta card
const deltaSeverityClass = computed(() => {
  const d = Number(props.dev?.deltaPct) || 0
  const ad = Math.abs(d)
  if (ad > 10) return 'sev-red'
  if (ad > 5)  return 'sev-orange'
  if (ad > 2)  return 'sev-yellow'
  return 'sev-green'
})

// Header status pill = justification state (grey/green)
const statusPillClass = computed(() => props.dev.justified ? 'status-saved' : 'status-open')
const statusPillLabel = computed(() => props.dev.justified ? 'Begründet' : 'Offen')

// Business rule helper (optional): require plan only for forecast when forecast < budget
const needsPlan = computed(() => {
  if (String(props.dev.type).toLowerCase() !== 'forecast') return false
  const f = Number(props.dev.forecast || 0)
  const b = Number(props.dev.budget || 0)
  if (b <= 0) return false
  return f < b
})

function addAction(){
  actions.value.push({ title:'', desc:'', due:null })
}
function removeAction(idx){
  actions.value.splice(idx,1)
}

function createPlan(){
  if (props.readonly) return
  planCreated.value = true
  // Optionally ensure there is at least one empty action after creation
  if (actions.value.length === 0) actions.value.push({ title:'', desc:'', due:null })
}

function doSave(){
  if (props.readonly) return
  emit('save', {
    id: props.dev.id,
    type: props.dev.type,
    comment: comment.value,
    plan: plan.value || null,
    actions: planCreated.value
      ? actions.value.map(a => ({
          title: a.title?.trim() || '',
          desc: a.desc?.trim() || '',
          due: a.due ? new Date(a.due).toISOString().slice(0,10) : null
        }))
      : []
  })
}
</script>


<style scoped>
.item{
  position: relative; border-radius: 12px;
  background: rgba(255,255,255,0.3);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,.08);
  box-shadow: 0 2px 6px rgba(0,0,0,.35);
  color:#fff;
}
.header{ display:flex; align-items:center; gap:12px; padding:10px 12px; cursor:pointer; }
.band{ width:6px; align-self:stretch; border-radius:6px; box-shadow: 0 0 0 1px rgba(0,0,0,.12) inset; }
.band.sales{ background:#749c75; }
.band.forecast{ background:#6A5D7B; }
.head-main{ flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
.top-row{ display:flex; align-items:center; gap:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.type{ font-weight:700; }
.dot{ opacity:.5; }
.meta{ font-size:.9rem; opacity:.9; }

/* Status pill in header (grey/green for justification) */
.pill{ min-width:90px; text-align:center; padding:4px 10px; border-radius:999px; font-weight:700; font-size:.85rem; }
.status-open{ background:#9CA3AF; color:#0a0a0a; }
.status-saved{ background:#2ecc71; color:#0a0a0a; }

.body{
  padding:12px;
  max-height:80vh; overflow:auto;
}

/* 3-column layout */
.content-grid-3{
  display:grid; grid-template-columns: 1.2fr 1fr 0.8fr; gap:12px;
}
@media (max-width: 1200px){
  .content-grid-3{ grid-template-columns: 1fr; }
}

/* Left column */
.chart-wrap{ height:360px; }
.metrics-row{
  margin-top:10px;
  display:grid; grid-template-columns: repeat(4, minmax(120px, 1fr)); gap:10px;
}
.box{
  background: rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.10);
  border-radius:8px; padding:10px 12px; min-height:76px;
}
.k{ font-size:.8rem; opacity:.85; margin-bottom:4px; }
.v{ font-weight:700; font-size:1.05rem; }

/* Delta severity coloring */
.box.delta{ position:relative; }
.box.delta::before{
  content:''; position:absolute; left:0; top:0; bottom:0; width:6px; border-radius:8px 0 0 8px; opacity:.9;
}
.sev-green{ border-color: rgba(46,204,113,.35); background: rgba(46,204,113,.12); }
.sev-green::before{ background:#2ecc71; }
.sev-yellow{ border-color: rgba(230,183,41,.35); background: rgba(230,183,41,.14); }
.sev-yellow::before{ background:#E6B729; }
.sev-orange{ border-color: rgba(232,141,30,.35); background: rgba(232,141,30,.12); }
.sev-orange::before{ background:#E88D1E; }
.sev-red{ border-color: rgba(176,21,19,.35); background: rgba(176,21,19,.12); }
.sev-red::before{ background:#B01513; }

/* Middle column */
.col.middle{ display:flex; flex-direction:column; gap:12px; }
.form .lbl{ font-weight:700; margin-bottom:6px; display:block; }
.note.warn{ background: rgba(232,141,30,.12); border:1px solid rgba(232,141,30,.35); padding:8px 10px; border-radius:8px; }

/* Right column: plan panel */
.plan-panel{
  height:100%;
  border:1px solid #fff;         /* requested: white 1px border */
  border-radius:10px;            /* requested: rounded corners 10px */
  background: rgba(255,255,255,0.06);
  padding:12px;
  display:flex; flex-direction:column;
}
.panel-empty{
  flex:1; display:flex; align-items:center; justify-content:center;
}
.panel-actions{
  display:flex; flex-direction:column; gap:10px; height:100%;
}
.actions-list{ display:flex; flex-direction:column; gap:10px; }
.action-row{ display:flex; flex-direction:column; gap:6px; background: rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.10); border-radius:8px; padding:10px; }
.row-inline{ display:flex; align-items:center; gap:8px; }
.add-wrap{ display:flex; justify-content:center; padding-top:4px; }
.add-btn{ color:#fff; }
</style>
