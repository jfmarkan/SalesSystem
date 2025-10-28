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

/** Quita máscara “miles con punto / coma decimal” y devuelve ENTERO */
function parseMaskedInt(v) {
  if (typeof v === 'number' && Number.isFinite(v)) return Math.round(v)
  if (typeof v !== 'string') return 0
  // ejemplo: "1.275.271,99" -> "1275271" -> 1275271
  const noDots = v.replace(/\./g, '')
  const beforeComma = noDots.split(',')[0]
  const onlyDigits = beforeComma.replace(/[^\d-]/g, '')
  if (onlyDigits === '' || onlyDigits === '-' ) return 0
  return Math.round(parseInt(onlyDigits, 10))
}

const toNumArray = (arr) => Array.isArray(arr) ? arr.map(parseMaskedInt) : null

function normalizeDev(d) {
  return {
    id: d.id,
    type: String(d.type || 'sales').toLowerCase(),
    clientName: d.clientName || '',
    pcCode: d.pcCode || '',
    pcName: d.pcName || '',
    year: parseMaskedInt(d.year ?? 0),
    month: parseMaskedInt(d.month ?? 0),

    // ← AQUÍ el fix: NUNCA Number() sobre "1.200"
    sales: parseMaskedInt(d.sales ?? 0),
    budget: parseMaskedInt(d.budget ?? 0),
    forecast: parseMaskedInt(d.forecast ?? 0),
    deltaAbs: parseMaskedInt(d.deltaAbs ?? 0),
    deltaPct: parseMaskedInt(d.deltaPct ?? 0), // si viene 0–100, lo dejamos entero

    comment: d.comment || '',
    plan: d.plan || null,
    actions: Array.isArray(d.actions) ? d.actions : [],
    justified: !!d.justified,

    months: Array.isArray(d.months) ? d.months : null,

    // Normalizamos series para charts (evita 1.2):
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
    toast.add({ severity: 'success', summary: 'Gespeichert', detail: 'Begründung gespeichert', life: 1600 })
  } catch {
    toast.add({ severity: 'error', summary: 'Fehler', detail: 'Begründung konnte nicht gespeichert werden', life: 2500 })
  } finally {
    savingId.value = null
  }
}

onMounted(loadDeviations)
</script>

