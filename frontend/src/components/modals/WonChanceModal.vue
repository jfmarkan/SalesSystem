<!-- WonChanceModal.vue -->
<template>
  <Dialog
    v-model:visible="innerVisible"
    :modal="true"
    :draggable="false"
    header="Chance gewonnen"
    :style="{ width: '560px' }"
  >
    <div style="min-height: 240px;">
      <!-- Klassifizierung -->
      <div class="field mb-2">
        <label class="lbl">Kundenartikelklassifizierung</label>
        <Dropdown
          v-model="localClassificationId"
          :options="visibleClassificationOptions"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          :disabled="clientFound"
          placeholder="Bitte wählen…"
        />
        <small v-if="clientFound" class="text-muted">
          Klassifizierung des bestehenden Kunden wird übernommen.
        </small>
      </div>

      <!-- Kundennummer -->
      <div class="field mb-2">
        <label class="lbl">Kundennummer</label>
        <InputText
          v-model="numInput"
          class="w-full"
          placeholder="z. B. 12345"
          :class="{ 'p-invalid': !!wonError }"
        />
        <small v-if="wonError" class="text-danger">{{ wonError }}</small>
        <small v-else class="text-muted">Nur 10000–19999.</small>
      </div>

      <!-- Kundenname -->
      <div class="field mb-2">
        <label class="lbl">Kundenname</label>
        <InputText
          v-model="nameInput"
          class="w-full"
          placeholder="Firmenname"
          :disabled="clientFound"
        />
      </div>

      <!-- Konfliktbox -->
      <div v-if="wonConflict" class="conflict-box">
        <span class="pi pi-exclamation-triangle mr-2"></span>
        Kunde + Profitcenter existieren bereits. Das ist <b>Forecast</b>.
      </div>
    </div>

    <!-- Fußleiste -->
    <template #footer>
      <div class="flex align-items-center justify-content-between w-full" style="gap: 10px;">
        <div />
        <div class="flex gap-2">
          <Button label="Abbrechen" severity="secondary" @click="emit('update:visible', false)" />
          <Button
            v-if="wonConflict"
            label="+ Forecast"
            icon="pi pi-plus"
            severity="warning"
            :loading="mergeLoading"
            @click="onMerge"
          />
          <Button
            v-else
            label="Übernehmen"
            icon="pi pi-check"
            :loading="finalizing"
            :disabled="finalizeDisabled"
            @click="onFinalize"
          />
        </div>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'

/** PROPS */
const props = defineProps({
  visible: { type: Boolean, default: false },
  lookupClientByNumber: { type: Function, required: true },   // (num)=>Promise<{name, classification_id} | null>
  checkClientPcExists: { type: Function, required: true },     // (num,pc)=>Promise<boolean>
  initialClientNumber: { type: [String, Number], default: '' },
  initialClientName:   { type: String, default: '' },
  initialClassificationId: { type: [Number, null], default: null },
  profitCenterCode: { type: [Number, String], default: null },
  fiscalYear: { type: [Number, String], default: null },
  /** lista completa 1..7 */
  classificationOptions: {
    type: Array,
    default: () => ([
      { label: 'A Kunde',     value: 1 },
      { label: 'B Kunde',     value: 2 },
      { label: 'C Kunde',     value: 3 },
      { label: 'D Kunde',     value: 4 },
      { label: 'X Kunde',     value: 5 },
      { label: 'Potenzial A', value: 6 },
      { label: 'Potenzial B', value: 7 },
    ]),
  },
})

const emit = defineEmits(['update:visible', 'finalize', 'merge-forecast'])

/* State */
const innerVisible = computed({
  get: () => props.visible,
  set: (v) => emit('update:visible', v),
})
const numInput = ref(String(props.initialClientNumber || ''))
const nameInput = ref(props.initialClientName || '')
const localClassificationId = ref(props.initialClassificationId ?? null)

const clientFound = ref(false)
const dbClassificationId = ref(null)
const wonConflict = ref(false)
const wonError = ref('')

const finalizing = ref(false)
const mergeLoading = ref(false)

/* Cache para no perder el nombre manual del usuario */
const initialName = props.initialClientName || ''
const lastManualName = ref(initialName)
watch(nameInput, (v) => { if (!clientFound.value) lastManualName.value = v })

/* Helpers */
const isValidClientNumber = (val) => {
  const s = String(val || '').trim()
  if (!/^\d{5}$/.test(s)) return false
  const n = Number(s)
  return n >= 10000 && n <= 19999
}
function resetValidation() {
  wonConflict.value = false
  wonError.value = ''
}

/* Klassifizierungs-Options sichtbare */
const visibleClassificationOptions = computed(() => {
  // Kunde existiert → mostrar solo su clase (una opción)
  if (clientFound.value && dbClassificationId.value != null) {
    const opt = props.classificationOptions.find(o => o.value === Number(dbClassificationId.value))
    return opt ? [opt] : []
  }
  // Nuevo cliente → sólo potencial A/B
  return props.classificationOptions.filter(o => o.value === 6 || o.value === 7)
})

/* Habilitar botón: reglas mínimas */
const finalizeDisabled = computed(() => {
  if (!isValidClientNumber(numInput.value)) return true
  if (!String(nameInput.value || '').trim()) return true
  if (wonConflict.value) return true
  // nuevo cliente: debe elegir Potencial A/B (mostramos sólo esas 2)
  if (!clientFound.value && ![6,7].includes(Number(localClassificationId.value))) return true
  // existente: no bloquear; tomamos clasificación de DB (si no hay, impedir)
  if (clientFound.value && dbClassificationId.value == null) return true
  return false
})

/* Al abrir: reset limpio */
watch(
  () => props.visible,
  (vis) => {
    if (!vis) return
    numInput.value = String(props.initialClientNumber || '')
    nameInput.value = props.initialClientName || ''
    lastManualName.value = nameInput.value || initialName
    localClassificationId.value = props.initialClassificationId ?? null
    clientFound.value = false
    dbClassificationId.value = null
    resetValidation()
    if (numInput.value) runLookupDebounced()
  },
  { immediate: true },
)

/* Lookup + PC-Konflikt (debounced) */
let timer = null
watch(numInput, () => runLookupDebounced())
function runLookupDebounced() {
  resetValidation()
  if (timer) clearTimeout(timer)
  timer = setTimeout(async () => {
    const s = String(numInput.value || '').trim()
    if (!s) {
      clientFound.value = false
      dbClassificationId.value = null
      localClassificationId.value = null
      nameInput.value = lastManualName.value || initialName
      return
    }
    if (!isValidClientNumber(s)) {
      clientFound.value = false
      dbClassificationId.value = null
      localClassificationId.value = null
      wonError.value = 'Die Nummer muss zwischen 10000 und 19999 liegen.'
      nameInput.value = lastManualName.value || initialName
      return
    }

    const cgNum = Number(s)
    // buscar cliente
    const c = await props.lookupClientByNumber(cgNum)
    // Sólo considerar "existente" si realmente hay datos de DB
    const exists = !!(c && (c.client_group_number != null || c.id != null))
    if (exists) {
      clientFound.value = true
      dbClassificationId.value = c.classification_id ?? null
      localClassificationId.value = dbClassificationId.value // para que el dropdown muestre la opción única
      if (String(c.name || '').trim()) {
        nameInput.value = String(c.name).trim()    // bloquear con nombre de DB
      }
      // conflicto cliente+pc
      const pc = Number(props.profitCenterCode || 0)
      if (pc) {
        const existsRel = await props.checkClientPcExists(cgNum, pc)
        if (existsRel) {
          wonConflict.value = true
          wonError.value = 'Kunde + Profitcenter existieren bereits. Das ist Forecast.'
        }
      }
    } else {
      // no existe: liberar edición y restaurar el último nombre manual
      clientFound.value = false
      dbClassificationId.value = null
      localClassificationId.value = null
      nameInput.value = lastManualName.value || initialName
    }
  }, 220)
}

/* Actions */
async function onFinalize() {
  finalizing.value = true
  try {
    const numStr = String(numInput.value || '').trim()
    if (!isValidClientNumber(numStr)) throw new Error('Kundennummer muss zwischen 10000 und 19999 liegen.')
    const cgNum = Number(numStr)
    const cn = String(nameInput.value || '').trim()
    if (!cn) throw new Error('Kundenname erforderlich.')

    const cls = clientFound.value ? (dbClassificationId.value ?? null) : (localClassificationId.value ?? null)
    if (!clientFound.value && ![6, 7].includes(Number(cls))) throw new Error('Bitte Potenzial (A/B) auswählen.')
    if (clientFound.value && cls == null) throw new Error('Klassifizierung des bestehenden Kunden fehlt.')

    // asegurar no-conflicto last-second
    const pc = Number(props.profitCenterCode || 0)
    if (pc) {
      const conflict = await props.checkClientPcExists(cgNum, pc)
      if (conflict) {
        wonConflict.value = true
        throw new Error('Kunde+Profitcenter existieren bereits. Das ist Forecast.')
      }
    }

    emit('finalize', {
      client_group_number: cgNum,
      client_name: cn,
      classification_id: clientFound.value ? dbClassificationId.value : localClassificationId.value,
      clientFound: clientFound.value,
    })
  } catch (e) {
    wonError.value = e?.message || 'Fehler beim Finalisieren'
  } finally {
    finalizing.value = false
  }
}

async function onMerge() {
  mergeLoading.value = true
  try {
    const s = String(numInput.value || '').trim()
    if (!isValidClientNumber(s)) throw new Error('Ungültige Nummer (10000–19999).')
    emit('merge-forecast', { client_group_number: Number(s) })
  } catch (e) {
    wonError.value = e?.message || 'Fehler beim Hinzufügen des Forecasts'
  } finally {
    mergeLoading.value = false
  }
}
</script>

<style scoped>
.lbl { color: #0f172a; font-weight: 600; }
.text-danger { color: #b91c1c; }
.text-muted { color: #64748b; }
.conflict-box {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.35);
  color: #92400e;
  padding: 10px;
  border-radius: 8px;
  margin-top: 8px;
}
</style>
