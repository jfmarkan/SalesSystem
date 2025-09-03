<!-- src/components/profile/UserProfileCard.vue -->
<template>
  <div class="profile-card">
    <Toast />

    <!-- ÚNICO GRID: 12 cols => ID (3) + CONTACT (9) + PWD (3 debajo de ID) -->
    <div class="top-grid">
      <!-- ID-CARD (3 cols) -->
      <div class="id-card glass">
        <div class="avatar-wrap" @dragover.prevent @drop.prevent="onDrop">
          <img
            :src="avatarSrc"
            :class="['avatar', isFallback ? 'placeholder' : 'real']"
            alt="Avatar"
            @error="onImgError"
          />
          <button class="avatar-btn" type="button" @click="pickImage" :title="'Bild ändern'">
            <i class="pi pi-camera"></i>
          </button>
          <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
        </div>

        <div class="identity">
          <div class="last-name">{{ lastName || '—' }}</div>
          <div class="first-name">{{ firstName || '' }}</div>
          <div class="role"><em>{{ displayRole }}</em></div>
        </div>
      </div>

      <!-- CONTACT-CARD (9 cols) -->
      <div class="contact-card glass">
        <div class="row two">
          <div>
            <label class="lbl">E-Mail</label>
            <InputText :value="email" disabled />
          </div>
          <div>
            <label class="lbl">Telefon</label>
            <InputText v-model="form.phone" placeholder="+43 660 0000000" />
          </div>
        </div>

        <div class="row">
          <label class="lbl">Adresse</label>
          <InputText v-model="form.address" placeholder="Straße, Nr." />
        </div>

        <div class="row two">
          <div>
            <label class="lbl">Stadt</label>
            <InputText v-model="form.city" placeholder="Stadt" />
          </div>
          <div>
            <label class="lbl">PLZ</label>
            <InputText v-model="form.postal_code" placeholder="1234" />
          </div>
        </div>

        <div class="row two">
          <div>
            <label class="lbl">Bundesland</label>
            <InputText v-model="form.state" placeholder="Bundesland" />
          </div>
          <div>
            <label class="lbl">Land</label>
            <Dropdown
              v-model="form.country"
              :options="countryOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Land wählen"
              class="w-full"
            />
          </div>
        </div>

        <div class="card-spacer"></div>
        <div class="actions">
          <Button :label="saving ? 'Speichern…' : 'Änderungen speichern'" icon="pi pi-save"
                  :loading="saving" @click="saveDetails" />
          <Button label="Zurücksetzen" icon="pi pi-refresh" severity="secondary"
                  :disabled="saving || loading" @click="loadDetails" />
        </div>
      </div>

      <!-- PWD-CARD (3 cols) — debajo de ID con mismo ancho -->
      <div class="pwd-card glass">
        <h3 class="section-title">Passwort ändern</h3>

        <div class="row">
          <label class="lbl">Aktuelles Passwort</label>
          <InputText v-model="pwd.current" type="password" autocomplete="current-password" />
        </div>

        <div class="row">
          <label class="lbl">Neues Passwort</label>
          <InputText
            v-model="pwd.new"
            type="password"
            :class="{ 'is-invalid': pwd.new && !pwdStrongEnough }"
            autocomplete="new-password"
            placeholder="Mindestens 8 Zeichen"
          />
          <small v-if="pwd.new && !pwdStrongEnough" class="hint error">
            Muss mindestens 8 Zeichen lang sein.
          </small>
        </div>

        <div class="row">
          <label class="lbl">Neues Passwort (Bestätigung)</label>
          <InputText
            v-model="pwd.confirm"
            type="password"
            :class="{ 'is-invalid': pwd.confirm && !pwdMatch }"
            autocomplete="new-password"
          />
          <small v-if="pwd.confirm && !pwdMatch" class="hint error">
            Passwörter stimmen nicht überein.
          </small>
          <small v-if="pwdMatch && pwdStrongEnough && pwd.new" class="hint ok">
            ✔️ Passwörter stimmen überein.
          </small>
        </div>

        <div class="pwd-actions">
          <Button
            :label="changingPwd ? 'Speichern…' : 'Passwort speichern'"
            icon="pi pi-lock"
            :loading="changingPwd"
            :disabled="!canSavePwd || changingPwd"
            @click="savePassword"
          />
        </div>
      </div>
    </div>

    <!-- Loader -->
    <div v-if="loading" class="page-loader">
      <div class="dots"><span class="dot g"></span><span class="dot r"></span><span class="dot b"></span></div>
      <div class="caption">Lädt…</div>
    </div>
  </div>
</template>

<script setup>
// code in English; UI German
import { ref, reactive, computed, onMounted } from 'vue'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'

import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import { useAuthStore } from '@/stores/auth'

// placeholders
import femalePlaceholder from '@/assets/img/placeholders/user-female.svg'
import malePlaceholder   from '@/assets/img/placeholders/user-male.svg'

const toast = useToast()
const auth = useAuthStore()
const API = '/api'

// identity
const firstName = computed(() => auth.user?.first_name || auth.firstName || '')
const lastName  = computed(() => auth.user?.last_name  || auth.lastName  || '')
const email     = computed(() => auth.user?.email || '')
const gender    = computed(() => String(auth.user?.gender || '').toUpperCase())
const roleMap   = { 1:'Superadmin', 2:'Admin', 3:'Manager', 4:'Sales Rep' }
const roleId    = computed(() => Number(auth.roleId ?? auth.role_id ?? auth.user?.role_id ?? 0))
const displayRole = computed(() => roleMap[roleId.value] || (auth.user?.role || '—'))
const userId    = computed(() => Number(auth.user?.id ?? auth.id ?? auth.userId ?? 0))

// form
const form = reactive({
  address: '', city: '', state: '', postal_code: '', country: '', phone: '', profile_picture: null
})

// avatar
const fileInput = ref(null)
const avatarPreview = ref('')
const isFallback = ref(true)

function absolutizeUrl(url) {
  if (!url) return ''
  if (/^https?:\/\//i.test(url)) return url
  // server ya devuelve absoluta con el fix del controller, pero por si acaso:
  const base = import.meta.env.VITE_API_BASE_URL || window.location.origin
  const clean = url.replace(/^\/+/, '')
  return `${base}/${clean}`
}
function cacheBust(u) {
  if (!u) return u
  const sep = u.includes('?') ? '&' : '?'
  return `${u}${sep}t=${Date.now()}`
}
const avatarSrc = computed(() => {
  if (!isFallback.value && avatarPreview.value) return avatarPreview.value
  return (gender.value === 'F') ? femalePlaceholder : malePlaceholder
})
function onImgError() {
  isFallback.value = true
  avatarPreview.value = ''
}

const loading = ref(false)
const saving = ref(false)

// password
const pwd = reactive({ current:'', new:'', confirm:'' })
const changingPwd = ref(false)
const pwdStrongEnough = computed(() => (pwd.new?.length || 0) >= 8)
const pwdMatch = computed(() => pwd.new === pwd.confirm && pwd.new.length > 0)
const canSavePwd = computed(() => pwd.current.trim().length > 0 && pwdStrongEnough.value && pwdMatch.value)

// countries
const countryOptions = [
  { label:'Österreich', value:'AT' }, { label:'Deutschland', value:'DE' },
  { label:'Schweiz', value:'CH' },    { label:'Italien', value:'IT' },
  { label:'Frankreich', value:'FR' }, { label:'Spanien', value:'ES' }
]

// load
async function loadDetails(){
  if (!userId.value) return
  loading.value = true
  try {
    await ensureCsrf()
    const { data } = await api.get(`${API}/users/${userId.value}/details`)
    form.address = data?.address || ''
    form.city = data?.city || ''
    form.state = data?.state || ''
    form.postal_code = data?.postal_code || ''
    form.country = data?.country || ''
    form.phone = data?.phone || ''
    const raw = data?.profile_picture_url || data?.profile_picture || ''
    if (raw) {
      // usa absoluta del back + cache-buster para ver el cambio al instante
      avatarPreview.value = cacheBust(absolutizeUrl(raw))
      isFallback.value = false
    }
  } finally { loading.value = false }
}

// save details (mantiene preview y fuerza refresco)
async function saveDetails(){
  if (!userId.value) return
  saving.value = true
  const currentPreview = avatarPreview.value
  try {
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

    const persisted = data?.profile_picture_url || data?.profile_picture || ''
    if (persisted) {
      avatarPreview.value = cacheBust(absolutizeUrl(persisted))
      isFallback.value = false
    } else if (form.profile_picture instanceof File) {
      avatarPreview.value = cacheBust(currentPreview)
      isFallback.value = false
    }

    toast.add({ severity:'success', summary:'Gespeichert', detail:'Profil aktualisiert', life:1600 })
  } catch {
    toast.add({ severity:'error', summary:'Fehler', detail:'Speichern fehlgeschlagen', life:2200 })
  } finally { saving.value = false }
}

// save password
async function savePassword(){
  if (!userId.value || !canSavePwd.value) return
  changingPwd.value = true
  try {
    await ensureCsrf()
    await api.put(`${API}/users/${userId.value}/password`, {
      current_password: pwd.current,
      password: pwd.new,
      password_confirmation: pwd.confirm
    })
    pwd.current = ''; pwd.new = ''; pwd.confirm = ''
    toast.add({ severity:'success', summary:'Gespeichert', detail:'Passwort aktualisiert', life:1600 })
  } catch (e){
    const msg = e?.response?.data?.message || 'Passwort konnte nicht geändert werden'
    toast.add({ severity:'error', summary:'Fehler', detail: msg, life:2200 })
  } finally { changingPwd.value = false }
}

// avatar handlers
function pickImage(){ fileInput.value?.click() }
function onFile(e){
  const f = e.target.files?.[0]; if (!f) return
  form.profile_picture = f
  avatarPreview.value = cacheBust(URL.createObjectURL(f)) // ver al toque
  isFallback.value = false
}
function onDrop(e){
  const f = e.dataTransfer?.files?.[0]; if (!f) return
  form.profile_picture = f
  avatarPreview.value = cacheBust(URL.createObjectURL(f))
  isFallback.value = false
}

onMounted(loadDetails)
</script>

<style scoped>
.profile-card{
  position:relative;
  padding:24px;
  width:100%;
  max-width:1200px;
  margin:0 auto;
}

/* Glass */
.glass{
  background: rgba(255,255,255,0.4);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(0,0,0,0.08);
  border-radius: 14px;
  box-shadow: 0 2px 10px rgba(0,0,0,.12);
  overflow: hidden;
}

/* 12-col grid: ID(3), CONTACT(9), PWD(3 debajo de ID) */
.top-grid{
  display:grid;
  grid-template-columns: repeat(12, 1fr);
  gap:24px;
}

.id-card{
  grid-column: span 3;
  padding:18px;
  display:flex; flex-direction:column; align-items:center; gap:16px;
}
.contact-card{
  grid-column: span 9;
  padding:18px;
  display:flex; flex-direction:column; gap:14px; min-height: 320px;
}
.pwd-card{
  grid-column: span 3; /* MISMO ANCHO QUE ID */
  padding:20px;
}

/* Avatar */
.avatar-wrap{ position:relative; width:160px; height:160px; margin-top: 24px; margin-bottom: 28px; }
.avatar{
  width:160px; height:160px; object-fit: cover;
  background: transparent; /* transparente */
}
.avatar.real{ border-radius: 50%; object-fit: cover; }
.avatar.placeholder{ border-radius: 10px; object-fit: contain; }

/* Botón de cambiar imagen: centrado abajo con leve superposición */
.avatar-btn{
  position:absolute;
  left:50%; transform: translate(-50%, 50%);
  bottom:0;
  width:40px; height:40px;
  border-radius:50%;
  border:none; background:#54849A; color:#fff; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 2px 8px rgba(0,0,0,.25);
}
.hidden{ display:none; }

/* Identidad */
.identity{ text-align:center; display:flex; flex-direction:column; gap:6px; margin-top:6px; }
.last-name{ font-weight:800; font-size:1.7rem; color:#0f172a; }
.first-name{ font-weight:400; font-size:1.25rem; color:#111827; }
.role{ font-weight:300; font-style:italic; color:#334155; }

/* Contacto */
.row{ display:flex; flex-direction:column; gap:8px; }
.row.two{ display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
.lbl{ font-size:.95rem; color:#334155; font-weight:600; }

/* Acciones al borde inferior derecho de la tarjeta de contacto */
.card-spacer{ flex:1 1 auto; }
.actions{ display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top: 6px; }

/* Password */
.section-title{ margin:0 0 16px; font-size:1.08rem; font-weight:800; color:#0f172a; }
.hint{ font-size:.82rem; margin-top:4px; }
.hint.error{ color:#b91c1c; }
.hint.ok{ color:#0f766e; }
.is-invalid{ border-color:#b91c1c !important; box-shadow:0 0 0 1px rgba(185,28,28,.15) inset; }
.pwd-actions{ display:flex; justify-content:flex-end; margin-top: 20px; }

/* Loader */
.page-loader{
  position:fixed; inset:0; display:flex; align-items:center; justify-content:center;
  flex-direction:column; gap:10px; pointer-events:none;
}
.dots{ display:flex; gap:10px; align-items:center; justify-content:center; }
.dot{ width:10px; height:10px; border-radius:50%; opacity:.9; animation:bounce 1s infinite ease-in-out; box-shadow:0 2px 6px rgba(0,0,0,.25); }
.dot.g{ background:#05A46F; animation-delay:0s; } .dot.r{ background:#B01513; animation-delay:.15s; } .dot.b{ background:#54849A; animation-delay:.30s; }
@keyframes bounce{ 0%,80%,100%{ transform:translateY(0) scale(1); opacity:.8 } 40%{ transform:translateY(-8px) scale(1.05); opacity:1 } }

/* Prime width fix */
:deep(.p-inputtext), :deep(.p-dropdown){ width:100%; }

/* MOBILE: TODO APILADO UNO BAJO EL OTRO */
@media (max-width: 1024px){
  .top-grid{ grid-template-columns: 1fr; }
  .id-card, .contact-card, .pwd-card{ grid-column: 1 / -1; }
}
</style>
