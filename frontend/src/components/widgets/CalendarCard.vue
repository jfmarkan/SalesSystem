<template>
  <div class="calendar-card">
    <vue-cal
      ref="vc"
      style="height: 100%;"
      :locale="deShortLocale"
      :week-start="1"
      :events="eventsNormalized"
      :special-days="specialDays"
      default-view="month"
      hide-view-selector
      :disable-views="['years','year','week']"
      :time="false"
      @cell-click="handleCellClick"
    >
      <!-- Keep backend event shape; only show minimal text -->
      <template #event="{ event }">
        <div class="ev">
          <div class="ev-title">{{ event.title }}</div>
          <div v-if="event.extendedProps?.objective" class="ev-sub">
            {{ event.extendedProps.objective }}
          </div>
        </div>
      </template>
    </vue-cal>
  </div>
</template>

<script setup>
// Code in English; UI in German.
import { ref, computed } from 'vue'
import VueCal from 'vue-cal'
import 'vue-cal/dist/vuecal.css'

const props = defineProps({
  // Expect EXACT backend shape; we only convert dates to Date objects.
  // [
  //   { id, title, start:'YYYY-MM-DD', end:'YYYY-MM-DD', allDay:true, status:'pending'|'overdue',
  //     extendedProps:{ description, deviation_id, objective, owner_user_id } }
  // ]
  events: { type: Array, default: () => [] }
})

const vc = ref(null)

// German locale with short weekday labels, Monday first
const deShortLocale = {
  weekStart: 1,
  firstDayOfWeek: 1,
  weekDays: ['Mo','Di','Mi','Do','Fr','Sa','So'],
  months: ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
  year: 'Jahr', month: 'Monat', years: 'Jahre',
}

// Date helpers
function toDate(v) {
  if (!v) return null
  if (v instanceof Date) return v
  if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}/.test(v)) return new Date(v + 'T00:00:00')
  return new Date(v)
}
function toISO(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

// Normalize ONLY start/end -> Date. Preserve all fields (id, title, allDay, status, extendedProps)
const eventsNormalized = computed(() =>
  (props.events || []).map(e => {
    const start = toDate(e.start)
    const endRaw = toDate(e.end ?? e.start) || start
    const end = (start && endRaw && endRaw < start) ? start : endRaw
    return { ...e, start, end }
  })
)

// Highlight days that have events (red border + 50% fill)
const specialDays = computed(() => {
  const out = []
  for (const ev of eventsNormalized.value) {
    let d = new Date(ev.start)
    const end = new Date(ev.end || ev.start)
    while (d <= end) {
      out.push({ start: toISO(d), end: toISO(d), class: 'is-special' })
      d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1)
    }
  }
  return out
})

// Month -> Day on click
function handleCellClick(payload) {
  const date =
    (payload instanceof Date && payload) ||
    (payload?.date instanceof Date && payload.date) ||
    (payload?.start && toDate(payload.start)) ||
    new Date()
  vc.value?.switchView?.('day', date)
}
</script>

<style scoped>
.calendar-card{
  height: 100%;
  width: 100%;
  overflow: hidden;
  padding: .5rem;
}

/* Month cells highlighted only when in our specialDays */
:deep(.vuecal--month-view .vuecal__cell.is-special){
  border: 2px solid rgba(239, 68, 68, 1) !important;   /* red border, opacity 1 */
  background: rgba(239, 68, 68, 0.5) !important;       /* center fill 50% */
  border-radius: 8px;
}

/* Day view: hide time grid */
:deep(.vuecal--day-view .vuecal__time-column),
:deep(.vuecal--day-view .vuecal__bg),
:deep(.vuecal--day-view .vuecal__all-day){ display: none !important; }
:deep(.vuecal--day-view .vuecal__event){
  position: static !important;
  width: 100% !important;
  transform: none !important;
  background: rgba(239,68,68,.08) !important;
  border: 1px solid rgba(239,68,68,.8) !important;
  box-shadow: none !important;
  margin: .25rem 0;
  padding: .25rem .4rem;
  border-radius: 6px;
  color: #111827 !important;
}

/* Event content */
.ev-title{ font-size: .9rem; font-weight: 700; }
.ev-sub{ font-size: .8rem; opacity: .9; }

/* Weekday header legible & short */
:deep(.vuecal__weekdays-headings .vuecal__weekday){ font-weight:700; font-size:.85rem; }
</style>

<!-- Global overrides to match requirements -->
<style>
/* Remove default count badge */
.vuecal__cell-events-count,
.vuecal__events-count,
.vuecal__cell .events-count { display: none !important; }

/* Ensure only our class paints event days */
.vuecal--month-view .vuecal__cell.vuecal__cell--has-events:not(.is-special){
  border: none !important;
  background: transparent !important;
}
</style>
