import { ref } from 'vue';

const isVisible = ref(false);
const modalTitle = ref('');
const modalMessage = ref('');

export const useModal = () => {
  const show = (title, message) => {
    modalTitle.value = title;
    modalMessage.value = message;
    isVisible.value = true;
  };

  const close = () => {
    isVisible.value = false;
  };

  return {
    isVisible,
    modalTitle,
    modalMessage,
    show,
    close,
  };
};
