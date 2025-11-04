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

    <div class="list-wrap">
      <label class="list-caption">
        {{ m === 'client' ? 'Profit Center pro Kunde' : 'Kunden im Profit Center' }}
      </label>

      <!-- 👇 OJO: la clase cae en el MISMO div que .p-listbox -->
      <Listbox
        class="listbox-grow"
        v-model="sid"
        :options="secondaryOptions"
        optionLabel="label"
        optionValue="value"
        :disabled="!m || pid == null"
      >
        <template #option="{ option }">
          <span v-html="option.label"></span>
        </template>
      </Listbox>
    </div>
  </div>
</template>

<style scoped>
/* ====== LAYOUT BASE ====== */
.filter-wrap {
  height: 100%;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 0;
  min-height: 0; /* ✅ clave para que el hijo pueda calcular alto */
}
.filter-wrap > * {
  min-width: 0;
}

/* ====== BOTONES SUPERIORES ====== */
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
}
.m-btn.active {
  background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
  color: white;
  border: none;
}

/* ====== SELECT (ancho 100% fijo) ====== */
.select-row {
  flex: 0 0 auto;
  width: 100%;
  overflow: hidden;
}
.select-full {
  width: 100% !important;
  min-width: 0 !important;
  max-width: 100% !important;
  box-sizing: border-box;
}
:deep(.p-select) {
  width: 100%;
  max-width: 100%;
  overflow: hidden;          /* evita ensanche */
}

:deep(.p-select .p-select-label) {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

:deep(.p-select-panel) {
  max-width: 100vw;          /* opcional: evita paneles más anchos que la ventana */
}

/* ====== LISTA (llena el resto del alto) ====== */
.list-wrap {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
}

/* el root de Listbox */
.listbox-grow {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  width: 100%;
}

.listbox-grow :deep(.p-listbox) {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
  height: auto !important;
}
.listbox-grow :deep(.p-listbox-list-wrapper) {
  flex: 1 1 auto;
  overflow-y: auto;
  min-height: 0;
  max-height: none;
}

/* anulá el alto predeterminado del tema */
.listbox-grow :deep(.p-listbox-list) {
  height: 100%;
  flex: 1 1 auto;
  min-height: 0;
  max-height: none !important;
}

.list-caption {
  font-size: 0.85rem;
  opacity: 0.9;
  flex: 0 0 auto;
}

/* ====== BOTÓN INFERIOR ====== */
.btn-next {
  margin-top: auto;
  width: 100%;
  flex: 0 0 auto;
}
</style>
