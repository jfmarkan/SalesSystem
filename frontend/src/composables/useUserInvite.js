import api from '@/plugins/axios'
import { useToast } from 'primevue/usetoast'

export function useUserInvite() {
  const toast = useToast()

  const invite = async (email, role = 'user') => {
    try {
      const response = await api.post('/invite-user', { email, role })
      toast.add({ severity: 'success', summary: 'Invitación enviada', detail: response.data.message, life: 3000 })
    } catch (error) {
      toast.add({ severity: 'error', summary: 'Error', detail: error.response?.data?.message || 'Error al invitar', life: 3000 })
    }
  }

  return { invite }
}