<template>
  <div class="dashboard-bg">
    <div class="dashboard-layout">
      <header class="dashboard-header glass-nav">
        <div class="logo-container">
          <img src="@/assets/img/logos/stb.png" alt="Logo" class="logo" />
        </div>

        <div class="nav-buttons">
          <!-- Dashboard -->
          <Button
            icon="pi pi-th-large"
            class="nav-icon-button"
            @click="$router.push('/dashboard')"
          />

          <!-- SALES: manager+ dropdown / sales_rep inline -->
          <template v-if="isManagerOrUp">
            <Menu ref="salesMenu" :model="salesItems" popup />
            <Button
              icon="pi pi-chart-line"
              class="nav-icon-button"
              @click="salesMenu.toggle($event)"
            />
          </template>
          <template v-else>
            <div class="nav-item-wrap" title="Prognosen">
              <Button
                icon="pi pi-chart-line"
                class="nav-icon-button"
                @click="$router.push('/forecasts')"
              />
            </div>
            <div class="nav-item-wrap" title="Budgetfälle">
              <Button
                icon="pi pi-briefcase"
                class="nav-icon-button"
                @click="$router.push('/budget-cases')"
              />
            </div>
            <div class="nav-item-wrap" title="Abweichungen">
              <Button
                icon="pi pi-sliders-h"
                class="nav-icon-button"
                @click="$router.push('/deviations')"
              />
              <span v-if="deviationsCount > 0" class="nav-badge">{{ deviationsCount }}</span>
            </div>
            <div class="nav-item-wrap" title="Zusatzquoten">
              <Button
                icon="pi pi-percentage"
                class="nav-icon-button"
                @click="$router.push('/extra-quotas')"
              />
            </div>
            <div class="nav-item-wrap" title="Aktionspläne">
              <Button
                icon="pi pi-list-check"
                class="nav-icon-button"
                @click="$router.push('/action-plans')"
              />
            </div>
          </template>

          <!-- REPORTS / LOGISTICS / CLAIMS solo manager+ -->
          <template v-if="isManagerOrUp">
            <Button icon="pi pi-users" class="nav-icon-button" @click="$router.push('/sales-force')" />
            <Menu ref="reportsMenu" :model="reportsItems" popup />
            <Button icon="pi pi-file" class="nav-icon-button" @click="reportsMenu.toggle($event)" />
            <Menu ref="logisticsMenu" :model="logisticsItems" popup />
            <Button
              icon="pi pi-truck"
              class="nav-icon-button"
              @click="logisticsMenu.toggle($event)"
            />
            <Menu ref="claimsMenu" :model="claimsItems" popup />
            <Button
              icon="pi pi-exclamation-triangle"
              class="nav-icon-button"
              @click="claimsMenu.toggle($event)"
            />
          </template>

          <!-- USER MENU con header, acciones y logout secciones -->
          <Menu ref="userMenu" :model="userItems" popup>
            <template #item="{ item, props }">
              <!-- Sección Header -->
              <div v-if="item.type === 'header'" class="user-menu-header glass-gray">
                <div class="name-line">
                    <span class="first">{{ firstName }}</span>
                    <span class="last">{{ lastName || '—' }}</span>
                </div>
                <div class="role-line">
                  <em>{{ displayRole }}</em>
                </div>
                <div class="email-line">{{ auth.user?.email }}</div>
              </div>

              <div v-else-if="item.separator" class="p-menu-separator"></div>

              <!-- Acciones intermedias -->
              <a v-else-if="!item.danger" v-bind="props.action" class="user-menu-item glass-mid">
                <i :class="['pi', item.icon]"></i>
                <span class="ml-2">{{ item.label }}</span>
              </a>

              <!-- Logout con fondo más oscuro e icono rojo -->
              <a v-else v-bind="props.action" class="user-menu-item glass-dark logout-item" @click.prevent="onLogout">
                <i class="pi pi-sign-out danger-icon"></i>
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
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import Menu from 'primevue/menu'
import Button from 'primevue/button'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

/* Menus refs */
const salesMenu = ref()
const reportsMenu = ref()
const logisticsMenu = ref()
const claimsMenu = ref()
const userMenu = ref()

/* Roles */
const roleId = computed(() => Number(auth.roleId ?? auth.role_id ?? auth.user?.role_id ?? 0))
const rolesList = computed(() =>
  (auth.roles ?? auth.user?.roles ?? []).map((r) => String(r).toLowerCase()),
)
const isManagerOrUp = computed(
  () =>
    [1, 2, 3].includes(roleId.value) ||
    rolesList.value.some((r) => ['manager', 'admin', 'superadmin'].includes(r)),
)
const isSalesRep = computed(
  () =>
    roleId.value === 4 ||
    rolesList.value.some((r) => ['sales_rep', 'employee', 'empleado'].includes(r)),
)

/* Nombre/Apellido */
const firstName = computed(
  () => auth.firstName || auth.user?.first_name || (auth.user?.name?.split(' ')[0] ?? ''),
)
const lastName = computed(
  () =>
    auth.lastName || auth.user?.last_name || (auth.user?.name?.split(' ').slice(1).join(' ') ?? ''),
)

/* Rol visible */
const roleMap = { 1: 'Superadmin', 2: 'Admin', 3: 'Manager', 4: 'Sales Rep' }
const rawRole = computed(
  () => auth.user?.role || (auth.roles?.[0] ?? '') || roleMap[roleId.value] || '',
)
const displayRole = computed(() => {
  const r = String(rawRole.value || '').toLowerCase()
  if (['sales_rep', 'salesrep', 'employee', 'empleado'].includes(r)) return 'Sales Rep'
  if (['manager'].includes(r)) return 'Manager'
  if (['admin', 'administrator'].includes(r)) return 'Admin'
  if (['superadmin', 'owner', 'root'].includes(r)) return 'Superadmin'
  return rawRole.value || '—'
})

/* Modelos de menús */
const salesItems = [
  { label: 'Prognosen', icon: 'pi pi-chart-line', command: () => router.push('/forecasts') },
  { label: 'Budgetfälle', icon: 'pi pi-briefcase', command: () => router.push('/budget-cases') },
  { label: 'Abweichungen', icon: 'pi pi-sliders-h', command: () => router.push('/deviations') },
  { label: 'Zusatzquoten', icon: 'pi pi-percentage', command: () => router.push('/extra-quotas') },
  { label: 'Aktionspläne', icon: 'pi pi-list-check', command: () => router.push('/action-plans') },
]
const reportsItems = [
  { label: 'Profitcenter-Bericht', icon: 'pi pi-file', command: () => router.push('/report-generator') },
  { label: 'Gesamtbericht', icon: 'pi pi-file-o', command: () => router.push('/company-analytics') },
]
const logisticsItems = [
  { label: 'Eingehende Bestellungen', icon: 'pi pi-inbox', command: () => {} },
  { label: 'Lieferstatus', icon: 'pi pi-send', command: () => {} },
]
const claimsItems = [
  { label: 'Offene Reklamationen', icon: 'pi pi-exclamation-circle', command: () => {} },
  { label: 'Gelöste Reklamationen', icon: 'pi pi-check-circle', command: () => {} },
]

/* User menu con header + acciones + logout */
const userItems = computed(() => [
  { type: 'header' },
  { separator: true },
//  {
//    label: 'Profil bearbeiten',
//    icon: 'pi pi-pen-to-square',
//    command: () => router.push('/profile'),
//  },
//  {
//    label: 'Dashboard bearbeiten',
//    icon: 'pi pi-th-large',
//    command: () => router.push('/edit'),
//  },
//  { separator: true },
  { label: 'Abmelden', danger: true}, // manejado por logout-item
])

/* Deviations badge para sales_rep */
const deviationsCount = ref(0)
let intervalId = null
async function refreshDeviationCount() {
  if (!isSalesRep.value) {
    deviationsCount.value = 0
    return
  }
  try {
    await ensureCsrf()
    const { data } = await api.get('/api/deviations')
    deviationsCount.value = Array.isArray(data) ? data.filter((d) => !d.justified).length : 0
  } catch {
    deviationsCount.value = 0
  }
}
onMounted(async () => {
  await refreshDeviationCount()
  intervalId = setInterval(refreshDeviationCount, 60000)
})
onBeforeUnmount(() => {
  if (intervalId) clearInterval(intervalId)
})

/* Logout acción */
function onLogout() {
  if (typeof auth.logout === 'function') auth.logout().then(() => router.push('/login'))
}
</script>

<style scoped>
.dashboard-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.dashboard-bg {
  background-image: url('@/assets/img/backgrounds/linen.png');
  background-repeat: repeat;
  background-position: center;
  background-attachment: fixed;
  min-height: 100vh;
  width: 100%;
}

.dashboard-bg::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0);
  z-index: 1;
}

.dashboard-header {
  position: fixed;
  width: 100%;
  top: 0;
  z-index: 10;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.5rem 2rem;
  background: rgba(255, 255, 255, 0.3);
  backdrop-filter: blur(20px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}
.logo {
  height: 30px;
}

.nav-buttons {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.nav-item-wrap {
  position: relative;
}

.nav-icon-button {
  background: transparent !important;
  border: 1px solid transparent !important;
  color: #000 !important;
  box-shadow: none !important;
  padding: 0.4rem;
  border-radius: 8px;
  transition:
    border-color 0.25s ease,
    color 0.25s ease;
}
:deep(.nav-icon-button .p-button-icon) {
  color: #000 !important;
  transition: color 0.25s ease;
}
.nav-icon-button:hover {
  background: transparent !important;
  border-color: #fff !important;
  color: #fff !important;
}
:deep(.nav-icon-button:hover .p-button-icon) {
  color: #fff !important;
}

.nav-badge {
  position: absolute;
  top: -2px;
  right: -2px;
  min-width: 16px;
  height: 16px;
  padding: 0 4px;
  background: #b01513;
  color: #fff;
  font-size: 0.65rem;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.25);
  line-height: 1;
}

.dashboard-content {
  flex: 1;
  padding: 0 1rem;
  margin-top: 70px;
  z-index: 2;
}

/* ---------- USER MENU: fondo negro .75 + blur 10 ---------- */
:deep(.p-menu.p-component) {
  background: rgba(0, 0, 0, 0.75) !important;
  backdrop-filter: blur(10px) !important;
  -webkit-backdrop-filter: blur(10px) !important;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
  border-radius: 12px;
  overflow: hidden;
  padding: 0;
}

/* Sección superior más clara (gris sobre negro .75) */
.user-menu-header {
  padding: 12px 14px;
  background: rgba(255, 255, 255, 0.08);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  color: #fff;
}
.name-line {
  display: flex;
  gap: 8px;
  align-items: baseline;
}
.name-line .last {
  font-weight: 800;
  font-size: 1.05rem;
  letter-spacing: 0.2px;
}
.name-line .first {
  font-weight: 600;
  font-size: 1rem;
  opacity: 0.95;
}
.role-line {
  font-size: 0.8rem;
  margin-top: 2px;
  opacity: 0.9;
}
.email-line {
  font-size: 0.8rem;
  margin-top: 4px;
  opacity: 0.85;
}

/* Acciones intermedias con glass claro */
.user-menu-item {
  display: flex;
  align-items: center;
  padding: 10px 12px;
  color: #fff;
  text-decoration: none;
}
.glass-mid {
  background: rgba(255, 255, 255, 0.04);
}
.user-menu-item:hover {
  background: rgba(255, 255, 255, 0.1);
}

/* Logout más oscuro */
.glass-dark {
  background: rgba(0, 0, 0, 0.5);
}
.logout-item:hover {
  background: rgba(0, 0, 0, 0.6);
}
.danger-icon {
  color: #b01513;
}

/* Separador */
:deep(.p-menu .p-menu-separator) {
  margin: 0;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.p-component {
  font-size: 1rem;
  font-weight: 300;
}
</style>
