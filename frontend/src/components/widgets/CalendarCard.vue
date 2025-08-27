<template>
  <div class="calendar-card">
    <vue-cal
      ref="vc"
      style="height: 100%;"
      :locale="deShortLocale"
      :week-start="1"
      :events="eventsNormalized"
      :time="false"
      default-view="month"
      hide-view-selector
      :disable-views="['years','year','week']"
      @cell-click="handleCellClick"
      @event-click="onEventClick"
    >
      <!-- Tagesansicht: Aktionen als Liste -->
      <template #event="{ event }">
        <div class="ev" :class="statusClass(event)">
          <div class="ev-title">
            <input class="ev-check" type="checkbox" :checked="event.extendedProps?.is_completed" disabled />
            <span :class="{done: event.extendedProps?.is_completed}">{{ event.title }}</span>
            <span class="ev-date">• {{ formatDate(event.extendedProps?.due_date || event.start) }}</span>
          </div>
          <div v-if="event.extendedProps?.description" class="ev-sub">
            {{ event.extendedProps.description }}
          </div>
        </div>
      </template>
    </vue-cal>

    <!-- Glass modal -->
    <div v-if="showModal" class="glass-backdrop" @click.self="closeModal">
      <div class="glass-modal">
        <div class="glass-header">
          <h3>Aktion bearbeiten</h3>
          <button class="icon-btn" @click="closeModal" aria-label="Schließen">✕</button>
        </div>
        <div class="glass-body">
          <div class="row">
            <label>Titel</label>
            <input class="input" type="text" :value="editingEvent?.title || ''" disabled>
          </div>
          <div class="row">
            <label>Fällig am</label>
            <input class="input" type="date" v-model="formDate">
          </div>
          <div class="row">
            <label>Status</label>
            <div class="radio-group">
              <label><input type="radio" value="pending"   v-model="formStatus"> Offen</label>
              <label><input type="radio" value="completed" v-model="formStatus"> Erledigt</label>
              <label><input type="radio" value="cancelled" v-model="formStatus"> Storniert</label>
            </div>
          </div>
        </div>
        <div class="glass-actions">
          <button class="btn" @click="closeModal">Abbrechen</button>
          <button class="btn primary" @click="saveEdit">Speichern</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Code in English; UI in German.
import { ref, computed } from 'vue'
import VueCal from 'vue-cal'
import 'vue-cal/dist/vuecal.css'

const props = defineProps({
  // Parent maps: { id,title,start:Date,end:Date,allDay:true,status?,extendedProps:{ due_date,is_completed,description } }
  events: { type: Array, default: () => [] }
})
const emit = defineEmits(['update-action'])

const vc = ref(null)

// German locale
const deShortLocale = {
  weekStart: 1,
  firstDayOfWeek: 1,
  weekDays: ['Mo','Di','Mi','Do','Fr','Sa','So'],
  months: ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
  year: 'Jahr', month: 'Monat', years: 'Jahre',
}

// helpers
function toDate(v){
  if (!v) return null
  if (v instanceof Date) return v
  if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}/.test(v)) return new Date(v + 'T00:00:00')
  return new Date(v)
}
function toISODate(d){
  const dt = toDate(d) || new Date()
  const y = dt.getFullYear()
  const m = String(dt.getMonth()+1).padStart(2,'0')
  const day = String(dt.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}
function formatDate(d){
  const dt = toDate(d) || new Date()
  const dd = String(dt.getDate()).padStart(2,'0')
  const mm = String(dt.getMonth()+1).padStart(2,'0')
  const yyyy = dt.getFullYear()
  return `${dd}.${mm}.${yyyy}`
}

// computed events
const eventsNormalized = computed(() =>
  (props.events || []).map(e => ({
    ...e,
    start: toDate(e.start),
    end: toDate(e.end ?? e.start)
  }))
)

// month -> day
function handleCellClick(payload){
  const date =
    (payload instanceof Date && payload) ||
    (payload?.date instanceof Date && payload.date) ||
    (payload?.start && toDate(payload.start)) ||
    new Date()
  vc.value?.switchView?.('day', date)
}

// modal state
const showModal    = ref(false)
const editingEvent = ref(null)
const formDate     = ref('')
const formStatus   = ref('pending')

function onEventClick(payload){
  const ev = payload?.event || payload
  editingEvent.value = ev
  formDate.value   = ev?.extendedProps?.due_date || toISODate(ev?.start)
  formStatus.value = ev?.status || (ev?.extendedProps?.is_completed ? 'completed' : 'pending')
  showModal.value  = true
}
function closeModal(){ showModal.value = false }

function saveEdit(){
  if (!editingEvent.value) return
  const id = editingEvent.value.id
  const due_date = formDate.value
  const status = formStatus.value

  // keep the view on the edited day
  const jump = new Date(due_date + 'T00:00:00')
  vc.value?.switchView?.('day', jump)

  // notify parent; parent will update props (optimistic) so the calendar rerenders
  emit('update-action', { id, due_date, status })
  showModal.value = false
}

// styles
function statusClass(event) {
  const s = (event?.status || '').toLowerCase()
  if (s === 'completed' || event?.extendedProps?.is_completed) return 'is-completed'
  if (s === 'cancelled') return 'is-cancelled'
  if (s === 'pending') return 'is-pending'
  return ''
}
</script>

<style scoped>
.calendar-card{ height:100%; width:100%; overflow:hidden; padding:.5rem; }

/* ===== MONTH VIEW ===== */
/* Today number in red */
:deep(.vuecal--month-view .vuecal__cell--today .vuecal__cell-date){
  color:#ef4444 !important; font-weight:800 !important;
}
/* Days with events: red border + red background @ 0.2 */
:deep(.vuecal--month-view .vuecal__cell--has-events){
  border:1px solid rgba(239,68,68,1) !important;
  background:rgba(239,68,68,.2) !important;
  border-radius:8px;
}
/* Hide chips in month view */
:deep(.vuecal--month-view .vuecal__event){ display:none !important; }

/* ===== DAY VIEW (timeless) ===== */
:deep(.vuecal--day-view .vuecal__event){
  position:static !important; width:100% !important; transform:none !important;
  background:rgba(239,68,68,.07) !important; border:1px solid rgba(239,68,68,.7) !important;
  box-shadow:none !important; margin:.35rem 0; padding:.5rem .6rem; border-radius:8px; color:#111827 !important;
}
.ev-title{ font-size:.95rem; font-weight:700; display:flex; align-items:center; gap:.5rem; }
.ev-sub{ font-size:.82rem; opacity:.9; margin-top:2px; }
.ev-date{ font-weight:600; opacity:.9; }
.ev-check{ width:14px; height:14px; accent-color:#ef4444; }
.done{ text-decoration:line-through; opacity:.7; }
.ev.is-completed{ border-color:#16a34a !important; background:rgba(22,163,74,.08) !important; }
.ev.is-cancelled{ border-color:#6b7280 !important; background:rgba(107,114,128,.08) !important; opacity:.95; }
.ev.is-pending{ border-color:#ef4444 !important; }

/* ===== Glass modal ===== */
.glass-backdrop{
  position:fixed; inset:0; background:rgba(0,0,0,.45);
  backdrop-filter: blur(4px);
  display:flex; align-items:center; justify-content:center; z-index:9999;
}
.glass-modal{
  width:min(540px, 94vw);
  background:rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.35);
  border-radius:16px;
  box-shadow:0 15px 40px rgba(0,0,0,.35);
  color:#111827;
  overflow:hidden;
  backdrop-filter: blur(18px);
}
.glass-header{
  display:flex; align-items:center; justify-content:space-between;
  padding:14px 16px;
  background:linear-gradient(180deg, rgba(255,255,255,.25), rgba(255,255,255,.08));
  border-bottom:1px solid rgba(255,255,255,.35);
}
.glass-body{ padding:16px; display:grid; gap:12px; }
.row{ display:grid; gap:6px; }
.input{
  width:100%; padding:10px 12px; border:1px solid rgba(0,0,0,.1);
  border-radius:10px; background:rgba(255,255,255,.65);
}
.radio-group{ display:flex; gap:16px; }
.glass-actions{
  display:flex; gap:10px; justify-content:flex-end;
  padding:12px 16px; border-top:1px solid rgba(255,255,255,.35);
  background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.02));
}
.btn{
  padding:8px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.1);
  background:rgba(255,255,255,.6); cursor:pointer;
}
.btn.primary{
  background:rgba(31,86,115,.9); color:#fff; border-color:rgba(31,86,115,1);
}
.icon-btn{ border:0; background:transparent; font-size:18px; cursor:pointer; color:#111827; }
</style>

<!-- Global overrides -->
<style>
.vuecal__cell-events-count,
.vuecal__events-count,
.vuecal__cell .events-count { display: none !important; }
</style>
