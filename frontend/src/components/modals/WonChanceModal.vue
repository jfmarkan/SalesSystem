<template>
	<Dialog v-model:visible="internalVisible" :draggable="false" modal :pt="{
		root: { class: '!border-0 !bg-transparent' },
		mask: { class: 'backdrop-blur-sm' }
	}">
		<template #container>
			<div class="wc-card">
				<div class="wc-head">
					<div class="wc-icon">
						<i class="pi pi-check"></i>
					</div>
					<div class="wc-title">Chance finalisieren</div>
					<div class="wc-sub">Bitte Daten vervollständigen, um in Budget/Forecast zu übernehmen</div>
				</div>

				<div class="wc-body">
					<div class="wc-field">
						<label class="wc-label">Kundenname</label>
						<InputText v-model="clientName" class="w-full" :disabled="true" />
						<small class="wc-help">Wird aus dem potenziellen Kunden der Chance übernommen.</small>
					</div>

					<div class="wc-field">
						<label class="wc-label">Kundennummer (Client Group Number)</label>
						<InputText v-model.trim="clientNumber" class="w-full" placeholder="z. B. 12345"
							@blur="checkExisting" />
						<small v-if="isExistingClient" class="wc-help">
							Kunde vorhanden. Klassifizierung: <b>{{ existingClassificationLabel || '—' }}</b>.
							Die Auswahl ist deaktiviert.
						</small>
						<small v-else class="wc-help">
							Falls der Kunde nicht existiert, wähle seine Klassifizierung (Potenzial A/B).
						</small>
					</div>

					<div class="wc-field">
						<label class="wc-label">Klassifizierung</label>
						<Select v-model="classificationId" :options="classificationOptions" optionLabel="label"
							optionValue="value" class="w-full" :disabled="isExistingClient"
							placeholder="Klassifizierung wählen…" />
						<small v-if="!isExistingClient" class="wc-help">
							Nur „Potenzial A (6)“ oder „Potenzial B (7)“.
						</small>
					</div>
				</div>

				<div class="wc-actions">
					<Button label="Abbrechen" severity="secondary" @click="close" />
					<Button label="Bestätigen" icon="pi pi-check" @click="emitFinalize" />
				</div>
			</div>
		</template>
	</Dialog>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

const props = defineProps({
	visible: { type: Boolean, default: false },
	lookupClientByNumber: { type: Function, required: true },
	initialClientNumber: { type: [String, Number], default: '' },
	initialClientName: { type: String, default: '' },
	profitCenterCode: { type: [String, Number], default: null },
	fiscalYear: { type: [String, Number], default: null },
})
const emit = defineEmits(['update:visible', 'finalize'])

const internalVisible = ref(props.visible)
watch(() => props.visible, v => (internalVisible.value = v))
watch(internalVisible, v => emit('update:visible', v))

const clientNumber = ref('')
const clientName = ref('')

// Sólo disponibles si NO existe el cliente
const classificationOptions = ref([
	{ label: 'Potenzial A', value: 6 },
	{ label: 'Potenzial B', value: 7 },
])
const classificationId = ref(null)

const isExistingClient = ref(false)
const existingClassificationId = ref(null)

const existingClassificationLabel = computed(() => {
	const id = Number(existingClassificationId.value ?? 0)
	if (!id) return ''
	if (id === 1) return 'A'
	if (id === 2) return 'B'
	if (id === 3) return 'C'
	if (id === 6) return 'Potenzial A'
	if (id === 7) return 'Potenzial B'
	return `ID ${id}`
})

function close() {
	internalVisible.value = false
}

// ---- Sincronizar props al ABRIR el modal
function syncFromProps() {
	clientName.value = String(props.initialClientName || '')
	clientNumber.value = String(props.initialClientNumber || '')
	// reset de estado
	isExistingClient.value = false
	existingClassificationId.value = null
	classificationId.value = null
	// si vino número, verificamos
	if (clientNumber.value.trim()) {
		checkExisting()
	}
}

watch(internalVisible, (v) => {
	if (v) syncFromProps()
})

// Mantener datos preparados si cambian las props con el modal cerrado
watch(() => props.initialClientName, (v) => {
	if (!internalVisible.value) clientName.value = String(v || '')
})
watch(() => props.initialClientNumber, (v) => {
	if (!internalVisible.value) clientNumber.value = String(v || '')
})

// ---- Debounce al escribir el número
let numTimer = null
watch(clientNumber, (val) => {
	const v = (val || '').trim()
	if (numTimer) clearTimeout(numTimer)
	if (!v) {
		// si está vacío, NO es existente y habilitamos el select
		isExistingClient.value = false
		existingClassificationId.value = null
		return
	}
	numTimer = setTimeout(() => checkExisting(), 300)
})

async function checkExisting() {
	const num = (clientNumber.value || '').trim()
	if (!num) {
		isExistingClient.value = false
		existingClassificationId.value = null
		return
	}
	try {
		const data = await props.lookupClientByNumber(num)
		if (data && data.client_number) {
			// Existe → bloquear clasificación y limpiar cualquier selección previa
			isExistingClient.value = true
			existingClassificationId.value = data.classification_id ?? null
			classificationId.value = null
			if (data.name && !clientName.value) clientName.value = data.name
		} else {
			// No existe → habilitar selección
			isExistingClient.value = false
			existingClassificationId.value = null
			// mantenemos lo que el usuario haya elegido
		}
	} catch {
		// en caso de error de red, no bloqueamos
		isExistingClient.value = false
		existingClassificationId.value = null
	}
}

function emitFinalize() {
	const out = {
		client_group_number: clientNumber.value,
		client_name: clientName.value,
		// Si existe el cliente, NO mandamos clasificación; si no existe, enviamos 6/7 (o null si no eligió)
		classification_id: isExistingClient.value ? null : (classificationId.value ?? null),
	}
	emit('finalize', out)
	close()
}

onMounted(() => {
	// por si el modal ya abre visible con datos
	if (internalVisible.value) syncFromProps()
})
</script>

<style scoped>
.wc-card {
	width: min(520px, 92vw);
	border-radius: 16px;
	background: var(--surface-card, #fff);
	box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
	display: flex;
	flex-direction: column;
	padding: 20px;
}

.wc-head {
	text-align: center;
	margin-bottom: 12px;
}

.wc-icon {
	width: 56px;
	height: 56px;
	border-radius: 999px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	background: color-mix(in oklab, var(--p-primary-500) 15%, transparent);
	color: var(--p-primary-600);
}

.wc-icon .pi {
	font-size: 20px;
}

.wc-title {
	margin-top: 10px;
	font-weight: 800;
	font-size: 1.1rem;
}

.wc-sub {
	margin-top: 4px;
	font-size: .9rem;
	opacity: .7;
}

.wc-body {
	display: grid;
	gap: 10px;
	margin-top: 10px;
}

.wc-field {
	display: flex;
	flex-direction: column;
	gap: 6px;
}

.wc-label {
	font-weight: 600;
}

.wc-help {
	font-size: .85rem;
	opacity: .75;
}

.wc-actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 16px;
}

/* blur del backdrop */
:deep(.backdrop-blur-sm) {
	backdrop-filter: blur(6px);
}
</style>
