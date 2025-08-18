<template>
  <div class="profile-page">
    <!-- Header -->
    <div class="glass header-card">
      <div class="header-left">
        <div class="avatar-wrap" @dragover.prevent @drop.prevent="onDrop">
          <img :src="avatarPreview || fallbackAvatar" class="avatar" alt="Avatar" />
          <button class="avatar-btn" type="button" @click="pickImage">
            <i class="pi pi-camera"></i> Bild ändern
          </button>
          <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
        </div>
        <div class="id-block">
          <div class="name-line">
            <span class="last">{{ lastName || '—' }}</span>
            <span class="first">{{ firstName || '' }}</span>
          </div>
          <div class="role-line"><em>{{ displayRole }}</em></div>
          <div class="email-line">{{ email }}</div>
        </div>
      </div>

      <div class="header-right">
        <Button :label="saving ? 'Speichern…' : 'Änderungen speichern'" icon="pi pi-save"
                :loading="saving" @click="saveAll" />
        <Button label="Zurücksetzen" icon="pi pi-refresh" severity="secondary" class="ml-2"
                :disabled="saving || loading" @click="resetForm" />
        <Button v-if="hasRecord" label="Details löschen" icon="pi pi-trash" severity="danger" class="ml-2"
                :disabled="saving || loading" @click="removeAll" />
      </div>
    </div>

    <!-- Body -->
    <div class="grid-2">
      <GlassCard class="section-card">
        <h3 class="section-title">Kontakt</h3>
        <div class="form-grid">
          <div class="col">
            <label class="lbl">Telefon</label>
            <InputText v-model="form.phone" placeholder="+43 660 0000000" />
          </div>
          <div class="col">
            <label class="lbl">E-Mail</label>
            <InputText :value="email" disabled />
          </div>
        </div>
      </GlassCard>

      <GlassCard class="section-card">
        <h3 class="section-title">Adresse</h3>
        <div class="form-grid">
          <div class="col col-2">
            <label class="lbl">Anschrift</label>
            <InputText v-model="form.address" placeholder="Straße, Nr." />
          </div>
          <div class="col">
            <label class="lbl">PLZ</label>
            <InputText v-model="form.postal_code" placeholder="1234" />
          </div>
          <div class="col">
            <label class="lbl">Stadt</label>
            <InputText v-model="form.city" placeholder="Stadt" />
          </div>
          <div class="col">
            <label class="lbl">Bundesland</label>
            <InputText v-model="form.state" placeholder="Bundesland" />
          </div>
          <div class="col">
            <label class="lbl">Land</label>
            <Dropdown v-model="form.country" :options="countryOptions" optionLabel="label" optionValue="value"
                      placeholder="Land wählen" class="w-full" />
          </div>
        </div>
      </GlassCard>
    </div>

    <!-- Page loader -->
    <div v-if="loading" class="page-loader">
      <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
      <div class="caption">Lädt Profil…</div>
    </div>
  </div>
</template>

<script setup>
/* Profile editor wired to /api/users/{user}/details routes */
import { ref, reactive, computed, onMounted } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import GlassCard from '@/components/ui/GlassCard.vue'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import { useAuthStore } from '@/stores/auth'

const toast = useToast()
const auth = useAuthStore()
const API = '/api'

/* Identity */
const firstName = computed(() => auth.firstName || auth.user?.first_name || (auth.user?.name?.split(' ')[0] ?? ''))
const lastName  = computed(() => auth.lastName  || auth.user?.last_name  || (auth.user?.name?.split(' ').slice(1).join(' ') ?? ''))
const email     = computed(() => auth.user?.email || '')
const roleMap   = { 1:'Superadmin', 2:'Admin', 3:'Manager', 4:'Sales Rep' }
const roleId    = computed(() => Number(auth.roleId ?? auth.role_id ?? auth.user?.role_id ?? 0))
const displayRole = computed(() => roleMap[roleId.value] || (auth.user?.role || '—'))
const userId    = computed(() => Number(auth.user?.id ?? auth.id ?? auth.userId ?? 0))

/* Form */
const form = reactive({
  address: '', city: '', state: '', postal_code: '',
  country: '', phone: '', profile_picture: null
})
const hasRecord = ref(false)

/* Avatar */
const fileInput = ref(null)
const avatarPreview = ref('')
const fallbackAvatar = '/default-avatar.svg'

/* Flags */
const loading = ref(false)
const saving = ref(false)

/* Countries */
const countryOptions = [
  { label:'Österreich', value:'AT' }, { label:'Deutschland', value:'DE' },
  { label:'Schweiz', value:'CH' },    { label:'Italien', value:'IT' },
  { label:'Frankreich', value:'FR' }, { label:'Spanien', value:'ES' },
  { label:'Portugal', value:'PT' },   { label:'Polen', value:'PL' }
]

/* Load details via GET /api/users/{id}/details */
async function loadDetails(){
  if (!userId.value) return
  loading.value = true
  try{
    await ensureCsrf()
    const { data } = await api.get(`${API}/users/${userId.value}/details`)
    hasRecord.value = !!data
    form.address = data?.address || ''
    form.city = data?.city || ''
    form.state = data?.state || ''
    form.postal_code = data?.postal_code || ''
    form.country = data?.country || ''
    form.phone = data?.phone || ''
    avatarPreview.value = data?.profile_picture_url || data?.profile_picture || ''
  } catch {
    hasRecord.value = false
  } finally {
    loading.value = false
  }
}

/* Save via POST /api/users/{id}/details (multipart) */
async function saveAll(){
  if (!userId.value) return
  saving.value = true
  try{
    await ensureCsrf()
    const fd = new FormData()
    fd.append('address', form.address ?? '')
    fd.append('city', form.city ?? '')
    fd.append('state', form.state ?? '')
    fd.append('postal_code', form.postal_code ?? '')
    fd.append('country', form.country ?? '')
    fd.append('phone', form.phone ?? '')
    if (form.profile_picture instanceof File) fd.append('profile_picture', form.profile_picture)

    const { data } = await api.post(`${API}/users/${userId.value}/details`, fd, {
      headers: { 'Content-Type':'multipart/form-data' }
    })
    hasRecord.value = true
    // server may return updated URL
    avatarPreview.value = data?.profile_picture_url || avatarPreview.value
    toast.add({ severity:'success', summary:'Gespeichert', detail:'Profil aktualisiert', life:1800 })
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Speichern fehlgeschlagen', life:2500 })
  } finally {
    saving.value = false
  }
}

/* Delete via DELETE /api/users/{id}/details */
async function removeAll(){
  if (!userId.value) return
  if (!confirm('Profil-Details wirklich löschen?')) return
  saving.value = true
  try{
    await ensureCsrf()
    await api.delete(`${API}/users/${userId.value}/details`)
    hasRecord.value = false
    resetLocal()
    toast.add({ severity:'success', summary:'Gelöscht', detail:'Details entfernt', life:1600 })
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Löschen fehlgeschlagen', life:2500 })
  } finally {
    saving.value = false
  }
}

/* Helpers */
function resetLocal(){
  form.address = ''; form.city = ''; form.state = ''; form.postal_code = ''
  form.country = ''; form.phone = ''; form.profile_picture = null
  avatarPreview.value = ''
}
function resetForm(){ loadDetails() }
function pickImage(){ fileInput.value?.click() }
function onFile(e){
  const f = e.target.files?.[0]; if (!f) return
  form.profile_picture = f; avatarPreview.value = URL.createObjectURL(f)
}
function onDrop(e){
  const f = e.dataTransfer?.files?.[0]; if (!f) return
  form.profile_picture = f; avatarPreview.value = URL.createObjectURL(f)
}

onMounted(loadDetails)
</script>

<style scoped>
.profile-page{ position:relative; width:calc(100vw - 70px); padding:12px; }
.grid-2{ display:grid; grid-template-columns: repeat(12, 1fr); gap:12px; margin-top:12px; }
.section-card{ grid-column: span 12; } @media (min-width:1024px){ .section-card{ grid-column: span 6; } }

.glass{
  background: rgba(255,255,255,0.40);
  backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
  border: 1px solid rgba(0,0,0,0.08); box-shadow: 0 2px 6px rgba(0,0,0,0.25); border-radius: 12px;
}

.header-card{ display:flex; align-items:center; justify-content:space-between; padding:12px; }
.header-left{ display:flex; align-items:center; gap:14px; }
.avatar-wrap{ position:relative; width:84px; height:84px; }
.avatar{ width:84px; height:84px; border-radius:50%; object-fit:cover; border:2px solid rgba(0,0,0,0.1); box-shadow: 0 2px 6px rgba(0,0,0,0.25); }
.avatar-btn{ position:absolute; bottom:-6px; right:-6px; font-size:.75rem; border:none; border-radius:999px; padding:6px 10px; cursor:pointer; background:#54849A; color:#fff; box-shadow:0 2px 6px rgba(0,0,0,0.25); }
.hidden{ display:none; }

.id-block{ display:flex; flex-direction:column; gap:4px; }
.name-line{ display:flex; gap:8px; align-items:baseline; }
.name-line .last{ font-weight:800; font-size:1.2rem; letter-spacing:.2px; }
.name-line .first{ font-weight:600; font-size:1.05rem; opacity:.95; }
.role-line{ font-size:.85rem; color:#334155; }
.email-line{ font-size:.85rem; color:#64748B; }
.header-right{ display:flex; align-items:center; }

.section-title{ margin:0 0 10px 0; font-size:1.05rem; font-weight:700; color:#111; }
.form-grid{ display:grid; grid-template-columns: repeat(12, 1fr); gap:10px; }
.col{ grid-column: span 12; } .col-2{ grid-column: span 12; }
@media (min-width:768px){ .col{ grid-column: span 6; } .col-2{ grid-column: span 12; } }
.lbl{ display:block; font-size:.85rem; color:#475569; margin-bottom:6px; }

/* Page loader */
.page-loader{ position:fixed; inset:70px 0 0 70px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; pointer-events:none; }
.dots{ display:flex; gap:10px; align-items:center; justify-content:center; }
.dot{ width:10px; height:10px; border-radius:50%; opacity:.9; animation:bounce 1s infinite ease-in-out; box-shadow:0 2px 6px rgba(0,0,0,0.25); }
.dot.g{ background:#05A46F; animation-delay:0s; } .dot.r{ background:#B01513; animation-delay:.15s; } .dot.b{ background:#54849A; animation-delay:.30s; }
@keyframes bounce{ 0%,80%,100%{ transform:translateY(0) scale(1); opacity:.8 } 40%{ transform:translateY(-8px) scale(1.05); opacity:1 } }
.caption{ font-size:.95rem; color:#334155; }

:deep(.p-inputtext), :deep(.p-dropdown){ width:100%; }
</style>
