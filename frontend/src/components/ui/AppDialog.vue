<template>
  <Dialog
    v-model:visible="isVisible"
    :header="modalTitle"
    modal
    class="custom-modal"
    :closable="false"
  >
    <p class="m-0">{{ modalMessage }}</p>
    <template #footer>
      <Button label="OK" class="p-button-text" @click="close" />
    </template>
  </Dialog>
</template>

<script setup>
import { useModal } from '@/composables/useModal'

const { isVisible, modalTitle, modalMessage, close } = useModal()
</script>

<style scoped>
.custom-modal {
  width: 90vw;
  max-width: 400px;
}
</style>
