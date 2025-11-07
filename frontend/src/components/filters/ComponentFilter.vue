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
    <!-- Botones de modo -->
    <div class="mode-toggle">
      <Button
        type="button"
        :class="['m-btn', m === 'client' && 'active']"
        :aria-pressed="m === 'client'"
        @click="setMode('client')"
      >Kunde</Button>
      <Button
        type="button"
        :class="['m-btn', m === 'pc' && 'active']"
        :aria-pressed="m === 'pc'"
        @click="setMode('pc')"
      >Profit Center</Button>
    </div>

    <!-- Select primario -->
    <div class="select-row">
      <Select
        v-model="pid"
        :options="primaryOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="Wählen"
        :disabled="!m"
        class="select-full"
      />
    </div>

    <!-- Lista secundaria con scroll -->
    <div class="list-wrap">
      <label class="list-caption">
        {{ m === 'client' ? 'Profit Center pro Kunde' : 'Kunden im Profit Center' }}
      </label>

      <Listbox
        v-model="sid"
        :options="secondaryOptions"
        optionLabel="label"
        optionValue="value"
		class="w-full h-full"
        :disabled="!m || pid == null"
		:listStyle="{ height: '100%' }"
      >
        <template #option="{ option }">
          <span v-html="option.label"></span>
        </template>
      </Listbox>
    </div>
  </div>
</template>

<style scoped>
.filter-wrap {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
  gap: 10px;
  min-height:0;
  height: 100%;
}

/* Botones modo */
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
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.m-btn.active {
  background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
  color: white;
  border: none;
}

/* Select primario */
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

/* Scroll interno del listbox SOLAMENTE */
.list-wrap {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden; /* 💡 clave para el scroll interno */
}

.list-caption {
  font-size: 0.85rem;
  opacity: 0.9;
  flex: 0 0 auto;
}

/* Listbox con scroll interno */
.listbox {
  flex: 1 1 auto;
  min-height: 0;
  overflow: auto;
  height: 100%;
}

.listbox :deep(.p-listbox) {
  height: 100%;
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height:0;
}
.listbox :deep(.p-listbox-list-container) {
  flex: 1 1 auto;
  overflow-y: auto;
  height: 100%;
  min-height: 0;
}

.listbox :deep(.p-listbox-list) {
  flex: 1 1 auto;
  height: 100%;
  min-height: 0;
}

/* Botón footer siempre visible */
.btn-footer {
  flex: 0 0 auto;
}

.btn-next {
  width: 100%;
}
</style>
