<template>
  <div class="dashboard-bg">
    <div class="dashboard-layout">
      <header class="dashboard-header glass-nav">
        <div class="logo-container">
          <img src="@/assets/img/logos/stb.png" alt="Logo" class="logo" />
        </div>

        <div class="nav-buttons">
          <!-- Dashboard -->
          <Button icon="pi pi-th-large" class="nav-icon-button" @click="$router.push('/dashboard')" />

          <!-- SALES: managers/admins -> dropdown; sales_rep -> inline items -->
          <template v-if="isManagerOrUp">
            <Menu ref="salesMenu" :model="salesItems" popup />
            <Button icon="pi pi-chart-line" class="nav-icon-button" @click="salesMenu.toggle($event)" />
          </template>
          <template v-else>
            <div class="nav-item-wrap" title="Prognosen">
              <Button icon="pi pi-chart-line" class="nav-icon-button" @click="$router.push('/forecasts')" />
            </div>
            <div class="nav-item-wrap" title="Budgetfälle">
              <Button icon="pi pi-briefcase" class="nav-icon-button" @click="$router.push('/budget-cases')" />
            </div>
            <div class="nav-item-wrap" title="Abweichungen">
              <Button icon="pi pi-sliders-h" class="nav-icon-button" @click="$router.push('/deviations')" />
              <span v-if="deviationsCount>0" class="nav-badge">{{ deviationsCount }}</span>
            </div>
            <div class="nav-item-wrap" title="Zusatzquoten">
              <Button icon="pi pi-percentage" class="nav-icon-button" @click="$router.push('/extra-quotas')" />
            </div>
            <div class="nav-item-wrap" title="Aktionspläne">
              <Button icon="pi pi-list-check" class="nav-icon-button" @click="$router.push('/action-plans')" />
            </div>
          </template>

          <!-- REPORTS / LOGISTICS / CLAIMS solo para manager+ -->
          <template v-if="isManagerOrUp">
            <Menu ref="reportsMenu" :model="reportsItems" popup />
            <Button icon="pi pi-file" class="nav-icon-button" @click="reportsMenu.toggle($event)" />
            <Menu ref="logisticsMenu" :model="logisticsItems" popup />
            <Button icon="pi pi-truck" class="nav-icon-button" @click="logisticsMenu.toggle($event)" />
            <Menu ref="claimsMenu" :model="claimsItems" popup />
            <Button icon="pi pi-exclamation-triangle" class="nav-icon-button" @click="claimsMenu.toggle($event)" />
          </template>

          <!-- USER: glass dropdown con header personalizado -->
          <Menu ref="userMenu" :model="userItems" popup>
            <template #item="{ item, props }">
              <!-- Header del usuario -->
              <div v-if="item.type==='header'" class="user-menu-header">
                <div class="name-line">
                  <span class="last">{{ lastName || '—' }}</span>
                  <span class="first">{{ firstName }}</span>
                </div>
                <div class="role-line">{{ displayRole }}</div>
                <div class="email-line">{{ auth.user?.email }}</div>
              </div>

              <!-- Separador estándar -->
              <div v-else-if="item.separator" class="p-menu-separator"></div>

              <!-- Ítems acción con glass claro -->
              <a v-else v-bind="props.action" class="user-menu-item">
                <i :class="['pi', item.icon, item.danger ? 'danger-icon' : '']"></i>
                <span class="ml-2">{{ item.label }}</span>
              </a>
            </template>
          </Menu>
          <Button icon="pi pi-user" class="nav-icon-button" @click="userMenu.toggle($event)" />
        </div>
      </header>

      <main class="dashboard-content">
        <router-view />
      </main>
    </div>
  </div>

  <InviteUserModal :visible="showInviteModal" @close="showInviteModal = false" />
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import InviteUserModal from '@/components/modals/InviteUserModal.vue'
import Menu from 'primevue/menu'
import Button from 'primevue/button'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const router = useRouter()
const auth = useAuthStore()

const showInviteModal = ref(false)

/* Menus refs */
const salesMenu = ref()
const reportsMenu = ref()
const logisticsMenu = ref()
const claimsMenu = ref()
const userMenu = ref()

/* Roles */
const roleId = computed(() => Number(auth.roleId ?? auth.role_id ?? auth.user?.role_id ?? 0))
const rolesList = computed(() => (auth.roles ?? auth.user?.roles ?? []).map(r => String(r).toLowerCase()))
const isManagerOrUp = computed(() =>
  [1,2,3].includes(roleId.value) || rolesList.value.some(r => ['manager','admin','superadmin'].includes(r))
)
const isSalesRep = computed(() =>
  roleId.value === 4 || rolesList.value.some(r => ['sales_rep','employee','empleado'].includes(r))
)

/* Nombre / Apellido con tolerancia */
const firstName = computed(() => auth.firstName || auth.user?.first_name || (auth.user?.name?.split(' ')[0] ?? ''))
const lastName  = computed(() => auth.lastName  || auth.user?.last_name  || (auth.user?.name?.split(' ').slice(1).join(' ') ?? ''))

/* Rol visible con mapeo fallback */
const roleMap = { 1:'Superadmin', 2:'Admin', 3:'Manager', 4:'Sales Rep' }
const rawRole = computed(() => auth.user?.role || (auth.roles?.[0] ?? '') || roleMap[roleId.value] || '')
const displayRole = computed(() => {
  const r = String(rawRole.value || '').toLowerCase()
  if (['sales_rep','salesrep','employee','empleado'].includes(r)) return 'Sales Rep'
  if (['manager'].includes(r)) return 'Manager'
  if (['admin','administrator'].includes(r)) return 'Admin'
  if (['superadmin','owner','root'].includes(r)) return 'Superadmin'
  return rawRole.value || '—'
})

/* Sales dropdown model (manager+) */
const salesItems = [
  { label: 'Prognosen', icon: 'pi pi-chart-line', command: () => router.push('/forecasts') },
  { label: 'Budgetfälle', icon: 'pi pi-briefcase', command: () => router.push('/budget-cases') },
  { label: 'Abweichungen', icon: 'pi pi-sliders-h', command: () => router.push('/deviations') },
  { label: 'Zusatzquoten', icon: 'pi pi-percentage', command: () => router.push('/extra-quotas') },
  { label: 'Aktionspläne', icon: 'pi pi-list-check', command: () => router.push('/action-plans') },
]

/* Reports / Logistics / Claims (manager+) */
const reportsItems = [
  { label: 'Profitcenter-Bericht', icon: 'pi pi-file', command: () => router.push('/reports/pc') },
  { label: 'Gesamtbericht', icon: 'pi pi-file-o', command: () => router.push('/reports/total') },
]
const logisticsItems = [
  { label: 'Eingehende Bestellungen', icon: 'pi pi-inbox', command: () => {} },
  { label: 'Lieferstatus', icon: 'pi pi-send', command: () => {} },
]
const claimsItems = [
  { label: 'Offene Reklamationen', icon: 'pi pi-exclamation-circle', command: () => {} },
  { label: 'Gelöste Reklamationen', icon: 'pi pi-check-circle', command: () => {} },
]

/* USER menu model con header + acciones */
const userItems = computed(() => ([
  { type: 'header' },
  { separator: true },
  { label: 'Profil bearbeiten', icon: 'pi pi-pen-to-square', command: () => router.push('/profile') },
  { label: 'Dashboard bearbeiten', icon: 'pi pi-th-large', command: () => router.push('/dashboard/edit') },
  { separator: true },
  {
    label: 'Abmelden',
    icon: 'pi pi-sign-out',
    danger: true,
    command: async () => { await auth.logout(); router.push('/login') }
  }
]))

/* Deviations badge para sales_rep */
const deviationsCount = ref(0)
let intervalId = null
async function refreshDeviationCount(){
  if (!isSalesRep.value) { deviationsCount.value = 0; return }
  try{
    await ensureCsrf()
    const { data } = await api.get('/api/deviations')
    deviationsCount.value = Array.isArray(data) ? data.filter(d => !d.justified).length : 0
  } catch { deviationsCount.value = 0 }
}
onMounted(async () => {
  await refreshDeviationCount()
  intervalId = setInterval(refreshDeviationCount, 60000)
})
onBeforeUnmount(() => { if (intervalId) clearInterval(intervalId) })
</script>

<style scoped>
.dashboard-layout { display: flex; flex-direction: column; min-height: 100vh; }

.dashboard-bg {
  background-image: url('@/assets/img/backgrounds/red.jpg');
  background-repeat: repeat;
  background-position: center;
  background-attachment: fixed;
  min-height: 100vh; width: 100%;
}
.dashboard-bg::after { content: ""; position: absolute; inset: 0; background: rgba(0, 0, 0, 0.4); z-index: 1; }

.dashboard-header {
  position: fixed; width: 100%; top: 0; z-index: 10;
  display: flex; justify-content: space-between; align-items: center;
  padding: 0.5rem 2rem;
  background: rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}
.logo { height: 30px; }

.nav-buttons { display: flex; align-items: center; gap: .25rem; }
.nav-item-wrap { position: relative; }

.nav-icon-button {
  background: transparent !important;
  border: 1px solid transparent !important;
  color: #000 !important;
  box-shadow: none !important;
  padding: 0.4rem; border-radius: 8px;
  transition: border-color .25s ease, color .25s ease;
}
:deep(.nav-icon-button .p-button-icon) { color: #000 !important; transition: color .25s ease; }
.nav-icon-button:hover { background: transparent !important; border-color: #fff !important; color: #fff !important; }
:deep(.nav-icon-button:hover .p-button-icon) { color: #fff !important; }

/* Badge rojo para Abweichungen */
.nav-badge{
  position: absolute; top: -2px; right: -2px;
  min-width: 16px; height: 16px; padding: 0 4px;
  background: #B01513; color: #fff; font-size: .65rem;
  border-radius: 999px; display: flex; align-items: center; justify-content: center;
  box-shadow: 0 0 0 2px rgba(0,0,0,.25); line-height: 1;
}

/* ---------- USER MENU GLASS ---------- */
:deep(.p-menu.p-component){
  background: rgba(255,255,255,.38);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(0,0,0,.08);
  box-shadow: 0 12px 30px rgba(0,0,0,.25);
  border-radius: 12px;
  overflow: hidden;
  padding: 0;
}
/* header más oscuro */
.user-menu-header{
  padding: 12px 14px;
  background: rgba(255,255,255,.52);
  border-bottom: 1px solid rgba(0,0,0,.06);
}
.name-line{ display:flex; gap:8px; align-items:baseline; }
.name-line .last{ font-weight: 800; font-size: 1.05rem; text-transform: uppercase; }
.name-line .first{ font-weight: 600; font-size: 1rem; opacity: .95; }
.role-line{ font-size: .85rem; color: #334155; margin-top: 2px; }
.email-line{ font-size: .8rem; color: #64748B; margin-top: 2px; }

/* items sobre glass claro */
.user-menu-item{
  display:flex; align-items:center; padding: 10px 12px;
  color:#111; text-decoration:none;
  background: rgba(255,255,255,.34);
}
.user-menu-item:hover{
  background: rgba(255,255,255,.48);
}
.danger-icon{ color:#B01513; }

/* Separador fino y translúcido */
:deep(.p-menu .p-menu-separator){ margin: 0; border-top: 1px solid rgba(0,0,0,.06); }

/* Fix layout scroll under fixed navbar */
.dashboard-content { flex: 1; padding: 0 1rem; margin-top: 70px; z-index: 2; }

.p-component { font-size: 1rem; font-weight: 300; }
</style>
