import axios from 'axios'

const api = axios.create({
	baseURL: 'http://localhost:8000',
	withCredentials: true,
})

api.interceptors.request.use(config => {
  const token = getCookie('XSRF-TOKEN');
  if (token) {
    config.headers['X-XSRF-TOKEN'] = decodeURIComponent(token); // 👈 ¡Clave!
  }
  return config;
})

function getCookie(name) {
  const value = `; ${document.cookie}`
  const parts = value.split(`; ${name}=`)
  if (parts.length === 2) return parts.pop().split(';').shift()
}

export default api