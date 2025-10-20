<template>
	<div class="cal ">
		<!-- Kopf -->
		<div class="cal-head">
			<button class="btn" @click="prevMonth" aria-label="Voriger Monat">
				<i class="pi pi-chevron-left" />
			</button>
			<div class="title">{{ title }}</div>
			<button class="btn" @click="nextMonth" aria-label="Nächster Monat">
				<i class="pi pi-chevron-right" />
			</button>
		</div>

		<!-- Raster -->
		<div class="cal-grid">
			<div class="dow" v-for="d in dows" :key="d">{{ d }}</div>

			<template v-for="(week, wi) in weeks" :key="wi">
				<div
					v-for="cell in week"
					:key="cell.iso"
					class="cell"
					:class="{ off: !cell.inMonth, today: cell.isToday }"
					@click="openDay(cell.iso)"
				>
					<span class="day">{{ dayNum(cell.date) }}</span>

					<div class="pill-stack">
						<template
							v-for="ev in dayEvents(cell.iso).slice(0, maxPills)"
							:key="ev.uid"
						>
							<div class="badge ev" :class="badgeClass(ev.type)" :title="ev.title">
								<i class="pi" :class="iconClass(ev)"></i>
								<span class="time">{{ ev.start || '' }}</span>
								<span class="txt">{{ short(ev.title) }}</span>
							</div>
						</template>
						<div v-if="extraCount(cell.iso) > 0" class="badge badge--more">
							+{{ extraCount(cell.iso) }}
						</div>
					</div>
				</div>
			</template>
		</div>

		<!-- Loader -->
		<div v-if="loading" class="overlay-loading">
			<div class="dots">
				<span
					v-for="i in 5"
					:key="i"
					:style="{ animationDelay: (i - 1) * 0.08 + 's' }"
				></span>
			</div>
			<div class="loading-label">Laden</div>
		</div>
	</div>

	<!-- Day drawer -->
	<div v-if="drawerOpen" class="day-overlay" @click.self="closeDay">
		<aside class="day-drawer glass">
			<header class="drawer-head">
				<div class="drawer-title">
					<strong>{{ drawerTitle }}</strong>
					<small v-if="selectedIso">{{ selectedIso }}</small>
				</div>
				<button class="btn ghost" @click="closeDay"><i class="pi pi-times" /></button>
			</header>

			<div class="agenda" v-if="agenda.length">
				<div v-for="ev in agenda" :key="ev.uid" class="row">
					<div class="ic" :class="badgeClass(ev.type)">
						<i class="pi" :class="iconClass(ev)"></i>
					</div>
					<div class="info">
						<div class="t1">
							<span class="title-line">{{ ev.title }}</span>
							<span
								v-if="ev.type === 'action'"
								class="state-chip"
								:class="{ ok: ev.completed }"
							>
								{{ ev.completed ? 'Erledigt' : 'Offen' }}
							</span>
						</div>

						<div class="t2">
							<span v-if="ev.start" class="chip"
								><i class="pi pi-clock" /> {{ ev.start
								}}<span v-if="ev.end">–{{ ev.end }}</span></span
							>
							<span v-if="ev.meta" class="chip"
								><i class="pi pi-info-circle" /> {{ ev.meta }}</span
							>
						</div>

						<!-- Editor -->
						<div v-if="ev.type === 'action'" class="editor">
							<label class="editor-field">
								<span>Fällig am</span>
								<input
									type="date"
									:value="ev.date"
									:disabled="saving.has(ev.uid)"
									@change="onChangeDueDate(ev, $event.target.value)"
								/>
							</label>

							<button
								class="btn primary"
								:disabled="saving.has(ev.uid)"
								@click.stop="onToggleCompleted(ev)"
							>
								<i
									class="pi"
									:class="ev.completed ? 'pi-undo' : 'pi-check-circle'"
								></i>
								<span>{{
									ev.completed ? 'Als offen markieren' : 'Als erledigt markieren'
								}}</span>
							</button>
						</div>
					</div>
				</div>
			</div>

			<div v-else class="empty">Keine Ereignisse an diesem Tag</div>
		</aside>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { monthGrid, f } from '@/utils/date'
import api from '@/plugins/axios'

defineOptions({ inheritAttrs: false })

const props = defineProps({ maxPills: { type: Number, default: 3 } })

/* Mes visible */
const now = new Date()
const y = ref(now.getFullYear())
const m = ref(now.getMonth())
const dows = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So']
const weeks = computed(() => monthGrid(y.value, m.value, { weekStartsOn: 1 }))
const title = computed(() => f(new Date(y.value, m.value, 1), 'MMMM YYYY', 'de-DE'))
function prevMonth() { m.value === 0 ? (m.value = 11, y.value -= 1) : (m.value -= 1) }
function nextMonth() { m.value === 11 ? (m.value = 0, y.value += 1) : (m.value += 1) }

/* Fechas solo string */
const z = n => String(n).padStart(2, '0')
function ensureISO(val) {
  if (!val) return ''
  if (val instanceof Date) return `${val.getFullYear()}-${z(val.getMonth() + 1)}-${z(val.getDate())}`
  if (typeof val === 'string') {
    if (/^\d{4}-\d{2}-\d{2}T/.test(val)) {
      const d = new Date(val)
      return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`
    }
    return val.slice(0, 10)
  }
  return ''
}
function fromISO(val) { const s = ensureISO(val); const [Y, M, D] = s.split('-').map(Number); return new Date(Y, M - 1, D) }
function dayNum(val) { return Number(ensureISO(val).slice(8, 10)) || 1 }
function rangeISO(Y, M) { const first = `${Y}-${z(M + 1)}-01`; const last = `${Y}-${z(M + 1)}-${z(new Date(Y, M + 1, 0).getDate())}`; return { from: first, to: last } }
function nextFridayISO(iso) { const d = new Date(ensureISO(iso) + 'T00:00:00'); const wd = d.getDay(); const k = (5 - wd + 7) % 7 || 7; d.setDate(d.getDate() + k); return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}` }

/* Estado */
const loading = ref(false)
const saving = ref(new Set())
const rows = ref([])

/* Normalización */
const toBool = v => (v === true || v === 1 || v === '1' || v === 'true')
function pickItemId(r) {
  if (r.action_item_id != null) return Number(r.action_item_id)
  if (r.item_id != null) return Number(r.item_id)
  if (r.id != null && r.id !== r.action_plan_id) return Number(r.id)
  return null
}
function normalize(list) {
  return (Array.isArray(list) ? list : [])
    .flatMap(r => Array.isArray(r?.items) ? r.items : [r])
    .map((r, idx) => {
      const id = pickItemId(r)
      const date = ensureISO(r.date || r.due_date || r.day || r.iso)
      const completed = ('is_completed' in r) ? toBool(r.is_completed)
        : ('completed' in r) ? toBool(r.completed)
        : (String(r.status || '').toLowerCase() === 'completed')
      const type = (r.due_date != null) ? 'action'
        : (['detection', 'repoting', 'forecasting', 'interview'].includes(String(r.type || '').toLowerCase())
          ? String(r.type).toLowerCase() : 'action')
      return {
        uid: id != null ? `ai:${id}` : `tmp:${idx}:${date}:${r.title || ''}`,
        id,
        date,
        type,
        title: r.title || r.name || 'Aktion',
        start: r.start || '',
        end: r.end || '',
        meta: r.meta || '',
        completed
      }
    })
    .filter(e => e.date)
}

/* Indicadores fijos del mes */
const monthIndicators = computed(() => {
  const ym = `${y.value}-${z(m.value + 1)}`
  const detection = `${ym}-04`
  const repoting = `${ym}-10`
  const forecasting = `${ym}-15`
  const controlling = nextFridayISO(forecasting)
  return normalize([
    { id: `ind-${detection}-detection`, date: detection, type: 'detection', title: 'Abweichungsanalyse' },
    { id: `ind-${repoting}-repoting`, date: repoting, type: 'repoting', title: 'Reporting' },
    { id: `ind-${forecasting}-forecast`, date: forecasting, type: 'forecasting', title: 'Forecasting' },
    { id: `ind-${controlling}-interview`, date: controlling, type: 'interview', title: 'Controlling Gespräch' }
  ])
})

/* Dedupe: indicadores no se duplican */
const isIndicator = t => ['detection', 'repoting', 'forecasting', 'interview'].includes(String(t || '').toLowerCase())
function keyOf(ev) { return isIndicator(ev.type) ? `I|${ev.type}|${ev.date}` : `A|${ev.id ?? (ev.title + '|' + ev.date)}` }
function mergeUnique(base, extra) {
  const seen = new Set(base.map(keyOf))
  const out = base.slice()
  for (const ev of extra) { const k = keyOf(ev); if (!seen.has(k)) { seen.add(k); out.push(ev) } }
  return out
}

/* Fetch */
async function fetchEvents() {
  const { from, to } = rangeISO(y.value, m.value)
  loading.value = true
  try {
    const { data } = await api.get('/api/calendar/events', { params: { from, to } })
    const norm = normalize(data)
    rows.value = norm.length ? mergeUnique(norm, monthIndicators.value) : monthIndicators.value
  } catch {
    rows.value = monthIndicators.value
  } finally {
    loading.value = false
  }
}
onMounted(fetchEvents)
watch(() => [y.value, m.value], fetchEvents)

/* Indexado */
const byDay = computed(() => {
  const map = Object.create(null)
  for (const ev of rows.value) (map[ev.date] ||= []).push(ev)
  for (const k in map) map[k].sort((a, b) => (a.start || '').localeCompare(b.start || ''))
  return map
})
const dayEvents = iso => byDay.value[iso] || []
const extraCount = iso => Math.max(0, dayEvents(iso).length - props.maxPills)

/* Drawer */
const selectedIso = ref(null)
const drawerOpen = computed(() => !!selectedIso.value)
const agenda = computed(() => (selectedIso.value ? dayEvents(selectedIso.value) : []))
const drawerTitle = computed(() => selectedIso.value ? f(fromISO(selectedIso.value), 'EEEE, dd. MMM', 'de-DE') : '')
function openDay(iso) { selectedIso.value = iso }
function closeDay() { selectedIso.value = null }

/* UI */
function badgeClass(t) {
  if (t === 'detection') return 'badge--detection'
  if (t === 'interview') return 'badge--interview'
  if (t === 'repoting') return 'badge--repoting'
  if (t === 'forecasting') return 'badge--forecasting'
  if (t === 'action') return 'badge--action'
  return ''
}

/* Colores del icono según estado de acción */
const DAY_MS = 86400000
function daysUntil(dueISO) {
  const s = ensureISO(dueISO); if (!s) return 0
  const [Y, M, D] = s.split('-').map(Number)
  const due = new Date(Y, M - 1, D)
  const now = new Date()
  const today0 = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  return Math.floor((due - today0) / DAY_MS)
}
function actionStatusClass(ev) {
  if (ev?.type !== 'action') return ''
  const diff = daysUntil(ev.date)
  const overdue = diff < 0
  if (ev.completed) return overdue ? 'a-red' : 'a-green'
  if (overdue) return 'a-red'
  if (diff < 2) return 'a-yellow'
  return 'a-gray'
}
function iconClass(ev) {
  if (ev.type === 'action') {
    const icon = ev.completed ? 'pi-check-circle' : 'pi-circle'
    return [icon, actionStatusClass(ev)]
  }
  if (ev.type === 'detection') return 'pi-calendar'
  if (ev.type === 'interview') return 'pi-comment'
  if (ev.type === 'repoting') return 'pi-book'
  if (ev.type === 'forecasting') return 'pi-chart-scatter'
  return 'pi-circle'
}
function short(s) { return s?.length > 26 ? s.slice(0, 24) + '…' : s || '' }

/* Persistencia */
function idxByUid(uid) { return rows.value.findIndex(e => e.uid === uid) }
async function patchItem(ev, payload) {
  const uid = ev.uid
  const id = ev.id
  saving.value.add(uid)
  try {
    // Optimista
    const i = idxByUid(uid)
    if (i !== -1) {
      const next = { ...rows.value[i] }
      if ('due_date' in payload && payload.due_date) next.date = ensureISO(payload.due_date)
      if ('is_completed' in payload) next.completed = !!payload.is_completed
      rows.value.splice(i, 1, next)
      if (selectedIso.value && next.date && selectedIso.value !== next.date) selectedIso.value = next.date
    }
    // PATCH real si hay id
    if (id != null) {
      const body = {}
      if ('due_date' in payload && payload.due_date) body.due_date = ensureISO(payload.due_date)
      if ('is_completed' in payload) body.is_completed = payload.is_completed ? 1 : 0
      await api.patch(`/api/action-items/${id}`, body)
    }
  } finally {
    saving.value.delete(uid)
  }
}
function onToggleCompleted(ev) { if (!ev || ev.type !== 'action') return; patchItem(ev, { is_completed: !ev.completed }) }
function onChangeDueDate(ev, val) { if (!ev || ev.type !== 'action' || !val) return; patchItem(ev, { due_date: ensureISO(val) }) }
</script>

<style scoped>
/* shell */
.cal {
	padding: 12px;
	display: grid;
	gap: 10px;
	position: relative;
}
.cal-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.title {
	font-weight: 700;
	font-size: 1rem;
}
.btn {
	background: transparent;
	border: 1px solid rgba(255, 255, 255, 0.25);
	color: #e5e7eb;
	border-radius: 8px;
	padding: 4px 8px;
	cursor: pointer;
}
.btn.ghost {
	background: transparent;
	border: 1px solid rgba(255, 255, 255, 0.18);
}
.btn.primary {
	background: rgba(31, 86, 115, 0.9);
	border-color: rgba(31, 86, 115, 1);
	color: #fff;
}

/* grid */
.cal-grid {
	display: grid;
	grid-template-columns: repeat(7, 1fr);
	gap: 6px;
}
.dow {
	text-align: center;
	font-size: 0.8rem;
	color: #94a3b8;
	padding: 4px 0;
}
.cell {
	min-height: 80px;
	border: 1px solid rgba(255, 255, 255, 0.15);
	border-radius: 10px;
	background: rgba(255, 255, 255, 0.06);
	position: relative;
	padding: 6px;
	display: flex;
	flex-direction: column;
	gap: 6px;
	cursor: pointer;
	overflow: hidden;
}
.cell.off {
	opacity: 0.55;
}
.cell.today {
	outline: 2px solid #38bdf8;
}
.day {
	position: absolute;
	top: 6px;
	right: 8px;
	font-size: 0.8rem;
	color: #cbd5e1;
}

.pill-stack {
	margin-top: 18px;
	display: flex;
	flex-direction: column;
	gap: 6px;
	overflow: hidden;
}

/* pills base */
.badge {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	max-width: 100%;
	padding: 2px 8px;
	border-radius: 5px;
	font-size: 12px;
	font-weight: 600;
	line-height: 1;
	color: #cbd5e1;
	background: rgb(148 163 184 / 0.14);
	border: 1px solid rgb(148 163 184 / 0.35);
}
.badge .txt {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.badge.ev .pi {
	font-size: 0.9rem;
}
.badge.ev .time {
	font-variant-numeric: tabular-nums;
	opacity: 0.9;
	margin-right: 6px;
}
.badge.ev.done {
	opacity: 0.75; /* sin tachado */
}

/* colores de indicadores */
.badge--interview {
	color: #799f52;
	background: rgba(22, 163, 74, 0.14);
	border-color: rgba(22, 163, 74, 0.45);
}
.badge--detection {
	color: #ec1f27;
	background: rgba(239, 68, 68, 0.14);
	border-color: rgba(239, 68, 68, 0.45);
}
.badge--repoting {
	color: #f79520;
	background: rgba(245, 158, 11, 0.16);
	border-color: rgba(245, 158, 11, 0.5);
}
.badge--forecasting {
	color: #0cebdf;
	background: rgba(59, 130, 246, 0.14);
	border-color: rgba(59, 130, 246, 0.45);
}

/* acciones en gris de base */
.badge--action {
	color: #cbd5e1;
	background: rgb(148 163 184 / 0.14);
	border-color: rgb(148 163 184 / 0.35);
}
.ic.badge--action {
	color: #cbd5e1;
	background: rgba(148, 163, 184, 0.14);
	border-color: rgba(148, 163, 184, 0.35);
}

/* contador extra */
.badge--more {
	color: #cbd5e1;
	background: rgb(148 163 184 / 0.14);
	border: 1px solid rgb(148 163 184 / 0.35);
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 12px;
	font-weight: 700;
}

/* loader */
.overlay-loading {
	position: absolute;
	inset: 0;
	display: grid;
	place-items: center;
	gap: 8px;
	background: rgba(0, 0, 0, 0.25);
	border-radius: 12px;
}
.dots {
	display: flex;
	gap: 6px;
}
.dots span {
	width: 8px;
	height: 8px;
	border-radius: 999px;
	background: #e5e7eb;
	opacity: 0.85;
	animation: wave 0.9s infinite ease-in-out;
}
@keyframes wave {
	0%,
	100% {
		transform: translateY(0);
		opacity: 0.6;
	}
	50% {
		transform: translateY(-6px);
		opacity: 1;
	}
}
.loading-label {
	color: #e5e7eb;
	font-size: 0.9rem;
}

/* drawer */
.day-overlay {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.5);
	z-index: 1000;
	display: flex;
	justify-content: flex-end;
}
.day-drawer {
	width: min(520px, 92vw);
	height: 100%;
	padding: 12px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	border-left: 1px solid rgba(255, 255, 255, 0.2);
}
.drawer-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.drawer-title {
	display: flex;
	flex-direction: column;
	gap: 2px;
	color: #e5e7eb;
}
.drawer-title small {
	color: #94a3b8;
}

.agenda {
	display: flex;
	flex-direction: column;
	gap: 10px;
	overflow: auto;
}
.row {
	display: grid;
	grid-template-columns: 32px 1fr;
	gap: 10px;
	align-items: flex-start;
	padding: 8px;
	border: 1px solid rgba(255, 255, 255, 0.15);
	border-radius: 10px;
	background: rgba(255, 255, 255, 0.06);
}
.ic {
	width: 32px;
	height: 32px;
	border-radius: 8px;
	display: grid;
	place-items: center;
	border: 1px solid transparent;
}
.ic .pi {
	font-size: 1rem;
}

.ic.badge--interview {
	color: #16a34a;
	background: rgba(22, 163, 74, 0.15);
	border-color: rgba(22, 163, 74, 0.35);
}
.ic.badge--detection {
	color: #ef4444;
	background: rgba(239, 68, 68, 0.15);
	border-color: rgba(239, 68, 68, 0.35);
}
.ic.badge--repoting {
	color: #f59e0b;
	background: rgba(245, 158, 11, 0.18);
	border-color: rgba(245, 158, 11, 0.45);
}
.ic.badge--forecasting {
	color: #3b82f6;
	background: rgba(59, 130, 246, 0.15);
	border-color: rgba(59, 130, 246, 0.35);
}

.t1 {
	font-weight: 700;
	color: #e5e7eb;
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}
.state-chip {
	font-size: 0.75rem;
	padding: 2px 8px;
	border-radius: 999px;
	border: 1px solid rgba(255, 255, 255, 0.2);
	color: #e5e7eb;
}
.state-chip.ok {
	background: rgba(34, 197, 94, 0.15);
	border-color: rgba(34, 197, 94, 0.35);
}

.t2 {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin-top: 4px;
}
.chip {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	background: rgba(255, 255, 255, 0.08);
	border: 1px solid rgba(255, 255, 255, 0.18);
	color: #cbd5e1;
	padding: 2px 8px;
	border-radius: 999px;
	font-size: 0.8rem;
}

.editor {
	margin-top: 8px;
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}
.editor-field {
	display: inline-flex;
	align-items: center;
	gap: 6px;
}
.editor-field input[type='date'] {
	background: rgba(255, 255, 255, 0.08);
	color: #e5e7eb;
	border: 1px solid rgba(255, 255, 255, 0.18);
	border-radius: 6px;
	padding: 4px 8px;
}

.empty {
	color: #94a3b8;
	text-align: center;
	padding: 24px 0;
}

/* Colores del icono de acciones */
.badge--action .pi.a-gray, .ic.badge--action .pi.a-gray   { color: #94a3b8; }
.badge--action .pi.a-green, .ic.badge--action .pi.a-green  { color: #22c55e; }
.badge--action .pi.a-yellow, .ic.badge--action .pi.a-yellow{ color: #f59e0b; }
.badge--action .pi.a-red, .ic.badge--action .pi.a-red      { color: #ef4444; }

@media (max-width: 768px) {
	.day-overlay {
		justify-content: stretch;
		align-items: flex-end;
	}
	.day-drawer {
		width: 100%;
		height: 70vh;
		border-left: none;
		border-top: 1px solid rgba(255, 255, 255, 0.2);
		border-radius: 12px 12px 0 0;
	}
}
</style>
