<template>
	<div>
		<Toolbar class="app-toolbar glass">
			<template #start>
				<img src="@/assets/img/logos/logo-dark.svg" alt="Logo" class="logo" />
			</template>
			<template #end>
				<div class="toolbar-actions">
					<Button icon="pi pi-th-large" text plain @click="$router.push('/dashboard')"
						:class="{ 'active-nav': isActive('/dashboard') }" />
					<Button icon="pi pi-chart-line" text plain @click="$router.push('/forecasts')"
						:class="{ 'active-nav': isActive('/forecasts') }" />
					<Button icon="pi pi-sort-alt-slash" text plain @click="$router.push('/budget-cases')"
						:class="{ 'active-nav': isActive('/budget-cases') }" />

					<div class="relative">
						<Button icon="pi pi-chart-scatter" text plain @click="$router.push('/deviations')"
							:class="{ 'active-nav': isActive('/deviations') }" />
						<Badge v-if="deviationsCount > 0" :value="deviationsCount" class="badge-count"
							severity="danger" />
					</div>
					<Button icon="pi pi-briefcase" text plain @click="$router.push('/extra-quotas')"
						:class="{ 'active-nav': isActive('/extra-quotas') }" />
					<Button icon="pi pi-list-check" text plain @click="$router.push('/action-plans')"
						:class="{ 'active-nav': isActive('/action-plans') }" />

					<!-- REPORTES -->
					<!-- MENU DE REPORTES (solo manager o más) -->
					<template v-if="isManagerOrUp">
						<Menu ref="reportsMenu" :model="reportsItems" popup />
						<Button icon="pi pi-file" text plain @click="toggleReportsMenu" />
					</template>


					<!-- SETTINGS -->
					<Button v-if="isSuperAdmin" icon="pi pi-cog" text plain @click="$router.push('/settings')"
						:class="{ 'active-nav': isActive('/settings') }" />

					<!-- USER POPOVER -->
					<Popover ref="userMenu" dismissable>
						<div class="p-2 w-48">
							<p class="font-bold mb-1">{{ firstName }} {{ lastName }}</p>
							<p class="text-sm text-gray-500 mb-2">{{ auth.user?.email }}</p>
							<Divider class="my-2" />
							<Button label="Profil Bearbeiten" icon="pi pi-user-edit" class="p-button-text w-full mb-2"
								@click="goto('/profile')" />
							<Button label="Abmelden" icon="pi pi-sign-out" class="p-button-danger p-button-text w-full"
								@click="logout" />
						</div>
					</Popover>

					<Avatar image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png" shape="circle"
						class="avatar" @click="toggleUserMenu" />
				</div>
			</template>
		</Toolbar>

		<main class="main-content">
			<router-view />
		</main>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Toolbar from 'primevue/toolbar'
import Button from 'primevue/button'
import Avatar from 'primevue/avatar'
import Badge from 'primevue/badge'
import Popover from 'primevue/popover'
import Divider from 'primevue/divider'
import Menu from 'primevue/menu'
import { useAuthStore } from '@/stores/auth'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

// Roles y permisos
const roleId = computed(() => Number(auth.roleId ?? auth.role_id ?? auth.user?.role_id ?? 0))
const rolesList = computed(() => (auth.roles ?? auth.user?.roles ?? []).map(r => r.toLowerCase()))
const isSuperAdmin = computed(() => roleId.value === 1 || rolesList.value.includes('superadmin'))
const isManagerOrUp = computed(() =>
	isSuperAdmin.value ||
	roleId.value === 2 || // Admin
	roleId.value === 3 || // Manager
	rolesList.value.includes('manager') ||
	rolesList.value.includes('admin')
)


// Navigation
const isActive = (path) => route.path.startsWith(path)

const reportsMenu = ref()
function toggleReportsMenu(event) {
	reportsMenu.value?.toggle(event)
}
const reportsItems = [
	{ label: 'Profitcenter Report', icon: 'pi pi-file', command: () => router.push('/report-generator') },
	{ label: 'Company Analytics', icon: 'pi pi-chart-bar', command: () => router.push('/company-analytics') }
]

const userMenu = ref()
function toggleUserMenu(event) {
	userMenu.value?.toggle(event)
}

function logout() {
	if (typeof auth.logout === 'function') {
		auth.logout().then(() => router.push('/login'))
	}
}

function goto(path) {
	userMenu.value?.hide()
	router.push(path)
}

const firstName = computed(() => auth.user?.first_name ?? auth.user?.name?.split(' ')[0] ?? '')
const lastName = computed(() => auth.user?.last_name ?? auth.user?.name?.split(' ').slice(1).join(' ') ?? '')

const deviationsCount = ref(0)
let intervalId
async function refreshDeviationCount() {
	try {
		await ensureCsrf()
		const { data } = await api.get('/api/deviations')
		deviationsCount.value = Array.isArray(data) ? data.filter(d => !d.justified).length : 0
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
</script>

<style scoped>
/* Mantiene todo en UNA LÍNEA horizontal */
.app-toolbar {
	height: 54px !important;
	min-height: 54px !important;
	padding: 0 1rem;
	display: flex;
	align-items: center;
	overflow: hidden;
	position: fixed;
	width: 100%;
	z-index: 10;
	background: transparent !important;
}

.logo {
	height: 28px;
	object-fit: contain;
}

/* Íconos + avatar en una sola fila */
.toolbar-actions {
	display: flex;
	align-items: center;
	gap: 0.5rem;
	flex-wrap: nowrap;
}

/* Avatar */
.avatar {
	width: 32px;
	height: 32px;
	cursor: pointer;
}

/* Badge */
.relative {
	position: relative;
}

.badge-count {
	position: absolute;
	top: -6px;
	right: -6px;
}

.active-nav {
  background-color: rgb(0, 0, 0) !important;
  color: white !important;
  border-radius: 6px;
}

/* Contenido principal */
.main-content {
	padding: 70px 1rem 1rem 1rem;
	height: 100vh;
	background-color: rgb(235, 235, 235);
	background-position: fixed;
	overflow: auto;
}
</style>
