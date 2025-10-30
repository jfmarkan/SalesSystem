<template>
	<Card class="sidebar-card">
		<template #content>
			<!-- Tabs -->
			<div class="tab-buttons-row">
				<Button label="Offen" :outlined="tab !== 'open'" :severity="tab === 'open' ? 'primary' : null"
					size="large" class="w-1/2" @click="$emit('update:tab', 'open')" />
				<Button label="Begründet" :outlined="tab !== 'just'" :severity="tab === 'just' ? 'primary' : null"
					size="large" class="w-1/2" @click="$emit('update:tab', 'just')" />
			</div>

			<!-- Lista -->
			<div class="deviation-list">
				<div class="list-item" v-for="dev in list" :key="dev.id" :class="{ selected: selectedId === dev.id }"
					@click="$emit('select', dev)">
					<div class="list-item-title">{{ dev.pcName }}</div>
					<div class="list-item-meta">{{ dev.year }}-{{ String(dev.month).padStart(2, '0') }} | {{ dev.type
						=== 'forecast' ? 'Forecast' : 'Ist' }}</div>
				</div>
			</div>

			<!-- Footer -->
			<div class="list-footer">Total: {{ list.length }}</div>
		</template>
	</Card>
</template>

<script setup>
import Card from 'primevue/card'
import Button from 'primevue/button'

defineProps({
	tab: String,
	list: Array,
	selectedId: Number
})
defineEmits(['update:tab', 'select'])
</script>

<style scoped>
.sidebar-card {
	display: flex;
	flex-direction: column;
	height: 100%;
	overflow: hidden;
}

.tab-buttons-row {
	display: flex;
	gap: 8px;
	margin-bottom: 8px;
}

/* Lista scrollable */
.deviation-list {
	flex: 1;
	min-height: 0;
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	gap: 6px;
	max-height: calc(100% - 96px);
	/* Ajustado para dejar espacio a botones + footer */
}

.list-item {
	padding: 8px;
	border-radius: 6px;
	cursor: pointer;
	font-size: 0.875rem;
	background: var(--surface-100);
	border: 1px solid transparent;
}

.list-item:hover {
	background: var(--surface-200);
}

.list-item.selected {
	border: 1px solid var(--primary);
	background: var(--primary-light);
}

.list-item-title {
	font-weight: 600;
}

.list-item-meta {
	font-size: 0.75rem;
	color: var(--text-muted);
}

.list-footer {
	font-size: 0.75rem;
	color: var(--text-muted);
	padding-top: 6px;
	text-align: right;
}
</style>
