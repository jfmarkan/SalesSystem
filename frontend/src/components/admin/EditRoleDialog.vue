<template>
  <Dialog v-model:visible="visible" :modal="true" :style="{width:'420px'}" header="Rolle ändern">
    <div class="mb-2 text-700">Benutzer: <b>{{ userName }}</b></div>
    <div class="p-float-label">
      <Dropdown id="role" v-model="localRole" :options="roles" />
      <label for="role">Rolle</label>
    </div>
    <template #footer>
      <Button label="Abbrechen" severity="secondary" text @click="visible=false"/>
      <Button label="Speichern" icon="pi pi-check" severity="warning" :disabled="!localRole" @click="confirm"/>
    </template>
  </Dialog>
</template>

<script setup>
// Code & comments in English
import { computed, ref, watch, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import api from '@/plugins/axios'

const props = defineProps({ visible: Boolean, user: Object, roles: { type: Array, default: () => [] } })
const emit = defineEmits(['update:visible', 'confirm'])

const visible = computed({ get:()=>props.visible, set:v=>emit('update:visible', v) })
const localRole = ref(null)
const userName = computed(() => (props.user?.name || `${props.user?.first_name||''} ${props.user?.last_name||''}`).trim())

watch(() => props.user, (u) => { localRole.value = u?.roles?.[0] || null }, { immediate: true })

// Fallback fetch if no roles provided
onMounted(async () => { if (!props.roles?.length) { try { await api.get('/api/sales-force/roles') } catch {} } })

function confirm(){
  emit('confirm', { userId: props.user.id, role: localRole.value })
  visible.value = false
}
</script>
