<template>
	<div class="clients-wraper">
		<Toast />
		<!-- Filtros -->
		<Card>
			<template #title>
				<div class="title">
					Kundenverarbeitung
				</div>
				<div class="grid grid-cols-1 md:grid-cols-5 gap-4">
					<!-- Kundenartikelklassifikation -->
					<InputText v-model="filters.kundenartikelklassifikation" placeholder="Artikelklassifikation" />

					<!-- Kundenname -->
					<InputText v-model="filters.kundenname" placeholder="Kundenname" />

					<!-- Klassifizierung -->
					<Dropdown v-model="filters.klassifizierung" :options="classificationOptions" optionLabel="label"
						optionValue="value" placeholder="Klassifizierung" />

					<!-- Verantwortlicher -->
					<Dropdown v-model="filters.verantwortlicher" :options="responsibleOptions"
						placeholder="Verantwortlicher" />

					<!-- Profit Center -->
					<Dropdown v-model="filters.profit_center" :options="profitCenterOptions"
						placeholder="Profit-Center" />
				</div>
			</template>
			<template #content>
				<DataTable :value="filteredClients" :sortField="sortField" :sortOrder="sortOrder" :rows="10" paginator
					dataKey="client_group_number" tableStyle="min-width: 100%" responsiveLayout="scroll" @sort="onSort">
					<!-- Kundenartikelklassifikation -->
					<Column field="client_group_number" header="Kundenartikelklassifikation" sortable />

					<!-- Kundenname -->
					<Column field="client_name" header="Kundenname" sortable />

					<!-- ABCKlassifizierung -->
					<Column field="classification" header="ABC Klassifizierung" sortable>
						<template #body="{ data }">
							<span :class="['classification-badge', getClassificationClass(data.classification)]">
								{{ data.classification }}
							</span>
						</template>
					</Column>

					<!-- Gruppierungsverantwortlicher -->
					<Column header="Gruppierungsverantwortlicher">
						<template #body="{ data }">
							<Chip class="p-chip p-chip-custom">
								<Avatar :image="data.responsible_avatar"
									:label="!data.responsible_avatar ? getInitials(data.responsible_name, data.responsible_surname) : null"
									shape="circle" size="small" class="mr-2" />
								<span>{{ data.responsible_surname || '—' }} {{ data.responsible_name }}</span>
							</Chip>
						</template>
					</Column>

					<!-- Profit-Center -->
					<Column header="Profit-Center">
						<template #body="{ data }">
							<div class="flex flex-wrap gap-1">
								<Chip v-for="pc in data.profit_centers" :key="pc.id" :label="pc.code"
									class="p-chip p-chip-secondary text-xs py-0 px-2" />
							</div>
						</template>
					</Column>

					<!-- Aktionen -->
					<Column header="Aktionen" style="width: 1%; white-space: nowrap;" class="text-left">
						<template #body="{ data }">
							<SplitButton label="" icon="pi pi-cog" :model="getActions(data)" class="p-button-sm" />
						</template>
					</Column>
				</DataTable>
			</template>
		</Card>
	</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'

import { useToast } from 'primevue/usetoast'

const toast = useToast()

// Estado
const clients = ref([])
const sortField = ref('client_group_number')
const sortOrder = ref(1)
const filters = ref({
	kundenartikelklassifikation: '',
	kundenname: '',
	klassifizierung: null,
	verantwortlicher: null,
	profit_center: null,
})

function getClassificationClass(code) {
	const c = (code || '').toLowerCase()
	return {
		a: 'class-a',
		b: 'class-b',
		c: 'class-c',
		d: 'class-d',
		x: 'class-x',
		pa: 'class-pa',
		pb: 'class-pb',
	}[c] || 'class-x'
}


// Toast


// Filtros dinámicos
const classificationOptions = ref([])
const responsibleOptions = ref([])
const profitCenterOptions = ref([])

// Fetch inicial
onMounted(async () => {
	try {
		const { data } = await api.get('/api/settings/clients')
		clients.value = Array.isArray(data)
			? data.map((client) => ({
				...client,
				profit_center_names: (client.profit_centers || []).map((pc) => pc.name),
			}))
			: []

		// Dinámicos para dropdowns
		classificationOptions.value = [...new Set(clients.value.map((c) => c.classification))].map((v) => ({
			label: v,
			value: v,
		}))

		responsibleOptions.value = [...new Set(clients.value.map((c) => c.responsible_name))].map((v) => ({
			label: v,
			value: v,
		}))

		const allPCs = clients.value.flatMap((c) => c.profit_center_names)
		profitCenterOptions.value = [...new Set(allPCs)].map((v) => ({ label: v, value: v }))
	} catch (err) {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: err?.message || 'Unbekannter Fehler',
		})
	}
})

/**
 * Filtro computado local por campos individuales
 */
const filteredClients = computed(() => {
	return clients.value.filter((c) => {
		const f = filters.value

		return (
			(!f.kundenartikelklassifikation || String(c.client_group_number).includes(f.kundenartikelklassifikation)) &&
			(!f.kundenname || c.client_name?.toLowerCase().includes(f.kundenname.toLowerCase())) &&
			(!f.klassifizierung || c.classification === f.klassifizierung) &&
			(!f.verantwortlicher || c.responsible_name === f.verantwortlicher) &&
			(!f.profit_center || c.profit_center_names.includes(f.profit_center))
		)
	})
})

function onSort(e) {
	sortField.value = e.sortField
	sortOrder.value = e.sortOrder
}

/**
 * Devuelve la URL completa del avatar o null
 * @param {string|null} path
 * @returns {string|null}
 */
function getAvatarUrl(path) {
	return path ? path.replace(/^\/?storage\//, '/storage/') : null
}

/**
 * Devuelve las iniciales F.P. del responsable
 * @param {string} name
 * @param {string} surname
 * @returns {string}
 */
function getInitials(name, surname) {
	const first = name?.trim().charAt(0).toUpperCase() || ''
	const last = surname?.trim().charAt(0).toUpperCase() || ''
	return `${first}${last}`
}


function getActions(client) {
	return [
		{
			label: 'Profit-Center hinzufügen',
			icon: 'pi pi-plus',
			command: () => toast.add({ severity: 'info', summary: 'Aktion', detail: `Profit-Center für ${client.client_name}` }),
		},
		{
			label: 'Kunde bearbeiten',
			icon: 'pi pi-pencil',
			command: () => toast.add({ severity: 'info', summary: 'Bearbeiten', detail: `Kunde ${client.client_name}` }),
		},
		{
			label: 'Kunde löschen',
			icon: 'pi pi-trash',
			command: () => toast.add({ severity: 'warn', summary: 'Löschen', detail: `Kunde ${client.client_name} löschen` }),
		},
	]
}
</script>

<style scoped>
.p-chip-custom {
	display: flex;
	align-items: center;
	font-size: 0.75rem;
}

.p-chip-secondary {
	background-color: #f0f0f0;
	color: #333;
	font-size: 0.75rem;
	padding: 0.1rem 0.4rem;
}

.classification-badge {
	width: 1.4rem;
	height: 1.4rem;
	border-radius: 50%;
	font-weight: bold;
	color: white;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	font-size: 0.8rem;
}

.class-a {
	background-color: #668c73;
}

/* Azul */
.class-b {
	background-color: #59768e;
}

/* Verde */
.class-c {
	background-color: #978b4b;
}

/* Amarillo */
.class-d {
	background-color: #a3535b;
}

/* Rojo */
.class-x {
	background-color: #8c8c8c;
}

/* Gris */
.class-pa {
	background-color: #91b79d;
}

/* Violeta */
.class-pb {
	background-color: #86a2bd;
}
</style>
