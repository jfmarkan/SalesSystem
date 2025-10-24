<template>
	<div class="users-compact">
		<div class="uc-header">
			<div class="uc-title">Benutzer</div>
			<div class="uc-actions">
				<Button label="Neu" icon="pi pi-user-plus" size="small" @click="$emit('create')" />
			</div>
		</div>

		<div class="uc-list">
			<div v-for="u in users" :key="u.id" class="uc-item" :title="u.email">
				<div class="avatar">
					<img v-if="u.profile_picture" :src="u.profile_picture" alt="" />
					<div v-else class="initials">{{ initials(u) }}</div>
					<span class="status-dot" :class="statusClass(u)"></span>
				</div>
				<div class="info">
					<div class="name">{{ u.first_name }} {{ u.last_name }}</div>
					<div class="role">{{ roleName(u.role_id) }}</div>
				</div>
				<div class="row-actions">
					<Button
						icon="pi pi-ban"
						text
						rounded
						severity="danger"
						@click.stop="$emit('block', u)"
						v-tooltip.top="'Blockieren'"
					/>
					<Button
						icon="pi pi-sign-out"
						text
						rounded
						severity="warning"
						@click.stop="$emit('kick', u)"
						v-tooltip.top="'Abmelden'"
					/>
					<Button
						icon="pi pi-pencil"
						text
						rounded
						@click.stop="$emit('edit', u)"
						v-tooltip.top="'Bearbeiten'"
					/>
				</div>
			</div>
		</div>

		<div class="uc-footer">
			<span class="muted">Online: {{ onlineCount }} / {{ users.length }}</span>
		</div>
	</div>
</template>

<script setup>
import { computed } from 'vue'
import Button from 'primevue/button'

const props = defineProps({
	users: { type: Array, default: () => [] },
	onlineMap: { type: Object, default: () => ({}) },
})

const onlineCount = computed(() => props.users.filter((u) => props.onlineMap[u.id]).length)

function initials(u) {
	const a = [u.first_name, u.last_name]
		.filter(Boolean)
		.map((s) => String(s)[0]?.toUpperCase())
		.join('')
	return a || u.username?.slice(0, 2)?.toUpperCase() || '??'
}
function roleName(id) {
	return { 1: 'Superadmin', 2: 'Admin', 3: 'Manager', 4: 'Sales Rep' }[Number(id)] || '—'
}
function statusClass(u) {
	return u.disabled ? 'off' : props.onlineMap[u.id] ? 'on' : 'idle'
}
</script>

<style scoped>
.users-compact {
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.uc-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
}
.uc-title {
	font-weight: 800;
}
.uc-actions {
	display: flex;
	gap: 8px;
}

.uc-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-height: calc(100vh - var(--navbar-h) - 260px);
	overflow: auto;
	padding-right: 4px;
}
.uc-item {
	display: grid;
	grid-template-columns: 48px 1fr auto;
	gap: 10px;
	align-items: center;
	padding: 8px;
	border: 1px solid var(--border);
	border-radius: 12px;
	background: color-mix(in oklab, var(--surface) 88%, transparent);
}
.avatar {
	position: relative;
	width: 40px;
	height: 40px;
	border-radius: 50%;
	overflow: hidden;
	background: color-mix(in oklab, var(--surface) 70%, transparent);
	display: grid;
	place-items: center;
}
.avatar img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
.initials {
	font-weight: 700;
	color: var(--text);
}
.status-dot {
	position: absolute;
	right: -2px;
	bottom: -2px;
	width: 12px;
	height: 12px;
	border-radius: 50%;
	border: 2px solid var(--surface);
}
.status-dot.on {
	background: #16a34a;
}
.status-dot.idle {
	background: #9aa3b2;
}
.status-dot.off {
	background: #b01513;
}

.info {
	display: flex;
	flex-direction: column;
	min-width: 0;
}
.name {
	font-weight: 600;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.role {
	font-size: 0.8rem;
	color: var(--muted);
}

.row-actions {
	display: flex;
	gap: 6px;
}
.uc-footer {
	display: flex;
	justify-content: flex-end;
}
.muted {
	color: var(--muted);
	font-size: 0.85rem;
}
</style>
