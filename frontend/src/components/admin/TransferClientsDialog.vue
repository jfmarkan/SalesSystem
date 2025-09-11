<template>
  <Dialog v-model:visible="visible" :modal="true" :style="{width:'1000px'}" header="Kunden übertragen">
    <div class="grid">
      <div class="col-12 md:col-4">
        <div class="p-float-label mb-3">
          <SelectButton v-model="mode" :options="modeOptions" optionLabel="label" optionValue="value" />
        </div>

        <div v-if="mode !== 'perRow'" class="p-float-label mb-3">
          <Dropdown id="globalTarget" v-model="globalTarget" :options="targets" optionLabel="name" optionValue="id" />
          <label for="globalTarget">Zielbenutzer</label>
        </div>

        <Message v-if="mode==='all'" severity="info" :closable="false">Alle Kunden des Benutzers werden übertragen.</Message>
        <Message v-if="mode==='pick'" severity="info" :closable="false">Nur ausgewählte Kunden werden übertragen.</Message>
        <Message v-if="mode==='perRow'" severity="info" :closable="false">Pro Zeile einen Zielbenutzer wählen.</Message>
      </div>

      <div class="col-12 md:col-8">
        <div class="flex align-items-center justify-content-between mb-2">
          <span class="font-semibold">Kundenliste</span>
          <span class="p-input-icon-left">
            <i class="pi pi-search" />
            <InputText v-model="search" placeholder="Suchen" />
          </span>
        </div>

        <DataTable
          :value="filteredRows"
          dataKey="assignmentId"
          v-model:selection="selection"
          :selectionMode="mode==='pick' ? 'checkbox' : null"
          :loading="loading"
          :rows="10"
          paginator
          responsiveLayout="scroll"
          :rowHover="true"
          :emptyMessage="'Keine Kunden'"
        >
          <Column v-if="mode==='pick'" selectionMode="multiple" headerStyle="width:3rem" />
          <Column field="assignmentId" header="Zuweisung-ID" style="width:140px"/>
          <Column field="clientName" header="Kunde"/>
          <Column v-if="mode==='perRow'" header="Zielbenutzer" style="width:280px">
            <template #body="{ data }">
              <Dropdown
                v-model="rowTargets[data.assignmentId]"
                :options="targets"
                optionLabel="name"
                optionValue="id"
                placeholder="Auswählen"
                class="w-full"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <template #footer>
      <Button label="Abbrechen" severity="secondary" text @click="close"/>
      <Button label="Bestätigen" icon="pi pi-check" :disabled="!canConfirm" @click="confirm"/>
    </template>
  </Dialog>
</template>

<script setup>
// Code & comments in English
import { computed, ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import api from '@/plugins/axios'

const props = defineProps({
  visible: Boolean,
  user: Object,                       // expects { id }
  targets: { type: Array, default: () => [] } // [{id,name}]
})
const emit = defineEmits(['update:visible', 'confirm'])

const visible = computed({ get: () => props.visible, set: v => emit('update:visible', v) })

const mode = ref('all')
const modeOptions = [{ label: 'Alle', value: 'all' }, { label: 'Auswahl', value: 'pick' }, { label: 'Pro Zeile', value: 'perRow' }]

const loading = ref(false)
const rows = ref([])
const selection = ref([])
const search = ref('')
const globalTarget = ref(null)
const rowTargets = ref({})

const filteredRows = computed(() => {
  const s = (search.value || '').toLowerCase()
  return rows.value.filter(r =>
    String(r.assignmentId).includes(s) ||
    (r.clientName || '').toLowerCase().includes(s)
  )
})

const canConfirm = computed(() => {
  if (!props.user?.id) return false
  if (mode.value === 'all') return !!globalTarget.value
  if (mode.value === 'pick') return !!globalTarget.value && selection.value.length > 0
  if (mode.value === 'perRow') return filteredRows.value.every(r => !!rowTargets.value[r.assignmentId])
  return false
})

function close(){ visible.value = false }

async function loadClients() {
  if (!props.user?.id) return
  loading.value = true
  const { data } = await api.get('/api/sales-force/clients', { params: { userId: props.user.id } })
  rows.value = (data || []).map(d => ({
    assignmentId: d.assignmentId ?? d.id ?? d.assignment_id,
    clientProfitCenterId: d.clientProfitCenterId ?? d.client_profit_center_id,
    clientName: d.clientName ?? d.client_name ?? d.name ?? 'Kunde'
  }))
  selection.value = []
  rowTargets.value = {}
  globalTarget.value = null
  loading.value = false
}

watch(visible, async (open) => {
  if (open) {
    mode.value = 'all'
    search.value = ''
    await loadClients()  // ✅ ensures loading when opening
  }
})

function confirm(){
  const fromUserId = props.user.id
  if (mode.value === 'perRow') {
    const perMap = new Map()
    filteredRows.value.forEach(r => {
      const dest = rowTargets.value[r.assignmentId]
      if (!dest) return
      if (!perMap.has(dest)) perMap.set(dest, [])
      perMap.get(dest).push(r.assignmentId)
    })
    const batches = Array.from(perMap.entries()).map(([toUserId, assignmentIds]) => ({ toUserId, assignmentIds }))
    emit('confirm', { mode: 'perRow', fromUserId, batches })
  } else if (mode.value === 'pick') {
    emit('confirm', { mode: 'pick', fromUserId, toUserId: globalTarget.value, assignmentIds: selection.value.map(s => s.assignmentId) })
  } else {
    emit('confirm', { mode: 'all', fromUserId, toUserId: globalTarget.value })
  }
  visible.value = false
}
</script>