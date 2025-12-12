<script setup>
import { computed } from 'vue'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Listbox from 'primevue/listbox'

const props = defineProps({
  mode: { type: String, required: true },
  primaryOptions: { type: Array, required: true },
  primaryId: { type: [Number, String, null], required: true },
  secondaryOptions: { type: Array, required: true },
  secondaryId: { type: [Number, String, null], required: true },

  /* 👇 NUEVO: sólo BudgetCase lo va a usar */
  showStatusIcons: { type: Boolean, default: false },
})

const emit = defineEmits(['update:mode', 'update:primary-id', 'update:secondary-id'])

const m = computed({
  get: () => props.mode,
  set: v => emit('update:mode', v),
})

const pid = computed({
  get: () => props.primaryId,
  set: v => emit('update:primary-id', v),
})

const sid = computed({
  get: () => props.secondaryId,
  set: v => emit('update:secondary-id', v),
})

function setMode(v) {
  if (v !== m.value) {
    m.value = v
    pid.value = null
    sid.value = null
  }
}
</script>

<template>
  <div class="ff-root">
    <!-- TOP -->
    <div class="ff-top">
      <div class="mode-toggle">
        <Button
          type="button"
          :class="['m-btn', m === 'client' && 'active']"
          :aria-pressed="m === 'client' || undefined"
          @click="setMode('client')"
        >
          Kunde
        </Button>

        <Button
          type="button"
          :class="['m-btn', m === 'pc' && 'active']"
          :aria-pressed="m === 'pc' || undefined"
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
    </div>

    <!-- MIDDLE: Listbox que se estira y scrollea adentro -->
    <div class="ff-middle">
      <label class="list-caption">
        {{ m === 'client' ? 'Profit Center pro Kunde' : 'Kunden im Profit Center' }}
      </label>

      <div class="list-scroll">
        <Listbox
          v-model="sid"
          :options="secondaryOptions"
          optionLabel="label"
          optionValue="value"
          class="listbox"
          :disabled="!m || pid == null"
        >
          <!-- 👇 AQUÍ la magia: si showStatusIcons = true => íconos; si no, texto plano -->
          <template #option="{ option }">
            <span
              v-if="showStatusIcons"
              class="option-with-icon"
            >
              <i
                :class="[
                  'pi',
                  option?.hasCase
                    ? 'pi-check-circle legend-icon legend-icon-done'
                    : 'pi-circle legend-icon legend-icon-pending'
                ]"
              />
              <span class="option-label-text">
                {{ option.label }}
              </span>
            </span>

            <span v-else>
              {{ option.label }}
            </span>
          </template>
        </Listbox>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ff-root {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
}

/* TOP fijo */
.ff-top {
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 4px;
}

.mode-toggle {
  display: flex;
  gap: 8px;
}

.m-btn {
  flex: 1;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--input-border, #d1d5db);
  font-weight: 500;
  cursor: pointer;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.m-btn.active {
  background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
  color: #ffffff;
  border: none;
}

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

/* MIDDLE: bloque que ajusta */
.ff-middle {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow: hidden;
}

.list-caption {
  font-size: 0.85rem;
  opacity: 0.9;
  flex: 0 0 auto;
}

/* contenedor con scroll interno */
.list-scroll {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
}

.list-scroll :deep(.p-listbox) {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
  height: 100%;
}

.list-scroll :deep(.p-listbox-list-container),
.list-scroll :deep(.p-listbox-list-wrapper) {
  flex: 1 1 auto;
  min-height: 0;
  height: 100%;
  max-height: none !important;
  overflow-y: auto;
}

.list-scroll :deep(.p-listbox-list) {
  flex: 0 0 auto;
}

/* ===== Opciones con iconito ===== */
.option-with-icon {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem; /* separación ícono / texto */
}

.option-with-icon i {
  font-size: 0.85rem;
}

.option-label-text {
  display: inline-block;
}

/* mismos colores que la leyenda del BudgetCase */
.legend-icon-done {
  color: var(--p-green-500, #10b981);
}

.legend-icon-pending {
  color: var(--p-surface-500, #9ca3af);
}
</style>
