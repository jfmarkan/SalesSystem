<template>
  <div class="border rounded bg-white shadow-sm">
    <details>
      <summary class="p-4 cursor-pointer font-semibold flex items-center gap-2">
        <span :class="['status-dot', plan.status]"></span>
        {{ plan.objective }}
        <span class="ml-2 text-sm text-gray-600">#{{ plan.id }}</span>
        <span class="ml-auto text-xs opacity-70">
          {{ labelForStatus(plan.status) }}
        </span>
      </summary>

      <div class="p-4 bg-gray-50 space-y-4">
        <div
          v-for="item in plan.action_items"
          :key="item.id"
          class="p-3 border rounded bg-white shadow-sm"
        >
          <div class="flex justify-between items-start">
            <div>
              <h4 class="font-semibold">{{ item.title }}</h4>
              <p class="text-sm text-gray-600">{{ item.description }}</p>
              <p class="text-xs text-gray-400">
                📅 Fälligkeitsdatum: {{ item.due_date || '—' }}
              </p>
              <p class="text-xs mt-1">
                <span class="pill" :class="item.status">{{ labelForStatus(item.status) }}</span>
              </p>
            </div>

            <div v-if="!readonly" class="flex gap-2">
              <button
                class="bg-yellow-500 text-white px-2 py-1 rounded text-sm"
                :disabled="savingId === item.id"
                @click="beginEdit(item)"
              >
                Bearbeiten
              </button>
              <button
                class="bg-green-600 text-white px-2 py-1 rounded text-sm"
                :disabled="savingId === item.id || item.status === 'completed'"
                @click="setStatus(item, 'completed')"
              >
                Erledigt
              </button>
              <button
                class="bg-red-600 text-white px-2 py-1 rounded text-sm"
                :disabled="savingId === item.id || item.status === 'cancelled'"
                @click="setStatus(item, 'cancelled')"
              >
                Abbrechen
              </button>
            </div>
          </div>

          <!-- Edit -->
          <div v-if="editing?.id === item.id" class="mt-3 space-y-2">
            <input v-model="editing.title" class="border p-1 w-full" placeholder="Titel" />
            <textarea v-model="editing.description" class="border p-1 w-full" placeholder="Beschreibung" />
            <input v-model="editing.due_date" type="date" class="border p-1 w-full" />
            <div class="flex gap-2">
              <button
                @click="saveEdit"
                class="bg-blue-600 text-white px-3 py-1 rounded text-sm"
                :disabled="savingId === editing.id"
              >
                Speichern
              </button>
              <button
                @click="editing = null"
                class="bg-gray-500 text-white px-3 py-1 rounded text-sm"
              >
                Abbrechen
              </button>
            </div>
          </div>
        </div>

        <!-- Empty items -->
        <div v-if="!plan.action_items.length" class="text-sm text-gray-500">
          Keine Aktionen vorhanden.
        </div>
      </div>
    </details>
  </div>
</template>

<script setup>
// Code in English; UI German.
import { ref } from 'vue'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  plan: { type: Object, required: true },
  readonly: { type: Boolean, default: false }
})
const emit = defineEmits(['item-updated'])

const toast = useToast()
const editing = ref(null)
const savingId = ref(null)

function labelForStatus(s) {
  if (s === 'completed') return 'Erledigt'
  if (s === 'cancelled') return 'Abgebrochen'
  return 'In Bearbeitung'
}

function beginEdit(item) {
  editing.value = { ...item }
}

async function saveEdit() {
  if (!editing.value) return
  try {
    savingId.value = editing.value.id
    await ensureCsrf()
    const { data } = await api.put(`/api/action-items/${editing.value.id}`, {
      title: editing.value.title,
      description: editing.value.description,
      due_date: editing.value.due_date
    })
    emit('item-updated', data) // data = updated item payload
    editing.value = null
    toast.add({ severity: 'success', summary: 'Gespeichert', detail: 'Aktion gespeichert', life: 1400 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Fehler', detail: 'Aktion konnte nicht gespeichert werden', life: 2200 })
  } finally {
    savingId.value = null
  }
}

async function setStatus(item, status) {
  try {
    savingId.value = item.id
    await ensureCsrf()
    const { data } = await api.put(`/api/action-items/${item.id}`, { status })
    emit('item-updated', data)
    toast.add({ severity: 'success', summary: 'Gespeichert', detail: 'Status aktualisiert', life: 1200 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Fehler', detail: 'Status konnte nicht aktualisiert werden', life: 2200 })
  } finally {
    savingId.value = null
  }
}
</script>

<style scoped>
.status-dot { width: 10px; height: 10px; border-radius: 999px; display: inline-block; }
.status-dot.in_progress { background: #f59e0b; } /* amber */
.status-dot.completed { background: #16a34a; }
.status-dot.cancelled { background: #dc2626; }
.pill { padding: 2px 8px; border-radius: 999px; font-size: 11px; }
.pill.in_progress { background: #fde68a; color: #92400e; }
.pill.completed { background: #bbf7d0; color: #065f46; }
.pill.cancelled { background: #fecaca; color: #7f1d1d; }
</style>
