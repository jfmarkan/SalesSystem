<!-- src/components/filters/ComponentFilter.vue -->
<script setup>
import { computed } from 'vue'

const props = defineProps({
	mode: { type: String, required: true },
	primaryOptions: { type: Array, required: true },
	primaryId: { type: [Number, String, null], required: true },
	secondaryOptions: { type: Array, required: true },
	secondaryId: { type: [Number, String, null], required: true },
})
const emit = defineEmits(['update:mode', 'update:primary-id', 'update:secondary-id', 'next'])

const m = computed({ get: () => props.mode, set: (v) => emit('update:mode', v) })
const pid = computed({ get: () => props.primaryId, set: (v) => emit('update:primary-id', v) })
const sid = computed({ get: () => props.secondaryId, set: (v) => emit('update:secondary-id', v) })

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
		<div class="mode-toggle">
			<Button
				type="button"
				:class="['m-btn', m === 'client' && 'active']"
				:aria-pressed="m === 'client'"
				@click="setMode('client')"
			>
				Kunde
			</Button>
			<Button
				type="button"
				:class="['m-btn', m === 'pc' && 'active']"
				:aria-pressed="m === 'pc'"
				@click="setMode('pc')"
			>
				Profit Center
			</Button>
		</div>

		<div class="select-row">
			<select class="select-native" v-model="pid" :disabled="!m">
				<option :value="null" disabled>Wählen</option>
				<option v-for="o in primaryOptions" :key="o.value" :value="o.value">
					{{ o.label }}
				</option>
			</select>
		</div>

		<div class="list-wrap">
			<label class="list-caption">{{
				m === 'client' ? 'Profit Center pro Kunde' : 'Kunden im Profit Center'
			}}</label>

			<Listbox
				class="listbox-grow"
				v-model="sid"
				:options="secondaryOptions"
				optionLabel="label"
				optionValue="value"
				:disabled="!m || pid == null"
				listStyle="max-height:100%"
			>
				<template #option="{ option }">
					<span v-html="option.label"></span>
				</template>
			</Listbox>
		</div>
	</div>
</template>

<style scoped>
/* layout base */
.filter-wrap {
	height: 100%;
	min-height: 0;
	display: flex;
	flex-direction: column;
	gap: 10px;
}
.mode-toggle {
	display: flex;
	gap: 8px;
}
.m-btn {
	flex: 1;
	padding: 8px 12px;
	border-radius: 10px;
	border: 1px solid var(--input-border);
	font-weight: 500;
	cursor: pointer;
}
.m-btn.active {
	background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
	color: white;
	border: none;
}

.select-native {
	width: 100%;
	height: 36px;
	padding: 6px 10px;
	border-radius: 10px;
	border: 1px solid var(--input-border);
	background: var(--input-bg);
	color: var(--text);
	outline: none;
}

.list-caption {
	font-size: 0.85rem;
	opacity: 0.9;
}

.btn-next {
	margin-top: auto;
	width: 100%;
}
</style>
