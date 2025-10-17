<template>
	<div class="theme-group">
		<ButtonGroup>
			<Button
				:severity="is('auto') ? 'primary' : 'secondary'"
				:outlined="!is('auto')"
				rounded
				@click="set('auto')"
				aria-label="Automatisch"
			> Auto
			</Button>

			<Button
				:severity="is('light') ? 'primary' : 'secondary'"
				:outlined="!is('light')"
				rounded
				@click="set('light')"
				aria-label="Hell"
			>
				<i class="pi pi-sun" /> Hell
			</Button>

			<Button
				:severity="is('dark') ? 'primary' : 'secondary'"
				:outlined="!is('dark')"
				rounded
				@click="set('dark')"
				aria-label="Dunkel"
			>
				<i class="pi pi-moon" /> Dunkel
			</Button>
		</ButtonGroup>
	</div>
</template>

<script setup>
import Button from 'primevue/button'
import ButtonGroup from 'primevue/buttongroup'
import { useTheme } from '@/composables/useTheme'
const { mode, setMode } = useTheme()
const is = (v) => mode.value === v
const set = (v) => setMode(v)
</script>

<style scoped>
.theme-group {
	display: flex;
	justify-content: center;
}
:deep(.p-buttongroup .p-button) {
	padding: 0.5rem 0.6rem;
}
:deep(.p-button .pi + .pi) {
	margin-left: 0.35rem;
}
</style>
