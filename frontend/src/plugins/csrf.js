import api from './axios'

let csrfBootstrapped = false

export async function ensureCsrf() {
  if (csrfBootstrapped) return
  await api.get('/sanctum/csrf-cookie') // sets XSRF-TOKEN cookie
  csrfBootstrapped = true
}