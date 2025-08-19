<template>
  <div class="deviations-wrapper">
    <Toast />

    <!-- Title / Tabs -->
    <div class="title-glass">
      <div class="title-bar">
        <h2 class="m-0">Abweichungen</h2>
        <div class="right">
          <div class="tabs">
            <button class="tab" :class="{ active: tab === 'open' }" @click="tab = 'open'">
              Offen <span class="badge">{{ openList.length }}</span>
            </button>
            <button class="tab" :class="{ active: tab === 'just' }" @click="tab = 'just'">
              Begründet <span class="badge">{{ closedList.length }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- List (natural height, no inner scroll) -->
    <div class="list-wrap">
      <div v-if="loading" class="local-loader">
        <div class="dots">
          <span class="dot g"></span><span class="dot r"></span><span class="dot b"></span>
        </div>
        <div class="caption">Wird geladen…</div>
      </div>

      <template v-else>
        <template v-if="tab === 'open'">
          <template v-if="openList.length">
            <DeviationItem
              v-for="dev in openList"
              :key="dev.id"
              :dev="dev"
              :saving="savingId === dev.id"
              :readonly="false"
              @save="onSave"
            />
          </template>
          <div v-else class="empty">Keine offenen Abweichungen.</div>
        </template>

        <template v-else>
          <template v-if="closedList.length">
            <DeviationItem
              v-for="dev in closedList"
              :key="'j-' + dev.id"
              :dev="dev"
              :readonly="true"
            />
          </template>
          <div v-else class="empty">Keine begründeten Abweichungen.</div>
        </template>
      </template>
    </div>
  </div>
</template>

<script setup>
// Code in English; UI German.
import { ref, computed, onMounted } from 'vue'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import DeviationItem from '@/components/elements/DeviationItem.vue'

const toast = useToast()

const deviations = ref([])
const loading = ref(false)
const savingId = ref(null)
const tab = ref('open')

const openList = computed(() => deviations.value.filter((d) => !d.justified))
const closedList = computed(() => deviations.value.filter((d) => d.justified))

function normalizeDev(d) {
  return {
    id: d.id,
    type: String(d.type || 'sales').toLowerCase(),
    clientName: d.clientName || '',
    pcCode: d.pcCode || '',
    pcName: d.pcName || '',
    year: Number(d.year || 0),
    month: Number(d.month || 0),
    sales: Number(d.sales ?? 0),
    budget: Number(d.budget ?? 0),
    forecast: Number(d.forecast ?? 0),
    deltaAbs: Number(d.deltaAbs ?? 0),
    deltaPct: Number(d.deltaPct ?? 0),
    comment: d.comment || '',
    plan: d.plan || null,
    actions: Array.isArray(d.actions) ? d.actions : [],
    justified: !!d.justified,
    months: d.months || null,
    salesSeries: d.salesSeries || null,
    budgetSeries: d.budgetSeries || null,
    forecastSeries: d.forecastSeries || null,
  }
}

async function loadDeviations() {
  loading.value = true
  try {
    await ensureCsrf()
    const { data } = await api.get('/api/deviations')
    deviations.value = Array.isArray(data) ? data.map(normalizeDev) : []
  } catch {
    deviations.value = []
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

async function onSave(payload) {
  const { id, comment, plan, actions } = payload
  savingId.value = id
  try {
    await ensureCsrf()
    await api.put(`/api/deviations/${id}/justify`, { comment, plan, actions })
    const idx = deviations.value.findIndex((d) => d.id === id)
    if (idx >= 0) {
      deviations.value[idx] = {
        ...deviations.value[idx],
        justified: true,
        comment,
        plan: plan ?? deviations.value[idx].plan,
        actions: Array.isArray(actions) ? actions : deviations.value[idx].actions,
      }
    }
    toast.add({
      severity: 'success',
      summary: 'Gespeichert',
      detail: 'Begründung gespeichert',
      life: 1600,
    })
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
.deviations-wrapper {
  width: 100%;
  /* Let BODY be the only scroller; no fixed heights here */
}

.title-glass {
  background: rgba(255, 255, 255, 0.4);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.25);
  border-radius: 12px;
  margin-bottom: 12px;
}
.title-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
}
.right {
  display: flex;
  align-items: center;
  gap: 12px;
}
.tabs {
  display: flex;
  gap: 8px;
}
.tab {
  background: rgba(0, 0, 0, 0.3);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 999px;
  padding: 6px 10px;
  font-weight: 600;
}
.tab.active {
  background: rgba(84, 132, 154, 1);
}
.badge {
  margin-left: 6px;
  background: rgba(0, 0, 0, 0.5);
  border-radius: 999px;
  padding: 2px 6px;
  font-size: 0.85em;
  color: #fff;
}

.list-wrap {
  position: relative;
  overflow: visible; /* no inner scroll; body handles it */
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.local-loader {
  position: fixed;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.dots {
  display: flex;
  gap: 10px;
  align-items: center;
  justify-content: center;
}
.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  opacity: 0.9;
  animation: bounce 1s infinite ease-in-out;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
}
.dot.g {
  background: #22c55e;
  animation-delay: 0s;
}
.dot.r {
  background: #ef4444;
  animation-delay: 0.15s;
}
.dot.b {
  background: #3b82f6;
  animation-delay: 0.3s;
}
@keyframes bounce {
  0%,
  80%,
  100% {
    transform: translateY(0) scale(1);
    opacity: 0.8;
  }
  40% {
    transform: translateY(-8px) scale(1.05);
    opacity: 1;
  }
}
.caption {
  font-size: 0.9rem;
  color: #e5e7eb;
  opacity: 0.9;
}
.empty {
  padding: 18px;
  text-align: center;
  color: #e5e7eb;
  opacity: 0.9;
}
</style>
