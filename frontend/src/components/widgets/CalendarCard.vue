<!-- src/components/widgets/CalendarCard.vue -->
<template>
  <div class="calendar-card">
    <vue-cal
      ref="vc"
      style="height: 100%;"
      :locale="deShortLocale"
      :week-start="1"
      :events="normalizedEvents"
      :special-days="specialDays"
      default-view="month"
      hide-view-selector
      :disable-views="['years','year','week']"
      @cell-click="handleCellClick"
    />
  </div>
</template>

<script setup>
// Code/vars/comments in English
import { ref, computed } from 'vue'
import VueCal from 'vue-cal'
import 'vue-cal/dist/vuecal.css'

const props = defineProps({
  // [{ start: 'YYYY-MM-DD'|Date, end?: 'YYYY-MM-DD'|Date, title?, content? }]
  events: { type: Array, default: () => [] }
})

const vc = ref(null)

// German locale with short weekday labels (ensure Monday first)
const deShortLocale = {
  weekStart: 1,
  firstDayOfWeek: 1,
  // Full and short day names (some builds read one or the other)
  weekDays:      ['Mo','Di','Mi','Do','Fr','Sa','So'],
  weekDaysShort: ['Mo','De','Mi','Do','Fr','Sa', 'So'],
  weekdays:      ['Mo','Di','Mi','Do','Fr','Sa','So'],
  months: ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'],
  year: 'Jahr', month: 'Monat', years: 'Jahre'
}

// Normalize incoming events
function toDate(val) {
  if (!val) return null
  if (val instanceof Date) return val
  if (typeof val === 'string') {
    if (/^\d{4}-\d{2}-\d{2}$/.test(val)) return new Date(`${val}T00:00:00`)
    return new Date(val)
  }
  return new Date(val)
}
function toISODate(d) {
  const y = d.getFullYear(), m = String(d.getMonth()+1).padStart(2,'0'), day = String(d.getDate()).padStart(2,'0')
  return `${y}-${m}-${day}`
}

const normalizedEvents = computed(() => {
  return props.events.map(e => {
    const start = toDate(e.start ?? e.date ?? e.start_date)
    const endRaw = toDate(e.end ?? e.end_date ?? e.start ?? e.date) || start
    const end = (start && endRaw && endRaw < start) ? start : endRaw
    return start ? { ...e, start, end } : null
  }).filter(Boolean)
})

// Build specialDays only from real event days; class 'is-special' used in CSS
const specialDays = computed(() => {
  const dates = new Set()
  for (const ev of normalizedEvents.value) {
    let d = new Date(ev.start)
    const end = new Date(ev.end || ev.start)
    while (d <= end) {
      dates.add(toISODate(d))
      d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1)
    }
  }
  return Array.from(dates).map(ds => ({ start: ds, end: ds, class: 'is-special' }))
})

function handleCellClick(payload) {
  const date =
    (payload instanceof Date && payload) ||
    (payload?.date instanceof Date && payload.date) ||
    (payload?.startDate instanceof Date && payload.startDate) ||
    (payload?.start && toDate(payload.start)) ||
    (typeof payload?.date === 'string' && toDate(payload.date)) ||
    new Date()
  vc.value?.switchView?.('day', date)
}
</script>

<style scoped>
.calendar-card{
  height: 100%; 
  width: 100%;
  overflow: hidden;
  padding: 0.5rem;
}

/* Month cells highlighted only when in our specialDays */
:deep(.vuecal--month-view .vuecal__cell.is-special){
  border: 2px solid rgba(239, 68, 68, 1) !important;
  background: rgba(239, 68, 68, 0.5) !important;
  border-radius: 8px;
}

/* Remove time grid/columns in day view */
:deep(.vuecal--day-view .vuecal__time-column),
:deep(.vuecal--day-view .vuecal__bg),
:deep(.vuecal--day-view .vuecal__all-day){ display: none !important; }
:deep(.vuecal--day-view .vuecal__event){
  position: static !important; width: 100% !important; transform: none !important;
  background: rgba(239,68,68,.08) !important; border: 1px solid rgba(239,68,68,.8) !important;
  box-shadow: none !important; margin: .25rem 0; padding: .25rem .4rem; border-radius: 6px; color:#111827 !important;
}

/* Ensure weekday headers fit */
:deep(.vuecal__weekdays-headings .vuecal__weekday){ font-weight:700; font-size:.85rem; }
</style>

<!-- Global overrides (not scoped) to ensure they always apply -->
<style>
/* Hide the default events count badge everywhere */
.vuecal__cell-events-count,
.vuecal__events-count,
.vuecal__cell .events-count { display: none !important; }

/* Force Monday as first day (in case build ignores locale) */
.vuecal--month-view .vuecal__weekdays-headings .vuecal__weekday { order: initial; }

/* Fallback: enforce short weekday labels (Mon-first) */
.vuecal__weekdays-headings .vuecal__weekday { color: transparent !important; position: relative; }
.vuecal__weekdays-headings .vuecal__weekday:nth-child(1)::after { content: 'Mo'; color:#111827; }
.vuecal__weekdays-headings .vuecal__weekday:nth-child(2)::after { content: 'Di'; color:#111827; }
.vuecal__weekdays-headings .vuecal__weekday:nth-child(3)::after { content: 'Mi'; color:#111827; }
.vuecal__weekdays-headings .vuecal__weekday:nth-child(4)::after { content: 'Do'; color:#111827; }
.vuecal__weekdays-headings .vuecal__weekday:nth-child(5)::after { content: 'Fr'; color:#111827; }
.vuecal__weekdays-headings .vuecal__weekday:nth-child(6)::after { content: 'Sa'; color:#111827; }
.vuecal__weekdays-headings .vuecal__weekday:nth-child(7)::after { content: 'So'; color:#111827; }

/* Ensure our month highlight uses only our class */
.vuecal--month-view .vuecal__cell.vuecal__cell--has-events:not(.is-special){
  border: none !important; background: transparent !important;
}
</style>