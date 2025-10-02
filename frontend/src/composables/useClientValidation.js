import { ref, watch } from 'vue'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

export function useClientValidation(clientGroupInput, profitCenterCode, dialogVisible) {
  const validating = ref(false)
  const validationError = ref('')
  const clientExists = ref(false)
  const clientData = ref(null)
  const pcConflict = ref(false)

  // helper: número válido?
  const isValidClientNumber = (val) => {
    const s = String(val || '').trim()
    if (!/^\d{5}$/.test(s)) return false
    const n = Number(s)
    return n >= 10000 && n <= 19999
  }

  // buscar cliente por número
  const lookupClientByNumber = async (num) => {
    try {
      await ensureCsrf()
      const { data } = await api.get(`/api/extra-quota/clients/by-number/${num}`)
      if (!data) return null
      return {
        id: data.id,
        name: data?.name ?? data?.client_name ?? '',
        client_number: data?.client_number ?? data?.client_group_number ?? num,
        classification_id: data?.classification_id ?? null,
      }
    } catch {
      return null
    }
  }

  // verificar si cliente ya compra en ese profit center
  const checkClientPcExists = async (cgNum, pc) => {
    try {
      await ensureCsrf()
      const { data } = await api.get('/api/extra-quota/clients/exists-in-pc', {
        params: { client_group_number: cgNum, profit_center_code: Number(pc) },
      })
      return !!data?.exists
    } catch {
      return false
    }
  }

  let timer = null
  watch([clientGroupInput, profitCenterCode, dialogVisible], async ([num, pc, vis]) => {
    if (!vis) return
    if (timer) clearTimeout(timer)
    timer = setTimeout(async () => {
      validationError.value = ''
      clientExists.value = false
      clientData.value = null
      pcConflict.value = false

      const s = String(num || '').trim()
      if (!s) return

      if (!isValidClientNumber(s)) {
        validationError.value = 'Die Nummer muss zwischen 10000 und 19999 liegen.'
        return
      }

      validating.value = true
      try {
        const cgNum = Number(s)
        const client = await lookupClientByNumber(cgNum)
        if (client) {
          // existe → bloquear, setear datos
          clientExists.value = true
          clientData.value = client

          if (pc) {
            const exists = await checkClientPcExists(cgNum, Number(pc))
            if (exists) {
              pcConflict.value = true
              validationError.value = 'Kunde + Profitcenter existieren bereits. Das ist Forecast.'
            }
          }
        } else {
          // no existe → cliente nuevo
          clientExists.value = false
        }
      } finally {
        validating.value = false
      }
    }, 300)
  })

  const isValidToSave = ref(false)
  watch([clientGroupInput, validationError, clientExists, clientData, pcConflict], () => {
    if (!clientGroupInput.value || validationError.value || pcConflict.value) {
      isValidToSave.value = false
      return
    }
    if (clientExists.value && clientData.value?.classification_id == null) {
      isValidToSave.value = false
      return
    }
    isValidToSave.value = true
  })

  return {
    validating,
    validationError,
    clientExists,
    clientData,
    pcConflict,
    isValidToSave,
  }
}