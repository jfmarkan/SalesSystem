<template>
	<div class="mini-card">
		<div class="mc-head">
			<div class="title">
				Kunden <span class="count">{{ total }}</span>
			</div>
			<div class="actions">
				<Button label="Neu" icon="pi pi-plus" size="small" @click="$emit('create')" />
			</div>
		</div>

		<div class="mc-search">
			<span class="pi pi-search"></span>
			<input class="input" v-model="q" placeholder="Suchen…" />
		</div>

		<div class="mc-list">
			<div v-for="c in items" :key="c.client_group_number" class="row">
				<div class="left">
					<div class="name">{{ c.client_name }}</div>
					<div class="meta">#{{ c.client_group_number }}</div>
				</div>
				<div class="right">
					<Button
						icon="pi pi-eye"
						text
						rounded
						@click="$emit('view', c)"
						v-tooltip.top="'Ansehen'"
					/>
					<Button
						icon="pi pi-pencil"
						text
						rounded
						@click="$emit('edit', c)"
						v-tooltip.top="'Bearbeiten'"
					/>
					<Button
						icon="pi pi-trash"
						text
						rounded
						severity="danger"
						@click="$emit('delete', c)"
						v-tooltip.top="'Löschen'"
					/>
				</div>
			</div>
			<div v-if="!items?.length" class="empty muted">Keine Treffer</div>
		</div>

		<div class="mc-pager">
			<Paginator
				:rows="perPage"
				:totalRecords="total"
				:first="(page - 1) * perPage"
				@page="onPage"
				:rowsPerPageOptions="[10, 20, 30, 50]"
				@update:rows="(val) => $emit('update:perPage', val)"
			/>
		</div>
	</div>
</template>

<script setup>
import { ref, watch } from 'vue'
import Button from 'primevue/button'
import Paginator from 'primevue/paginator'

const props = defineProps({
	items: { type: Array, default: () => [] }, // página actual ya filtrada desde el padre
	total: { type: Number, default: 0 },
	page: { type: Number, default: 1 },
	perPage: { type: Number, default: 20 },
	query: { type: String, default: '' },
})
const emit = defineEmits([
	'create',
	'view',
	'edit',
	'delete',
	'update:page',
	'update:perPage',
	'update:query',
])

const q = ref(props.query || '')
watch(q, (v) => {
	// debounce simple
	clearTimeout(timer)
	timer = setTimeout(() => emit('update:query', v.trim()), 250)
})
let timer

function onPage(e) {
	// e.first, e.rows, e.page (0-based)
	const next = Number(e.page) + 1
	emit('update:page', next)
}
</script>

<style scoped>
.mini-card {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.mc-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.title {
	font-weight: 800;
	display: inline-flex;
	gap: 8px;
	align-items: baseline;
}
.title .count {
	font-weight: 300;
	color: var(--muted);
}
.actions {
	display: flex;
	align-items: center;
	gap: 10px;
}
.muted {
	color: var(--muted);
}

.mc-search {
	position: relative;
}
.mc-search .pi {
	position: absolute;
	left: 10px;
	top: 50%;
	transform: translateY(-50%);
	color: var(--muted);
}
.mc-search .input {
	padding-left: 32px;
}

.mc-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-height: calc(100vh - var(--navbar-h) - 260px);
	overflow: auto;
	padding-right: 4px;
}
.row {
	display: grid;
	grid-template-columns: 1fr auto;
	gap: 10px;
	align-items: center;
	padding: 8px;
	border: 1px solid var(--border);
	border-radius: 12px;
	background: color-mix(in oklab, var(--surface) 90%, transparent);
}
.left .name {
	font-weight: 700;
}
.left .meta {
	font-size: 0.85rem;
	color: var(--muted);
}
.right {
	display: flex;
	gap: 6px;
}
.empty {
	text-align: center;
	padding: 8px 0;
}
.mc-pager {
	padding-top: 6px;
}
</style>
