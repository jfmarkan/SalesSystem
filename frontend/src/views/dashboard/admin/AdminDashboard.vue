<template>
	<div class="menu-wrapper">
		<div class="grid-wrapper">
			<!-- 3 columnas izquierdas (acciones directas): 2 por columna -->
			<div class="column" v-for="(col, ci) in leftColumns" :key="'left-' + ci">
				<div
					v-for="card in col"
					:key="card.key"
					class="menu-card action"
					@click="onCardClick(card)"
					:aria-label="card.label"
					role="button"
				>
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

			<!-- 1 columna derecha (opciones): 3 en vertical -->
			<div class="column">
				<div
					v-for="card in rightColumn"
					:key="card.key"
					class="menu-card option"
					@click="onCardClick(card)"
					:aria-label="card.label"
					role="button"
				>
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
</template>

<script setup>
import { computed, defineProps } from 'vue'
import { useRouter } from 'vue-router'
import Card from 'primevue/card'

/* PROPS: listas y rutas */
const props = defineProps({
	actions: {
		type: Array,
		default: () => [
			{ key: 'manage_users',     label: 'Benutzer synchronisieren', icon: 'pi-users',      desc: 'Synchronisiert Benutzer mit externer Quelle.' },
			{ key: 'reload_cache',     label: 'Cache neu laden',          icon: 'pi-refresh',    desc: 'Leert und regeneriert den App-Cache.' },
			{ key: 'update_clients',   label: 'Kunden aktualisieren',     icon: 'pi-building',   desc: 'Importiert/aktualisiert Kundendaten.' },
			{ key: 'rebuild_sales',    label: 'Umsätze neu berechnen',    icon: 'pi-database',   desc: 'Reindiziert/verdichtet Verkaufsdaten.' },
			{ key: 'regenerate_kpis',  label: 'KPIs neu berechnen',       icon: 'pi-chart-line', desc: 'Berechnet Kennzahlen neu.' },
			{ key: 'toggle_maintenance', label: 'Wartungsmodus',          icon: 'pi-power-off',  desc: 'Aktiviert/Deaktiviert Wartungsmodus.' },
		],
	},
	options: {
		type: Array,
		default: () => [
			{ key: 'manage_users',  label: 'Benutzer verwalten', icon: 'pi-user-edit',  desc: 'Benutzer & Rollen erstellen, Rollen zuweisen.' },
			{ key: 'open_budget',   label: 'Budget + Forecast',  icon: 'pi-percentage', desc: 'Budgets & Forecasts generieren.' },
			{ key: 'client_explorer', label: 'Kunden-Explorer',  icon: 'pi-search',     desc: 'Kunden suchen, filtern und prüfen.' },
		],
	},
	routesByKey: {
		type: Object,
		default: () => ({
			// acciones
			manage_users: '/users',
			reload_cache: '/admin/cache',
			update_clients: '/admin/clients/update',
			rebuild_sales: '/admin/sales/rebuild',
			regenerate_kpis: '/admin/kpis',
			toggle_maintenance: '/admin/maintenance',
			// opciones
			open_budget: '/admin/budget',
			client_explorer: '/admin/clients',
			// por si acaso
			sync_users: '/admin/users/sync',
		}),
	},
})

/* layout 2,2,2,3 */
function group2(list) { return [list.slice(0, 2), list.slice(2, 4), list.slice(4, 6)] }
const leftColumns = computed(() => group2(props.actions))
const rightColumn = computed(() => props.options)

/* Router push (no UI interna) */
const router = useRouter()
function onCardClick(card) {
	const to = props.routesByKey[card?.key]
	if (to) router.push(to)
	else console.warn('No route mapped for key:', card?.key)
}
</script>

<style scoped>
/* ======= Ajustes rápidos por CSS vars =======
   Puedes sobreescribir desde fuera:
   .menu-wrapper{ --card-w:240px; --card-h:160px; --gap:18px; }
*/
.menu-wrapper {
	--warm: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
	--cool: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
	--card-w: 300px;
	--card-h: 200px;
	--gap: 24px;

	display: flex;
	justify-content: center;
	align-items: start;
	height: 100%;
}

/* grid 4 cols con gap constante */
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
	align-items: center
}

/* Card + glow */
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
/* SWAP: action -> COOL, option -> WARM */
.menu-card.action::before { background: var(--cool); }
.menu-card.option::before { background: var(--warm); }

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

/* Hover: leve, con glow visible */
.menu-card:hover::before {
	opacity: 1;
	transform: scale(.95);
	filter: blur(22px);
}
.menu-card:hover :deep(.p-card) {
	transform: scale(1.01);
	box-shadow: 0 8px 18px rgba(0, 0, 0, .10);
}

/* Contenido */
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
.tile-head i { font-size: 18px; color: #333; }
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

/* Responsive */
@media (max-width: 1100px) {
	.grid-wrapper { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
	.grid-wrapper { grid-template-columns: 1fr; }
	.menu-card :deep(.p-card) {
		width: 88vw;
		max-width: 360px;
		height: auto;
		aspect-ratio: 1/1;
	}
}
</style>
