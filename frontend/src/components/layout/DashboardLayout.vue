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

          <!-- Sales -->
          <Menu ref="salesMenu" :model="salesItems" popup />
          <Button icon="pi pi-chart-line" class="nav-icon-button" @click="salesMenu.toggle($event)" />

          <!-- Reports -->
          <Menu ref="reportsMenu" :model="reportsItems" popup />
          <Button icon="pi pi-file" class="nav-icon-button" @click="reportsMenu.toggle($event)" />

          <!-- Logistics -->
          <Menu ref="logisticsMenu" :model="logisticsItems" popup />
          <Button icon="pi pi-truck" class="nav-icon-button" @click="logisticsMenu.toggle($event)" />

          <!-- Claims -->
          <Menu ref="claimsMenu" :model="claimsItems" popup />
          <Button icon="pi pi-exclamation-triangle" class="nav-icon-button" @click="claimsMenu.toggle($event)" />

          <!-- User -->
          <Menu ref="userMenu" :model="userItems" popup />
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
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import InviteUserModal from '@/components/modals/InviteUserModal.vue'

const router = useRouter()
const auth = useAuthStore()

const showInviteModal = ref(false)

// Menus refs
const salesMenu = ref()
const reportsMenu = ref()
const logisticsMenu = ref()
const claimsMenu = ref()
const userMenu = ref()

// 🟦 Sales ()
const salesItems = [
  { label: 'Prognosen', icon: 'pi pi-chart-line', command: () => router.push('/forecasts') },
  { label: 'Budgetfälle', icon: 'pi pi-briefcase', command: () => router.push('/budget-cases') },
  { label: 'Abweichungen', icon: 'pi pi-sliders-h', command: () => router.push('/deviations') },
  { label: 'Zusatzquoten', icon: 'pi pi-percentage', command: () => router.push('/extra-quotas') },
  { label: 'Aktionspläne', icon: 'pi pi-list-check', command: () => router.push('/action-plans') },
]

// 🟨 Reports (placeholder)
const reportsItems = [
  { label: 'Profitcenter-Bericht', icon: 'pi pi-file', command: () => router.push('/reports/pc') },
  { label: 'Gesamtbericht', icon: 'pi pi-file-o', command: () => router.push('/reports/total') },
]

// 🟩 Logistics (placeholder)
const logisticsItems = [
  { label: 'Eingehende Bestellungen', icon: 'pi pi-inbox', command: () => {} },
  { label: 'Lieferstatus', icon: 'pi pi-send', command: () => {} },
]

// 🟥 Claims (placeholder)
const claimsItems = [
  { label: 'Offene Reklamationen', icon: 'pi pi-exclamation-circle', command: () => {} },
  { label: 'Gelöste Reklamationen', icon: 'pi pi-check-circle', command: () => {} },
]

// 👤 User (Deutsch UI)
const userItems = [
  {
    label: auth.user?.first_name || 'Benutzer',
    items: [
      {
        label: (auth.roles && auth.roles.length > 0) ? auth.roles[0] : 'Keine Rolle',
        disabled: true,
      },
      { separator: true },
      { label: 'Profil bearbeiten', icon: 'pi pi-pen-to-square', command: () => router.push('/profile') },
      { label: 'Dashboard bearbeiten', icon: 'pi pi-th-large', command: () => router.push('/dashboard/edit') },
      { separator: true },
      {
        label: 'Abmelden',
        icon: 'pi pi-sign-out',
        command: async () => {
          await auth.logout()
          router.push('/login')
        }
      }
    ]
  }
];
</script>

<style scoped>
.dashboard-layout {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.dashboard-bg {
  background-image: url('@/assets/img/backgrounds/green.jpg');
  background-repeat: repeat;
  background-position: center;
  background-attachment: fixed;
  min-height: 100vh;
  width: 100%;
}

.dashboard-bg::after {
  content: "";
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
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
  background: rgba(0, 0, 0, 0.1);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}

.logo {
  height: 30px;
}

.nav-buttons {
  display: flex;
  align-items: center;
  gap: .25rem;
}

.nav-icon-button {
  background: transparent !important;
  border: 1px solid transparent !important;
  color: #000 !important;                /* icono negro por defecto */
  box-shadow: none !important;
  padding: 0.4rem;
  border-radius: 8px;
  transition: border-color .25s ease, color .25s ease;
}

/* el ícono interno de PrimeVue */
:deep(.nav-icon-button .p-button-icon) {
  color: #000 !important;                /* negro por defecto */
  transition: color .25s ease;
}

.nav-icon-button:hover {
  background: transparent !important;
  border-color: #fff !important;         /* borde blanco al hover */
  color: #fff !important;                /* icono blanco al hover */
}

:deep(.nav-icon-button:hover .p-button-icon) {
  color: #fff !important;                /* blanco al hover */
}

/* Fix layout scroll under fixed navbar */
.dashboard-content {
  flex: 1;
  padding: 0 1rem;
  margin-top: 70px;
  z-index: 2;
}

.p-component {
  font-size: 1rem;
  font-weight: 300;
}
</style>