import { useToast } from 'primevue/usetoast';

export const useNotify = () => {
  const toast = useToast();

  const success = (message, summary = 'Éxito') => {
    toast.add({
      severity: 'success',
      summary,
      detail: message,
      life: 3000,
    });
  };

  const error = (message, summary = 'Error') => {
    toast.add({
      severity: 'error',
      summary,
      detail: message,
      life: 4000,
    });
  };

  const info = (message, summary = 'Información') => {
    toast.add({
      severity: 'info',
      summary,
      detail: message,
      life: 3000,
    });
  };

  const warn = (message, summary = 'Atención') => {
    toast.add({
      severity: 'warn',
      summary,
      detail: message,
      life: 3000,
    });
  };

  return {
    success,
    error,
    info,
    warn,
  };
};
