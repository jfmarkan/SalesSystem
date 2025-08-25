// Axios client for Laravel Sanctum (stateful, cookies-based)
import axios from 'axios';
import router from '@/router';

// Simple cookie reader
function readCookie(name) {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
  return match ? match[1] : null;
}

export const api = axios.create({
  baseURL: 'http://localhost:8000',
  withCredentials: true, // send cookies
  headers: { 'X-Requested-With': 'XMLHttpRequest' },

  // Axios XSRF config
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',

  // Axios v1.6+: send XSRF header for cross-site requests too
  withXSRFToken: true,
});

// Fallback: force X-XSRF-TOKEN header from cookie on every request
api.interceptors.request.use((config) => {
  // Only set if not already set by axios
  if (!config.headers['X-XSRF-TOKEN']) {
    const token = readCookie('XSRF-TOKEN');
    if (token) {
      // Cookie is URL-encoded; decode before sending
      config.headers['X-XSRF-TOKEN'] = decodeURIComponent(token);
    }
  }
  return config;
});

api.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 401) {
      // Limpia cookies si hace falta (opcional, dependiendo del logout en el backend)
      
      // Redirige al login con un mensaje opcional
      router.push({ name: 'login', query: { expired: '1' } })
    }

    return Promise.reject(error);
  }
);

export default api
