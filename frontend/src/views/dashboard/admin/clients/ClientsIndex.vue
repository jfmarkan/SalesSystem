<!-- src/views/admin/clients/ClientsIndex.vue -->
<template>
	<div class="container-fluid clients-index">
		<div class="row">
			<!-- Lista izquierda -->
			<div class="span-12 md-span-4 xl-span-3">
				<GlassCard :title="''" class="no-strip h-full">
					<div class="p-2">
						<InputText v-model="q" class="input" placeholder="Suchen…" />
					</div>
					<div class="list">
						<div
							v-for="c in filtered"
							:key="c.client_group_number"
							class="item"
							:class="{
								active: selected?.client_group_number === c.client_group_number,
							}"
							@click="select(c)"
						>
							<div class="line1">{{ c.client_name }}</div>
							<div class="line2">#{{ c.client_group_number }}</div>
						</div>
					</div>
				</GlassCard>
			</div>

			<!-- Editor derecha -->
			<div class="span-12 md-span-8 xl-span-9">
				<GlassCard :title="''" class="no-strip h-full">
					<div v-if="!selected" class="placeholder">Kunde auswählen</div>

					<div v-else class="editor">
						<div class="row">
							<div class="span-12 md-span-4">
								<FloatLabel>
									<InputNumber
										inputId="cg"
										v-model="form.client_group_number"
										disabled
										:useGrouping="false"
										class="w-100"
									/>
									<label for="cg">Kundennummer</label>
								</FloatLabel>
							</div>
							<div class="span-12 md-span-8">
								<FloatLabel>
									<InputText id="cn" v-model="form.client_name" class="input" />
									<label for="cn">Kundenname</label>
								</FloatLabel>
							</div>
							<div class="span-12 md-span-6">
								<Dropdown
									v-model="form.classification_id"
									:options="classOptions"
									optionLabel="classification"
									optionValue="id"
									placeholder="Klassifikation"
									class="w-100"
								/>
							</div>
							<div class="span-12 md-span-6">
								<Dropdown
									v-model="responsibleId"
									:options="userOptions"
									optionLabel="label"
									optionValue="value"
									placeholder="Verantwortlich"
									class="w-100"
								/>
							</div>
						</div>

						<hr class="div" />

						<div class="pcs">
							<div class="pcs-title">Profit-Center</div>
							<div class="pcs-grid">
								<label v-for="code in PC_CODES" :key="code" class="pc-check">
									<input type="checkbox" :value="code" v-model="pcSelected" />
									<span>{{ code }}</span>
								</label>
							</div>
						</div>

						<div class="actions">
							<Button label="Speichern" icon="pi pi-save" @click="onSave" />
							<Button
								label="Blockieren"
								icon="pi pi-lock"
								severity="warn"
								@click="onBlock"
							/>
							<Button
								label="Löschen"
								icon="pi pi-trash"
								severity="danger"
								@click="onDelete"
							/>
						</div>
					</div>
				</GlassCard>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import GlassCard from '@/components/ui/GlassCard.vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Dropdown from 'primevue/dropdown'
import FloatLabel from 'primevue/floatlabel'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { useAdminApi } from '@/composables/useAdminApi'

const toast = useToast()
const {
	listClients,
	listClassifications,
	getClientPCs,
	saveClient,
	setClientPCs,
	setClientResponsible,
	blockClient,
	deleteClient,
	getUsers,
} = useAdminApi()

const PC_CODES = [110, 130, 140, 141, 142, 143, 160, 170, 171, 173, 174, 175]

const q = ref('')
const clients = ref([])
const classifications = ref([])
const selected = ref(null)

const form = ref({ client_group_number: null, client_name: '', classification_id: null })
const pcSelected = ref([])
const responsibleId = ref(null)

const users = ref([])
const userOptions = computed(() =>
	users.value.map((u) => ({ label: `${u.first_name} ${u.last_name}`, value: u.id })),
)
const classOptions = computed(() => classifications.value)

const filtered = computed(() => {
	const s = q.value.trim().toLowerCase()
	if (!s) return clients.value
	return clients.value.filter(
		(c) =>
			String(c.client_name).toLowerCase().includes(s) ||
			String(c.client_group_number).includes(s),
	)
})

function mapIn(pcList) {
	const set = new Set(pcList.map((p) => Number(p.profit_center_code || p.profit_center_code)))
	return PC_CODES.filter((code) => set.has(Number(code)))
}

async function select(c) {
	selected.value = c
	form.value = {
		client_group_number: c.client_group_number,
		client_name: c.client_name,
		classification_id: c.classification_id || null,
	}
	responsibleId.value = null
	const pcs = await getClientPCs(c.client_group_number)
	pcSelected.value = mapIn(pcs)
}

async function onSave() {
	try {
		await saveClient(form.value.client_group_number, {
			client_name: form.value.client_name,
			classification_id: form.value.classification_id,
		})
		await setClientPCs(form.value.client_group_number, pcSelected.value)
		if (responsibleId.value) {
			await setClientResponsible(form.value.client_group_number, responsibleId.value)
		}
		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Kunde aktualisiert',
			life: 1800,
		})
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Speichern fehlgeschlagen',
			life: 2200,
		})
	}
}
async function onBlock() {
	try {
		await blockClient(form.value.client_group_number)
		toast.add({ severity: 'warn', summary: 'Blockiert', detail: 'Kunde blockiert', life: 1800 })
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Aktion fehlgeschlagen',
			life: 2200,
		})
	}
}
async function onDelete() {
	try {
		await deleteClient(form.value.client_group_number)
		toast.add({
			severity: 'success',
			summary: 'Gelöscht',
			detail: 'Kunde gelöscht',
			life: 1800,
		})
		selected.value = null
		await load()
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Aktion fehlgeschlagen',
			life: 2200,
		})
	}
}

async function load() {
	const [cl, cls, us] = await Promise.all([listClients(), listClassifications(), getUsers()])
	clients.value = cl
	classifications.value = cls
	users.value = us
}
onMounted(load)
</script>

<style scoped>
.clients-index {
	margin-top: calc(var(--navbar-h) + 12px);
}
.list {
	max-height: calc(100vh - var(--navbar-h) - 120px);
	overflow: auto;
	padding: 0 6px 8px;
}
.item {
	padding: 8px;
	border: 1px solid var(--border);
	border-radius: 10px;
	background: color-mix(in oklab, var(--surface) 88%, transparent);
	cursor: pointer;
	margin-bottom: 8px;
}
.item.active {
	border-color: var(--primary);
	box-shadow: var(--ring);
}
.line1 {
	font-weight: 700;
}
.line2 {
	font-size: 0.85rem;
	color: var(--muted);
}

.placeholder {
	min-height: 240px;
	display: grid;
	place-items: center;
	color: var(--muted);
}
.editor {
	padding: 10px;
}
.pcs-title {
	font-weight: 700;
	margin-bottom: 6px;
}
.pcs-grid {
	display: grid;
	grid-template-columns: repeat(6, minmax(0, 1fr));
	gap: 8px;
}
@media (max-width: 991.98px) {
	.pcs-grid {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}
}
.pc-check {
	display: flex;
	align-items: center;
	gap: 8px;
	border: 1px solid var(--border);
	padding: 8px;
	border-radius: 10px;
}
.actions {
	margin-top: 12px;
	display: flex;
	gap: 10px;
}
</style>
