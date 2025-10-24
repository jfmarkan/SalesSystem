<template>
	<Dialog
		v-model:visible="visible"
		:modal="true"
		:draggable="false"
		:dismissableMask="true"
		header="Kunde"
		:style="{ width: '640px' }"
	>
		<div v-if="client" class="cv-wrap">
			<div class="cv-head">
				<div class="cv-title">
					<div class="cv-name">{{ client.client_name }}</div>
					<div class="cv-meta">#{{ client.client_group_number }}</div>
				</div>
				<div class="cv-actions">
					<Button label="Bearbeiten" icon="pi pi-pencil" @click="$emit('edit', client)" />
				</div>
			</div>

			<div class="cv-section">
				<div class="sec-title">Profit-Center</div>
				<div v-if="loading" class="muted">Lädt…</div>
				<div v-else class="pc-grid">
					<span v-for="pc in pcs" :key="pc.profit_center_code" class="pc-pill">
						<i class="pi pi-building"></i>
						<span>{{ pc.profit_center_name || pc.profit_center_code }}</span>
					</span>
					<div v-if="!pcs?.length" class="muted">Keine Zuordnungen</div>
				</div>
			</div>
		</div>
		<template #footer>
			<Button label="Schließen" severity="secondary" @click="visible = false" />
		</template>
	</Dialog>
</template>

<script setup>
import { computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const props = defineProps({
	modelValue: { type: Boolean, default: false },
	client: { type: Object, default: null },
	pcs: { type: Array, default: () => [] },
	loading: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'edit'])

const visible = computed({
	get: () => props.modelValue,
	set: (v) => emit('update:modelValue', v),
})
</script>

<style scoped>
.cv-wrap {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
.cv-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.cv-title {
	display: flex;
	flex-direction: column;
}
.cv-name {
	font-weight: 800;
	font-size: 1.05rem;
}
.cv-meta {
	color: var(--muted);
	font-size: 0.9rem;
}
.cv-actions {
	display: flex;
	gap: 8px;
}

.cv-section {
	display: flex;
	flex-direction: column;
	gap: 8px;
}
.sec-title {
	font-weight: 700;
}

.pc-grid {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}
.pc-pill {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 6px 10px;
	border: 1px solid var(--border);
	border-radius: 999px;
	background: color-mix(in oklab, var(--surface) 85%, transparent);
}
.muted {
	color: var(--muted);
}
</style>
