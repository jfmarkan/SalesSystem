<template>
	<div class="dashboard-bg">
		<div class="dashboard-layout">
			<header class="dashboard-header glass">
				<div class="logo-container">
					<img src="@/assets/img/logos/stb.png" alt="Logo" class="logo" />
				</div>

				<div class="nav-buttons">
					<!-- DASHBOARD -->
					<Button
						icon="pi pi-th-large"
						class="nav-icon-button"
						:class="{ 'is-active': isActive('/dashboard') }"
						@click="$router.push('/dashboard')"
						v-tooltip.top="'Dashboard'"
					/>

					<!-- SALES + REPORTS (Manager or Up) -->
					<template v-if="isManagerOrUp">
						<!-- 🔧 SEPARATED SALES ICONS -->
						<Button
							icon="pi pi-chart-line"
							class="nav-icon-button"
							@click="$router.push('/forecasts')"
							v-tooltip.top="'Forecast'"
						/>
						<Button
							icon="pi pi-sort-alt-slash"
							class="nav-icon-button"
							@click="$router.push('/budget-cases')"
							v-tooltip.top="'Budget Cases'"
						/>
						<Button
							icon="pi pi-chart-scatter"
							class="nav-icon-button"
							@click="$router.push('/deviations')"
							v-tooltip.top="'Abweichungen'"
						/>
						<Button
							icon="pi pi-briefcase"
							class="nav-icon-button"
							@click="$router.push('/extra-quotas')"
							v-tooltip.top="'Verkaufschancen'"
						/>
						<Button
							icon="pi pi-list-check"
							class="nav-icon-button"
							@click="$router.push('/action-plans')"
							v-tooltip.top="'Aktionspläne'"
						/>

						<Button
							icon="pi pi-chart-pie"
							class="nav-icon-button"
							@click="$router.push('/sales-force')"
							v-tooltip.top="'Analyse'"
						/>

						<!-- REPORTS DROPDOWN -->
						<Menu ref="reportsMenu" :model="reportsItems" popup />
						<Button
							icon="pi pi-file"
							class="nav-icon-button"
							@click="reportsMenu.toggle($event)"
							v-tooltip.top="'Berichte'"
						/>
					</template>

					<!-- SALES REP SIMPLE ICONS -->
					<template v-else>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-chart-line"
								class="nav-icon-button"
								@click="$router.push('/forecasts')"
								v-tooltip.top="'Forecasts'"
							/>
						</div>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-sort-alt-slash"
								class="nav-icon-button"
								@click="$router.push('/budget-cases')"
								v-tooltip.top="'Budget Cases'"
							/>
						</div>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-chart-scatter"
								class="nav-icon-button"
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
								@click="$router.push('/extra-quotas')"
								v-tooltip.top="'Verkaufschancen'"
							/>
						</div>
						<div class="nav-item-wrap">
							<Button
								icon="pi pi-list-check"
								class="nav-icon-button"
								@click="$router.push('/action-plans')"
								v-tooltip.top="'Aktionspläne'"
							/>
						</div>
					</template>

					<!-- LOGISTICS / CLAIMS: 🔧 COMENTADOS TEMPORALMENTE -->
					<!--
          <span class="disabled-wrap" v-tooltip.top="'Kommt bald'">
            <Button icon="pi pi-truck" class="nav-icon-button" :disabled="true" />
          </span>
          <span class="disabled-wrap" v-tooltip.top="'Kommt bald'">
            <Button
              icon="pi pi-exclamation-triangle"
              class="nav-icon-button"
              :disabled="true"
            />
          </span>
          -->

					<!-- SOLO SUPERADMIN: engranaje (admin usuarios) -->
					<Button
						v-if="isSuperAdmin"
						icon="fas fa-users-cog"
						class="nav-icon-button"
						@click="$router.push('/settings/users')"
						v-tooltip.top="'Einstellungen · Benutzerverwaltung'"
					/>

					<!-- USER PANEL (OverlayPanel) -->
<OverlayPanel ref="userPanel" :dismissable="true" :showCloseIcon="false" class="user-panel glass">
  <div class="user-menu-header glass-gray">
    <div class="name-line">
      <span class="first">{{ firstName }}</span>
      <span class="last">{{ lastName || '—' }}</span>
    </div>
    <div class="role-line"><em>{{ displayRole }}</em></div>
    <div class="email-line">{{ auth.user?.email }}</div>
  </div>

  <div class="user-menu-body">
    <!-- Selector de tema: 🌞🌙 / 🌞 / 🌙 -->
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
</OverlayPanel>

<!-- Trigger -->
<Button
  icon="fas fa-user-circle"
  class="nav-icon-button"
  @click="openUserPanel($event)"
  v-tooltip.top="'Benutzermenü'"
/>
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
import { useRoute, useRouter } from 'vue-router'
import OverlayPanel from 'primevue/overlaypanel'
import Divider from 'primevue/divider'
import Button from 'primevue/button'
import Tooltip from 'primevue/tooltip'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import { useAuthStore } from '@/stores/auth'
import ThemeSwitcher from '../ThemeSwitcher.vue'

defineExpose({ directives: { tooltip: Tooltip } })

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

/* Refs */
// const salesMenu = ref()
const reportsMenu = ref()
// const userMenu = ref()

/* Roles */
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

/* Active helper */
const isActive = (path) => route.path.startsWith(path)

/* Nombre/Rol */
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

/* Menús */
// const salesItems = [
// 	{ label: 'Forecasts', icon: 'pi pi-chart-line', command: () => router.push('/forecasts') },
// 	{ label: 'Budget Cases', icon: 'pi pi-briefcase', command: () => router.push('/budget-cases') },
// 	{ label: 'Abweichungen', icon: 'pi pi-sliders-h', command: () => router.push('/deviations') },
// 	{
// 		label: 'Verkaufschancen',
// 		icon: 'pi pi-percentage',
// 		command: () => router.push('/extra-quotas'),
// 	},
// 	{
// 		label: 'Aktionspläne',
// 		icon: 'pi pi-list-check',
// 		command: () => router.push('/action-plans'),
// 	},
// ]
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

const userPanel = ref(null)
function openUserPanel(e){ userPanel.value?.toggle(e) }
function goto(path){ userPanel.value?.hide(); router.push(path) }

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

/* Logout */
function onLogout() {
	if (typeof auth.logout === 'function') auth.logout().then(() => router.push('/login'))
}
</script>

<style scoped>
:global(:root) {
	--glass-bg: rgba(255, 255, 255, 0.4);
	--glass-fg: #1f1f1f;
	--icon-color: #1f1f1f;
	--shadow-color: rgba(0, 0, 0, 0.25);
	--hover-overlay: rgba(0, 0, 0, 0.06);
	--header-strip: rgba(0, 0, 0, 0.04);
	--separator-color: rgba(0, 0, 0, 0.08);
}

@media (prefers-color-scheme: dark) {
	:global(:root) {
		--glass-bg: rgba(0, 0, 0, 0.4);
		--glass-fg: #ffffff;
		--icon-color: #ffffff;
		--shadow-color: rgba(0, 0, 0, 0.6);
		--hover-overlay: rgba(255, 255, 255, 0.1);
		--header-strip: rgba(255, 255, 255, 0.08);
		--separator-color: rgba(255, 255, 255, 0.12);
	}
}

:global(html.dark),
:global(body.dark) {
	--glass-bg: rgba(0, 0, 0, 0.4);
	--glass-fg: #ffffff;
	--icon-color: #ffffff;
	--shadow-color: rgba(0, 0, 0, 0.6);
	--hover-overlay: rgba(255, 255, 255, 0.1);
	--header-strip: rgba(255, 255, 255, 0.08);
	--separator-color: rgba(255, 255, 255, 0.12);
}

:global(.glass) {
	background-color: var(--glass-bg) !important;
	color: var(--glass-fg) !important;
	backdrop-filter: blur(10px);
	-webkit-backdrop-filter: blur(10px);
	transition:
		background-color 0.25s ease,
		color 0.25s ease;
}

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

.dashboard-header {
	position: fixed;
	width: 100%;
	top: 0;
	z-index: 10;
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 0.5rem 2rem;
	box-shadow: 0 4px 12px var(--shadow-color);
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

.nav-icon-button {
	background: transparent !important;
	border: 1px solid transparent !important;
	box-shadow: none !important;
	padding: 0.4rem;
	border-radius: 8px;
	color: var(--icon-color) !important;
	transition:
		border-color 0.25s ease,
		transform 0.1s ease,
		color 0.25s ease;
}
.nav-icon-button:hover {
	border-color: rgba(0, 0, 0, 0.2) !important;
	transform: translateY(-1px);
}

:deep(.nav-icon-button .p-button-icon) {
	color: currentColor !important;
}

:global([class^='pi']),
:global([class*=' pi-']) {
	color: currentColor;
}

.disabled-wrap {
	display: inline-flex;
	align-items: center;
}

.dashboard-content {
	flex: 1;
	padding: 0 1rem;
	margin-top: 60px;
	z-index: 2;
	min-height: calc(100vh - 60px);
}

:deep(.p-menu.p-component) {
	background: var(--glass-bg) !important;
	color: var(--glass-fg) !important;
	backdrop-filter: blur(10px) !important;
	-webkit-backdrop-filter: blur(10px) !important;
	border: 1px solid transparent;
	box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
	border-radius: 12px;
	overflow: hidden;
	padding: 0;
}
.user-menu-header {
	padding: 12px 14px;
	background: var(--header-strip);
	border-bottom: 1px solid var(--separator-color);
	color: var(--glass-fg);
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

.user-menu-item {
	display: flex;
	align-items: center;
	padding: 10px 12px;
	color: var(--glass-fg);
	text-decoration: none;
	transition: background 0.2s ease;
}
.user-menu-item:hover {
	background: var(--hover-overlay);
}

.logout-item:hover {
	background: rgba(0, 0, 0, 0.2);
}

.danger-icon {
	color: #b01513;
}

:deep(.p-menu .p-menu-separator) {
	margin: 0;
	border-top: 1px solid var(--separator-color);
}

.p-component {
	font-size: 1rem;
	font-weight: 300;
}

.user-panel {
	padding: 0;
	border-radius: 12px;
}
.user-menu-body {
	padding: 10px 12px;
	min-width: 260px;
}
.user-menu-body :deep(.p-buttongroup) {
	width: 100%;
	justify-content: space-between;
}
.user-menu-body :deep(.p-button) {
	padding: 0.5rem 0.6rem;
}
</style>
