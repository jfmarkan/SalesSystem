<template>
	<div class="dashboard-bg">
		<div class="dashboard-layout">
			<header class="navbar glass">
				<div class="brand">
					<img :src="logoSrc" alt="Logo" class="logo" />
				</div>

				<div class="spacer"></div>

				<div class="nav-buttons">
					<!-- Dashboard -->
					<Button
						icon="pi pi-th-large"
						class="nav-icon-button"
						:class="{ 'is-active': isActive('/dashboard') }"
						@click="$router.push('/dashboard')"
						v-tooltip.top="'Dashboard'"
					/>

					<!-- Manager / Up -->
					<template v-if="isManagerOrUp">
						<Button
							icon="pi pi-chart-line"
							class="nav-icon-button"
							:class="{ 'is-active': isActive('/forecasts') }"
							@click="$router.push('/forecasts')"
							v-tooltip.top="'Forecast'"
						/>
						<Button
							icon="pi pi-sort-alt-slash"
							class="nav-icon-button"
							:class="{ 'is-active': isActive('/budget-cases') }"
							@click="$router.push('/budget-cases')"
							v-tooltip.top="'Budget Cases'"
						/>
						<Button
							icon="pi pi-chart-scatter"
							class="nav-icon-button"
							:class="{ 'is-active': isActive('/deviations') }"
							@click="$router.push('/deviations')"
							v-tooltip.top="'Abweichungen'"
						/>
						<Button
							icon="pi pi-briefcase"
							class="nav-icon-button"
							:class="{ 'is-active': isActive('/extra-quotas') }"
							@click="$router.push('/extra-quotas')"
							v-tooltip.top="'Verkaufschancen'"
						/>
						<Button
							icon="pi pi-list-check"
							class="nav-icon-button"
							:class="{ 'is-active': isActive('/action-plans') }"
							@click="$router.push('/action-plans')"
							v-tooltip.top="'Aktionspläne'"
						/>
						<Button
							icon="pi pi-chart-pie"
							class="nav-icon-button"
							:class="{ 'is-active': isActive('/sales-force') }"
							@click="$router.push('/sales-force')"
							v-tooltip.top="'Analyse'"
						/>

						<!-- Reports -->
						<Menu ref="reportsMenu" :model="reportsItems" popup />
						<Button
							icon="pi pi-file"
							class="nav-icon-button"
							:class="{
								'is-active':
									isActive('/report-generator') || isActive('/company-analytics'),
							}"
							@click="reportsMenu.toggle($event)"
							v-tooltip.top="'Berichte'"
						/>
					</template>

					<!-- Sales Rep -->
					<template v-else>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-chart-line"
								class="nav-icon-button"
								:class="{ 'is-active': isActive('/forecasts') }"
								@click="$router.push('/forecasts')"
								v-tooltip.top="'Forecasts'"
							/>
						</div>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-sort-alt-slash"
								class="nav-icon-button"
								:class="{ 'is-active': isActive('/budget-cases') }"
								@click="$router.push('/budget-cases')"
								v-tooltip.top="'Budget Cases'"
							/>
						</div>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-chart-scatter"
								class="nav-icon-button"
								:class="{ 'is-active': isActive('/deviations') }"
								@click="$router.push('/deviations')"
								v-tooltip.top="'Abweichungen'"
							/>
							<span v-if="deviationsCount > 0" class="nav-badge">{{
								deviationsCount
							}}</span>
						</div>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-briefcase"
								class="nav-icon-button"
								:class="{ 'is-active': isActive('/extra-quotas') }"
								@click="$router.push('/extra-quotas')"
								v-tooltip.top="'Verkaufschancen'"
							/>
						</div>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-list-check"
								class="nav-icon-button"
								:class="{ 'is-active': isActive('/action-plans') }"
								@click="$router.push('/action-plans')"
								v-tooltip.top="'Aktionspläne'"
							/>
						</div>
					</template>

					<!-- Settings -->
					<Button
						v-if="isSuperAdmin"
						icon="fas fa-users-cog"
						class="nav-icon-button"
						:class="{ 'is-active': isActive('/settings') }"
						@click="$router.push('/settings')"
						v-tooltip.top="'Einstellungen'"
					/>

					<!-- User Popover -->
					<Popover ref="userPanel" dismissable class="user-panel glass">
						<div class="user-menu-header">
							<div class="name-line">
								<span class="first">{{ firstName }}</span>
								<span class="last">{{ lastName || '—' }}</span>
							</div>
							<div class="role-line">
								<em>{{ displayRole }}</em>
							</div>
							<div class="email-line">{{ auth.user?.email }}</div>
						</div>

						<div class="user-menu-body">
							<ThemeSwitcher />
							<Divider class="my-2" />
							<Button
								label="Profil bearbeiten"
								icon="pi pi-pen-to-square"
								class="p-button-text w-full justify-content-start"
								@click="goto('/profile')"
							/>
							<Button
								label="Abmelden"
								icon="pi pi-sign-out"
								class="p-button-text p-button-danger w-full justify-content-start"
								@click="onLogout"
							/>
						</div>
					</Popover>

					<Button
						icon="fas fa-user-circle"
						class="nav-icon-button"
						@click="openUserPanel($event)"
						v-tooltip.top="'Benutzermenü'"
					/>
				</div>
			</header>

			<main class="dashboard-content container-fluid">
				<router-view />
			</main>
		</div>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Popover from 'primevue/popover'
import Divider from 'primevue/divider'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import { useAuthStore } from '@/stores/auth'
import ThemeSwitcher from '../ThemeSwitcher.vue'

/* Logo por tema */
const isDark = ref(document.documentElement.classList.contains('dark'))
let _mo
onMounted(() => {
	_mo = new MutationObserver(() => {
		isDark.value = document.documentElement.classList.contains('dark')
	})
	_mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
})
onBeforeUnmount(() => {
	_mo && _mo.disconnect()
})
const logoSrc = computed(() =>
	isDark.value
		? new URL('@/assets/img/logos/logo-light.svg', import.meta.url).href
		: new URL('@/assets/img/logos/logo-dark.svg', import.meta.url).href,
)

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const reportsMenu = ref()
const userPanel = ref(null)

const roleId = computed(() => Number(auth.roleId ?? auth.role_id ?? auth.user?.role_id ?? 0))
const rolesList = computed(() =>
	(auth.roles ?? auth.user?.roles ?? []).map((r) => String(r).toLowerCase()),
)
const isSuperAdmin = computed(() => roleId.value === 1 || rolesList.value.includes('superadmin'))
const isManagerOrUp = computed(
	() =>
		isSuperAdmin.value ||
		roleId.value === 2 ||
		roleId.value === 3 ||
		rolesList.value.includes('manager') ||
		rolesList.value.includes('admin'),
)
const isSalesRep = computed(
	() =>
		roleId.value === 4 ||
		rolesList.value.some((r) => ['sales_rep', 'employee', 'empleado'].includes(r)),
)

const isActive = (path) => route.path.startsWith(path)

const firstName = computed(
	() => auth.firstName || auth.user?.first_name || (auth.user?.name?.split(' ')[0] ?? ''),
)
const lastName = computed(
	() =>
		auth.lastName ||
		auth.user?.last_name ||
		(auth.user?.name?.split(' ').slice(1).join(' ') ?? ''),
)
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

const reportsItems = [
	{
		label: 'Profitcenter-Bericht',
		icon: 'pi pi-file',
		command: () => router.push('/report-generator'),
	},
	{
		label: 'Gesamtbericht',
		icon: 'pi pi-file-o',
		command: () => router.push('/company-analytics'),
	},
]

function openUserPanel(e) {
	userPanel.value?.toggle(e)
}
function goto(path) {
	userPanel.value?.hide()
	router.push(path)
}

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
.logo {
	height: 30px;
}
.nav-buttons {
	display: flex;
	align-items: center;
	gap: 0.25rem;
}
.dashboard-content {
	flex: 1;
	padding: 0 1rem;
	margin-top: var(--navbar-h);
	z-index: 2;
	min-height: calc(100vh - var(--navbar-h));
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
</style>
