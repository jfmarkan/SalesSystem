<template>
	<div class="settings-layout h-full">
		<!-- ====== MENU DE CARDS (INICIAL) ====== -->
		<div v-if="!sectionKey" class="menu-wrapper">
			<div class="grid-wrapper">
				<!-- 3 columnas izquierdas (acciones directas): 2 por columna -->
				<div class="column" v-for="(col, ci) in leftColumns" :key="'left-' + ci">
					<div v-for="card in col" :key="card.key" class="menu-card action" role="button"
						:aria-label="card.label" @click="goTo(card.key)">
						<Card>
							<template #content>
								<div class="card-content">
									<div class="tile-head">
										<i :class="['pi', card.icon]"></i>
										<h4 class="title">{{ card.label }}</h4>
									</div>
									<p class="desc">{{ card.desc }}</p>
								</div>
							</template>
						</Card>
					</div>
				</div>

				<!-- 1 columna derecha (opciones): 3 vertical -->
				<div class="column">
					<div v-for="card in rightColumn" :key="card.key" class="menu-card option" role="button"
						:aria-label="card.label" @click="goTo(card.key)">
						<Card>
							<template #content>
								<div class="card-content">
									<div class="tile-head">
										<i :class="['pi', card.icon]"></i>
										<h4 class="title">{{ card.label }}</h4>
									</div>
									<p class="desc">{{ card.desc }}</p>
								</div>
							</template>
						</Card>
					</div>
				</div>
			</div>
		</div>

		<!-- ====== MODO APP: RAIL 50px + CONTENT (router-view) ====== -->
		<div v-else class="app-wrapper">
			<!-- Rail izquierda 50px + padding 10 -->
			<aside class="rail">
				<Button icon="pi pi-arrow-left" rounded text class="rail-top" v-tooltip.right="'Zurück zum Menü'"
					@click="backToMenu" />
				<Divider class="my-2" />
				<div class="rail-items">
					<Button v-for="it in allItems" :key="it.key" :icon="`pi ${it.icon}`" rounded text
						:class="['rail-btn', { active: sectionKey === it.key }]" v-tooltip.right="it.label"
						@click="goTo(it.key)" />
				</div>
			</aside>

			<!-- Contenido ruteado -->
			<main class="content">
				<router-view />
			</main>
		</div>
	</div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Tooltip from 'primevue/tooltip'

const vTooltip = Tooltip
const router = useRouter()
const route = useRoute()

/* DATA menú */
const actions = [
	{ key: 'users', label: 'Benutzer', icon: 'pi-user', desc: 'Verwalten & Zuweisungen' },
	{ key: 'teams', label: 'Teams', icon: 'pi-sitemap', desc: 'Erstellen & Struktur' },
	{ key: 'company', label: 'Firma', icon: 'pi-building', desc: 'Daten & Adressen' },
	{ key: 'cache', label: 'Cache neu laden', icon: 'pi-refresh', desc: 'Leert & regeneriert Cache' },
	{ key: 'sales', label: 'Umsätze neu berechn.', icon: 'pi-database', desc: 'Reindex & Verdichtung' },
	{ key: 'kpis', label: 'KPIs neu berechnen', icon: 'pi-chart-line', desc: 'Kennzahlen neu generieren' },
]
const options = [
	{ key: 'budget', label: 'Budget + Forecast', icon: 'pi-percentage', desc: 'Budgets & Forecasts' },
	{ key: 'clients', label: 'Kunden-Explorer', icon: 'pi-search', desc: 'Suchen & prüfen' },
	{ key: 'maint', label: 'Wartungsmodus', icon: 'pi-power-off', desc: 'On/Off' },
]

/* layout 2,2,2,3 para menú */
function group2(list) { return [list.slice(0, 2), list.slice(2, 4), list.slice(4, 6)] }
const leftColumns = group2(actions)
const rightColumn = options
const allItems = [...actions, ...options]

/* Estado via ruta: /settings/:section? */
const sectionKey = computed(() => route.params.section ? String(route.params.section) : '')

/* Navegación */
function goTo(key) {
	router.push({ name: 'settings.section', params: { section: key } })
}
function backToMenu() {
	router.push({ name: 'settings.home' })
}
</script>

<style scoped>
.settings-layout {
	display: flex;
	flex-direction: column;
}

/* ========== MENU (cards) ========== */
.menu-wrapper {
	--warm: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
	--cool: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
	--gap: 24px;
	--card-w: 300px;
	--card-h: 200px;

	display: flex;
	justify-content: center;
	align-items: start;
	height: 100%;
}

.grid-wrapper {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: var(--gap);
	width: 100%;
	max-width: 1400px;
	height: 100%;
}

.column {
	display: flex;
	flex-direction: column;
	gap: var(--gap);
	align-items: center;
}

.menu-card {
	position: relative;
	border-radius: 15px;
	cursor: pointer;
}

.menu-card::before {
	content: "";
	position: absolute;
	inset: -10px;
	border-radius: inherit;
	z-index: 0;
	opacity: 0;
	transform: scale(.98);
	filter: blur(15px);
	transition: opacity .16s ease, transform .16s ease, filter .16s ease;
	pointer-events: none;
}

.menu-card.action::before {
	background: var(--cool);
}

.menu-card.option::before {
	background: var(--warm);
}

.menu-card :deep(.p-card) {
	position: relative;
	z-index: 1;
	width: var(--card-w);
	height: var(--card-h);
	border-radius: 15px;
	background: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
	transition: transform .14s ease, box-shadow .14s ease;
}

.menu-card:hover::before {
	opacity: 1;
	transform: scale(.95);
	filter: blur(22px);
}

.menu-card:hover :deep(.p-card) {
	transform: scale(1.01);
	box-shadow: 0 8px 18px rgba(0, 0, 0, .10);
}

.card-content {
	height: 100%;
	width: 100%;
	padding: 12px 12px 10px;
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	align-items: center;
	text-align: center;
}

.tile-head {
	display: flex;
	flex-direction: column;
	align-items: start;
	gap: 6px;
}

.tile-head i {
	font-size: 18px;
	color: #333;
}

.tile-head .title {
	margin: 0;
	font-size: .95rem;
	color: #1b1b1d;
	font-weight: 700;
}

.desc {
	margin: 0;
	font-size: .82rem;
	line-height: 1.2;
	color: #4b5563;
	display: -webkit-box;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

/* ========== APP (rail + content) ========== */
.app-wrapper {
	display: grid;
	grid-template-columns: 50px 1fr;
	/* rail fijo 50px */
	gap: 12px;
	height: 100%;
}

.rail {
	width: 50px;
	padding: 10px;
	/* 10px arriba/abajo/lados */
	background: var(--surface-card, #fff);
	border-right: 1px solid var(--surface-200, #e5e7eb);
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
}

.rail-top {
	margin-bottom: 4px;
}

.rail-items {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.rail-btn.active {
	color: var(--primary-color) !important;
}

.content {
	min-width: 0;
	overflow: auto;
	padding-right: 8px;
}

/* Responsive: 2 cols -> 1 col */
@media (max-width: 1100px) {
	.grid-wrapper {
		grid-template-columns: repeat(2, 1fr);
	}
}

@media (max-width: 640px) {
	.grid-wrapper {
		grid-template-columns: 1fr;
	}

	.menu-card :deep(.p-card) {
		width: 88vw;
		max-width: 360px;
		height: auto;
		aspect-ratio: 1/1;
	}

	.app-wrapper {
		grid-template-columns: 48px 1fr;
	}
}
</style>
