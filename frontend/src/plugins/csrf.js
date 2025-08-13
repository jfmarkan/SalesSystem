import api from '@/plugins/axios'

let booted = false

export async function ensureCsrf () {
  if (booted) return
  await api.get('/sanctum/csrf-cookie')
  booted = true
}
