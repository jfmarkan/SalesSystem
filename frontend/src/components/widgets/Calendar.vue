<template>
    <div class="cal glass">
        <!-- Header -->
        <div class="cal-head">
            <button class="btn" @click="prevMonth" aria-label="Previous month">
                <i class="pi pi-chevron-left" />
            </button>
            <div class="title">{{ title }}</div>
            <button class="btn" @click="nextMonth" aria-label="Next month">
                <i class="pi pi-chevron-right" />
            </button>
        </div>

        <!-- Grid -->
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
                    <span class="day">{{ new Date(cell.date).getDate() }}</span>

                    <div class="pill-stack">
                        <template v-for="ev in dayEvents(cell.iso).slice(0, maxPills)" :key="ev.id">
                            <div class="badge ev" :class="badgeClass(ev.type)" :title="ev.title">
                                <i class="pi" :class="iconFor(ev.type)"></i>
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
            <div class="loading-label">Loading</div>
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
                <div v-for="ev in agenda" :key="ev.id" class="row">
                    <div class="ic" :class="badgeClass(ev.type)">
                        <i class="pi" :class="iconFor(ev.type)"></i>
                    </div>
                    <div class="info">
                        <div class="t1">{{ ev.title }}</div>
                        <div class="t2">
                            <span v-if="ev.start" class="chip"
                                ><i class="pi pi-clock" /> {{ ev.start
                                }}<span v-if="ev.end">–{{ ev.end }}</span></span
                            >
                            <span v-if="ev.teacher" class="chip"
                                ><i class="pi pi-user" /> {{ ev.teacher }}</span
                            >
                            <span v-if="ev.venue" class="chip"
                                ><i class="pi pi-map-marker" /> {{ ev.venue }}</span
                            >
                            <span v-if="ev.meta" class="chip"
                                ><i class="pi pi-info-circle" /> {{ ev.meta }}</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="empty">No events for this day</div>
        </aside>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { monthGrid, f } from '@/utils/date'
import { useAuthStore } from '@/stores/auth'
import api from '@/plugins/axios'

const props = defineProps({ maxPills: { type: Number, default: 3 } })

/* Visible month */
const now = new Date()
const y = ref(now.getFullYear())
const m = ref(now.getMonth())
const dows = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const weeks = computed(() => monthGrid(y.value, m.value, { weekStartsOn: 1 }))
const title = computed(() => f(new Date(y.value, m.value, 1), 'MMMM YYYY', 'en-US'))
function prevMonth() {
    m.value === 0 ? ((m.value = 11), (y.value -= 1)) : (m.value -= 1)
}
function nextMonth() {
    m.value === 11 ? ((m.value = 0), (y.value += 1)) : (m.value += 1)
}

/* Events */
const auth = useAuthStore()
const events = ref([])
const loading = ref(false)

function rangeISO(Y, M) {
    const first = new Date(Y, M, 1),
        last = new Date(Y, M + 1, 0)
    const z = (n) => String(n).padStart(2, '0')
    const iso = (d) => `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`
    return { from: iso(first), to: iso(last) }
}
function normalize(list) {
    return (Array.isArray(list) ? list : [])
        .map((r, idx) => ({
            id: r.id ?? idx,
            date: r.date || r.day || r.iso || '',
            start: r.start || r.time || r.starts_at || '',
            end: r.end || r.ends_at || '',
            type: mapType(r.type || r.kind || r.category),
            title: r.title || r.name || '',
            teacher: r.teacher || r.coach || r.professor || '',
            venue: r.venue || r.location || r.site || r.branch || '',
            meta: r.meta || '',
        }))
        .filter((e) => e.date)
}
function mapType(t) {
    const s = String(t || '').toLowerCase()
    if (['res', 'reservation', 'class', 'tee', 'tee_time'].includes(s)) return 'class'
    if (['torneo', 'tournament', 'event'].includes(s)) return 'tournament'
    if (['mag', 'magazine', 'revista'].includes(s)) return 'magazine'
    if (['health', 'fisio', 'physio', 'kine', 'kinesiologia', 'kinesiology'].includes(s))
        return 'health'
    return 'class'
}
async function fetchEvents() {
    const { from, to } = rangeISO(y.value, m.value)
    loading.value = true
    try {
        const { data } = await api.get('/api/calendar/events', { params: { from, to } })
        events.value = normalize(data)
    } catch {
        // demo
        const ym = from.slice(0, 7)
        events.value = normalize([
            {
                date: `${ym}-02`,
                type: 'class',
                title: 'Academy · Short game',
                start: '10:00',
                teacher: 'Ana',
                venue: 'HQ',
            },
            {
                date: `${ym}-02`,
                type: 'health',
                title: 'Physio session',
                start: '12:30',
                teacher: 'Dr. Ruiz',
                venue: 'Clinic',
            },
            {
                date: `${ym}-06`,
                type: 'tournament',
                title: 'Monthly Open',
                start: '08:00',
                venue: 'North Course',
            },
            { date: `${ym}-15`, type: 'magazine', title: 'Digital Magazine', start: '09:00' },
            { date: `${ym}-22`, type: 'class', title: 'Tee time', start: '14:10', venue: 'West' },
            {
                date: `${ym}-22`,
                type: 'class',
                title: 'Private class',
                start: '16:00',
                teacher: 'Luis',
                venue: 'HQ',
            },
        ])
    } finally {
        loading.value = false
    }
}
onMounted(fetchEvents)
watch(() => [y.value, m.value], fetchEvents)

/* Index */
const byDay = computed(() => {
    const map = Object.create(null)
    for (const ev of events.value) {
        ;(map[ev.date] ||= []).push(ev)
    }
    for (const k in map) map[k].sort((a, b) => (a.start || '').localeCompare(b.start || ''))
    return map
})
const dayEvents = (iso) => byDay.value[iso] || []
const extraCount = (iso) => Math.max(0, dayEvents(iso).length - props.maxPills)

/* Drawer */
const selectedIso = ref(null)
const drawerOpen = computed(() => !!selectedIso.value)
const agenda = computed(() => (selectedIso.value ? dayEvents(selectedIso.value) : []))
const drawerTitle = computed(() =>
    selectedIso.value ? f(new Date(selectedIso.value), 'EEEE, MMM dd', 'en-US') : '',
)
function openDay(iso) {
    selectedIso.value = iso
}
function closeDay() {
    selectedIso.value = null
}

/* UI helpers */
function badgeClass(t) {
    if (t === 'class') return 'badge--class'
    if (t === 'tournament') return 'badge--tournament'
    if (t === 'magazine') return 'badge--magazine'
    if (t === 'health') return 'badge--health'
    return ''
}
function iconFor(t) {
    if (t === 'class') return 'pi-calendar'
    if (t === 'tournament') return 'pi-trophy'
    if (t === 'magazine') return 'pi-book'
    if (t === 'health') return 'pi-heart'
    return 'pi-circle'
}
function short(s) {
    return s?.length > 26 ? s.slice(0, 24) + '…' : s || ''
}
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
}
.btn.ghost {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.18);
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
    min-height: 96px;
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
    overflow: hidden; /* ✅ no rompe columnas */
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

/* variants (colores) */
.badge--tournament {
    color: #799F52;
    background: rgba(22, 163, 74, 0.14);
    border-color: rgba(22, 163, 74, 0.45);
}
.badge--class {
    color: #EC1F27;
    background: rgba(239, 68, 68, 0.14);
    border-color: rgba(239, 68, 68, 0.45);
}

.badge--magazine {
    color: #F79520;
    background: rgba(245, 158, 11, 0.16);
    border-color: rgba(245, 158, 11, 0.5);
}
.badge--health {
    color: #0CEBDF;
    background: rgba(59, 130, 246, 0.14);
    border-color: rgba(59, 130, 246, 0.45);
}

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

/* drawer color chips for icon box */
.ic.badge--tournament {
    color: #16a34a;
    background: rgba(22, 163, 74, 0.15);
    border-color: rgba(22, 163, 74, 0.35);
}
.ic.badge--class {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.35);
    
}
.ic.badge--magazine {
    color: #f59e0b;
    background: rgba(245, 158, 11, 0.18);
    border-color: rgba(245, 158, 11, 0.45);
}
.ic.badge--health {
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.35);
}

.t1 {
    font-weight: 700;
    color: #e5e7eb;
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
.empty {
    color: #94a3b8;
    text-align: center;
    padding: 24px 0;
}

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
