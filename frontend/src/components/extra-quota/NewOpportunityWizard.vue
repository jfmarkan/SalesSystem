<template>
  <div>
    <Dialog
      v-model:visible="visibleModel"
      modal
      :draggable="false"
      header="Neue Verkaufschance"
      :style="{ width: '720px' }"
    >
      <div class="grid-form">
        <label>User-ID</label>
        <InputNumber v-model="form.user_id" :min="1" :useGrouping="false" inputId="uid" />

        <label>Geschäftsjahr</label>
        <InputNumber v-model="form.fiscal_year" :min="2000" :max="2100" :useGrouping="false" inputId="fy" />

        <label>Profitcenter-Code</label>
        <InputText v-model="form.profit_center_code" />

        <label>Volumen</label>
        <InputNumber v-model="form.opportunity_ammount" :min="0" :step="0.01" mode="decimal" :minFractionDigits="2" :maxFractionDigits="2" />

        <label>Wahrscheinlichkeit</label>
        <div class="slider">
          <Slider v-model="form.probability_pct" :min="0" :max="100" @slideend="onSliderChange" />
          <span class="pct">{{ form.probability_pct }}%</span>
        </div>

        <label>Start (Schätzung)</label>
        <Calendar v-model="form.estimated_start_date" dateFormat="yy-mm-dd" showIcon />

        <label>Pot. Kunde</label>
        <InputText v-model="form.potential_client_name" />

        <label>Kundengruppe Nr.</label>
        <InputText v-model="form.client_group_number" />

        <label>Kommentare</label>
        <InputTextarea v-model="form.comments" rows="3" autoResize />
      </div>

      <template #footer>
        <Button label="Abbrechen" class="p-button-text" icon="pi pi-times" @click="onCancel" />
        <Button :label="creating ? 'Speichern…' : 'Erstellen'" :icon="creating ? 'pi pi-spin pi-spinner' : 'pi pi-check'" @click="submitCreate" />
      </template>

      <!-- Inline planner appears after create + slider touched + amount > 0 -->
      <div v-if="showGrid" class="grid-holder">
        <h4>Budget & Forecast planen</h4>
        <BudgetForecastGridLight
          :group-id="createdGroupId"
          :version="1"
          :estimated-start-date="isoDate(form.estimated_start_date)"
        />
      </div>
    </Dialog>

    <!-- Also render a compact “inline card” when used standalone -->
    <div v-if="!dialogMode" class="inline-card">
      <Button label="Neu erstellen" icon="pi pi-plus" @click="visibleModel = true" />
    </div>
  </div>
</template>

<script setup>
// Code in English; UI German.
import { ref, computed, watch } from 'vue'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'


const props = defineProps({
  modelValue: { type: Boolean, default: false },
  visible: { type: Boolean, default: undefined },  // for v-model:visible from parent
  userId: { type: [Number, String], default: null },
  fiscalYear: { type: Number, default: new Date().getFullYear() }
})
const emit = defineEmits(['update:visible', 'created'])

const toast = useToast()
const dialogMode = ref(true) // rendered as Dialog mostly
const visibleModel = ref(props.visible ?? props.modelValue ?? false)

const creating = ref(false)
const created = ref(false)
const sliderTouched = ref(false)
const createdGroupId = ref(null)

const form = ref({
  user_id: props.userId || null,
  fiscal_year: props.fiscalYear,
  profit_center_code: '',
  opportunity_ammount: 0,
  probability_pct: 0,
  estimated_start_date: null,
  comments: '',
  potential_client_name: '',
  client_group_number: ''
})

const readyToPlan = computed(() => Number(form.value.opportunity_ammount) > 0 && sliderTouched.value)
const showGrid = computed(() => created.value && readyToPlan.value)

function isoDate(d) {
  if (!d) return null
  const dt = new Date(d)
  if (Number.isNaN(dt.getTime())) return null
  return dt.toISOString().slice(0, 10)
}

async function submitCreate() {
  creating.value = true
  try {
    const payload = {
      ...form.value,
      estimated_start_date: isoDate(form.value.estimated_start_date)
    }
    const { data } = await api.post('/api/extra-quota/opportunities', payload)
    created.value = true
    createdGroupId.value = data.opportunity_group_id
    emit('created', { groupId: data.opportunity_group_id })
    toast.add({ severity: 'success', summary: 'Gespeichert', detail: 'Neue Chance erstellt', life: 1400 })
  } catch {
    toast.add({ severity: 'error', summary: 'Fehler', detail: 'Erstellung fehlgeschlagen', life: 2000 })
  } finally { creating.value = false }
}

function onSliderChange() { sliderTouched.value = true }

function onCancel() { visibleModel.value = false }

watch(() => props.visible, v => { if (typeof v === 'boolean') visibleModel.value = v })
watch(visibleModel, v => emit('update:visible', v))
watch(() => [props.userId, props.fiscalYear], () => {
  form.value.user_id = props.userId || null
  form.value.fiscal_year = props.fiscalYear
})
</script>

<style scoped>
.grid-form { display:grid; grid-template-columns: 200px 1fr; gap:10px 12px; margin:8px 0; }
.slider { display:flex; gap:10px; align-items:center; }
.pct { width:42px; text-align:right; }
.grid-holder { margin-top:12px; border-top:1px dashed rgba(255,255,255,0.15); padding-top:12px; }
.inline-card { padding:6px 0; }
</style>