<script setup>
import { computed } from 'vue'

const props = defineProps({
	mode: { type: String, required: true },
	primaryOptions: { type: Array, required: true },
	primaryId: { type: [Number, String, null], required: true },
	secondaryOptions: { type: Array, required: true },
	secondaryId: { type: [Number, String, null], required: true },
})

const emit = defineEmits(['update:mode', 'update:primary-id', 'update:secondary-id'])

/**
 * Local computed bindings to support v-model style for props
 */
const m = computed({
	get: () => props.mode,
	set: (v) => emit('update:mode', v),
})

const pid = computed({
	get: () => props.primaryId,
	set: (v) => emit('update:primary-id', v),
})

const sid = computed({
	get: () => props.secondaryId,
	set: (v) => emit('update:secondary-id', v),
})

/**
 * When changing mode, reset primary/secondary selections
 */
function setMode(v) {
	if (v !== m.value) {
		m.value = v
		pid.value = null
		sid.value = null
	}
}
</script>

<template>
	<div class="filter-wrap">
		<!-- Modus-Schalter -->
		<div class="mode-toggle">
			<Button type="button" :class="['m-btn', m === 'client' && 'active']" :aria-pressed="m === 'client'"
				@click="setMode('client')">
				Kunde
			</Button>

			<Button type="button" :class="['m-btn', m === 'pc' && 'active']" :aria-pressed="m === 'pc'"
				@click="setMode('pc')">
				Profit Center
			</Button>
		</div>

		<!-- Primäre Auswahl -->
		<div class="select-row">
			<Select v-model="pid" :options="primaryOptions" optionLabel="label" optionValue="value" placeholder="Wählen"
				:disabled="!m" class="select-full" />
		</div>

		<!-- Sekundäre Liste mit internem Scroll -->
		<div class="list-wrap">
			<label class="list-caption">
				{{ m === 'client' ? 'Profit Center pro Kunde' : 'Kunden im Profit Center' }}
			</label>

			<!-- This wrapper takes all remaining height and keeps the scroll only inside the list -->
			<div class="listbox-shell">
				<Listbox v-model="sid" :options="secondaryOptions" optionLabel="label" optionValue="value"
					class="listbox" :disabled="!m || pid == null">
					<template #option="{ option }">
						<span v-html="option.label"></span>
					</template>
				</Listbox>
			</div>
		</div>
	</div>
</template>

<style scoped>
/* Root container: must be allowed to stretch by its parent (parent needs a fixed height or flex:1) */
.filter-wrap {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	min-height: 0;
	/* allow children to control internal scroll */
	height: 100%;
	/* take full height of parent */
	gap: 10px;
}

/* Mode buttons row */
.mode-toggle {
	display: flex;
	gap: 8px;
	flex: 0 0 auto;
}

.m-btn {
	flex: 1;
	padding: 8px 12px;
	border-radius: 10px;
	border: 1px solid var(--input-border);
	font-weight: 500;
	cursor: pointer;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.m-btn.active {
	background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
	color: white;
	border: none;
}

/* Primary select (fixed height area) */
.select-row {
	flex: 0 0 auto;
}

.select-full {
	width: 100% !important;
}

.select-full :deep(.p-select-label) {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* List area: takes all remaining height */
.list-wrap {
	flex: 1 1 auto;
	/* consume remaining vertical space */
	min-height: 0;
	/* important for flex scroll */
	display: flex;
	flex-direction: column;
	overflow: hidden;
	/* only inner shell scrolls */
}

.list-caption {
	font-size: 0.85rem;
	opacity: 0.9;
	flex: 0 0 auto;
}

/* Shell that hosts the Listbox and controls scroll region */
.listbox-shell {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	flex-direction: column;
}

/* PrimeVue Listbox root element */
.listbox-shell :deep(.p-listbox) {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	flex-direction: column;
}

/* Wrapper around the <ul> list: this gets the scroll */
.listbox-shell :deep(.p-listbox-list-wrapper),
.listbox-shell :deep(.p-listbox-list-container) {
	flex: 1 1 auto;
	min-height: 0;
	overflow-y: auto;
	/* internal scroll here */
}

/* The <ul> itself should not have its own fixed height */
.listbox-shell :deep(.p-listbox-list) {
	flex: 1 1 auto;
	min-height: 0;
}
</style>
