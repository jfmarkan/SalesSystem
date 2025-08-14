<script setup>
/* UI in German. Comments in English. */
import { ref, reactive, onMounted } from 'vue'
import InputText from 'primevue/inputtext'
import Button from 'primevue/button'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'

import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const toast = useToast()
const API = '/api' // adjust if no /api prefix

/* State: user core + details */
const user = reactive({ id:null, name:'', email:'' })
const details = reactive({
  address:'', city:'', state:'', postal_code:'', country:'', phone:'',
  profile_picture:null, profile_picture_url:null
})
const original = ref({})
const saving = ref(false)
const uploading = ref(false)

/* Load profile from backend */
async function loadProfile(){
  await ensureCsrf()
  const { data } = await api.get(`${API}/me/profile`)
  user.id = data.user.id
  user.name = data.user.name
  user.email = data.user.email
  Object.assign(details, {
    address: data.details?.address || '',
    city: data.details?.city || '',
    state: data.details?.state || '',
    postal_code: data.details?.postal_code || '',
    country: data.details?.country || '',
    phone: data.details?.phone || '',
    profile_picture: data.details?.profile_picture || null,
    profile_picture_url: data.details?.profile_picture_url || null
  })
  // keep snapshot for reset
  original.value = {
    name: user.name, ...JSON.parse(JSON.stringify(details))
  }
}

/* Save profile changes */
async function save(){
  try{
    saving.value = true
    await ensureCsrf()
    await api.put(`${API}/me/profile`, {
      name: user.name,
      address: details.address,
      city: details.city,
      state: details.state,
      postal_code: details.postal_code,
      country: details.country,
      phone: details.phone
    })
    toast.add({ severity:'success', summary:'Gespeichert', detail:'Profil aktualisiert', life:1800 })
    original.value = { name: user.name, ...JSON.parse(JSON.stringify(details)) }
  }catch{
    toast.add({ severity:'error', summary:'Fehler', detail:'Speichern fehlgeschlagen', life:2200 })
  }finally{
    saving.value = false
  }
}

/* Reset to last loaded values */
function reset(){
  user.name = original.value.name
  Object.assign(details, JSON.parse(JSON.stringify(original.value)))
}

/* Upload profile picture */
async function onPickFile(ev){
  const file = ev.target.files?.[0]
  if(!file) return
  const fd = new FormData()
  fd.append('file', file)
  try{
    uploading.value = true
    await ensureCsrf()
    const { data } = await api.post(`${API}/me/profile/picture`, fd, {
      headers: { 'Content-Type':'multipart/form-data' }
    })
    details.profile_picture = data.path
    details.profile_picture_url = data.url
    toast.add({ severity:'success', summary:'Hochgeladen', detail:'Profilbild aktualisiert', life:1800 })
  }catch{
    toast.add({ severity:'error', summary:'Fehler', detail:'Upload fehlgeschlagen', life:2200 })
  }finally{
    uploading.value = false
    ev.target.value = '' // reset file input
  }
}

onMounted(loadProfile)
</script>

<template>
  <div class="profile-wrapper">
    <Toast />

    <!-- Header Card -->
    <div class="glass-card header-card">
      <div class="header-left">
        <div class="avatar-wrap">
          <img v-if="details.profile_picture_url" :src="details.profile_picture_url" alt="Profilbild" />
          <div v-else class="avatar-placeholder">{{ user.name?.charAt(0)?.toUpperCase() || '?' }}</div>
          <label class="upload-btn">
            <input type="file" accept="image/*" @change="onPickFile" :disabled="uploading" />
            <i class="pi pi-camera mr-2" /> {{ uploading ? 'Lädt…' : 'Bild ändern' }}
          </label>
        </div>
        <div class="user-core">
          <div class="label">Name</div>
          <InputText v-model="user.name" class="w-full" />
          <div class="label mt-3">E-Mail</div>
          <InputText v-model="user.email" class="w-full" disabled />
        </div>
      </div>
      <div class="header-right">
        <div class="actions">
          <Button :label="saving ? 'Speichert…' : 'Speichern'" icon="pi pi-save" :disabled="saving" @click="save" />
          <Button label="Zurücksetzen" icon="pi pi-refresh" severity="secondary" class="ml-2" @click="reset" />
        </div>
      </div>
    </div>

    <!-- Details Card -->
    <div class="glass-card details-card">
      <div class="grid-2">
        <div class="field">
          <label>Adresse</label>
          <InputText v-model="details.address" class="w-full" placeholder="Straße und Nr." />
        </div>
        <div class="field">
          <label>Stadt</label>
          <InputText v-model="details.city" class="w-full" />
        </div>
        <div class="field">
          <label>Bundesland</label>
          <InputText v-model="details.state" class="w-full" />
        </div>
        <div class="field">
          <label>PLZ</label>
          <InputText v-model="details.postal_code" class="w-full" />
        </div>
        <div class="field">
          <label>Land</label>
          <InputText v-model="details.country" class="w-full" />
        </div>
        <div class="field">
          <label>Telefon</label>
          <InputText v-model="details.phone" class="w-full" />
        </div>
      </div>

      <div class="form-actions">
        <Button :label="saving ? 'Speichert…' : 'Speichern'" icon="pi pi-save" :disabled="saving" @click="save" />
        <Button label="Zurücksetzen" icon="pi pi-refresh" severity="secondary" class="ml-2" @click="reset" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.profile-wrapper{
  width: calc(100vw - 70px);
  padding: 12px;
}

/* Glass cards consistent with your style */
.glass-card{
  background: rgba(255,255,255,0.4);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  box-shadow: 0 2px 4px rgba(0,0,0,0.4);
  border-radius: 12px;
  padding: 14px;
}

.header-card{
  display: flex;
  justify-content: space-between;
  align-items: stretch;
  gap: 16px;
  margin-bottom: 12px;
}

.header-left{
  display: flex;
  gap: 16px;
  align-items: center;
  flex: 1;
}

.avatar-wrap{
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  min-width: 140px;
}
.avatar-wrap img{
  width: 112px; height: 112px; object-fit: cover; border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
.avatar-placeholder{
  width: 112px; height: 112px; display:flex; align-items:center; justify-content:center;
  border-radius: 12px; font-weight:700; font-size: 36px; color:#111827;
  background: rgba(255,255,255,0.6); backdrop-filter: blur(8px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
.upload-btn{
  font-size: .85rem; color:#111827; cursor: pointer; display:flex; align-items:center;
  background: rgba(255,255,255,0.5); padding: 6px 10px; border-radius: 8px;
  border: 1px solid rgba(0,0,0,0.06);
}
.upload-btn input{ display:none; }

.user-core{ min-width: 240px; flex: 1; }
.label{ font-size: .8rem; color:#6b7280; margin-bottom: 6px; }

.header-right{ display:flex; align-items:flex-start; }
.actions{ display:flex; }


.grid-2{
  display: grid;
  grid-template-columns: repeat(2, minmax(240px, 1fr));
  gap: 14px;
}
.field label{
  display:block; font-size:.85rem; color:#6b7280; margin-bottom:6px;
}

.form-actions{
  display:flex; justify-content:flex-end; margin-top: 16px;
}

/* Responsive */
@media (max-width: 960px){
  .header-card{ flex-direction: column; align-items: stretch; }
  .grid-2{ grid-template-columns: 1fr; }
}
</style>