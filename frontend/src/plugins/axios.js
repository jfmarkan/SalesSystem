// src/plugins/axios.js
import axios from 'axios'

const api = axios.create({
  baseURL: 'http://localhost:8000',
  withCredentials: true,                       // envía/recibe cookies
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
  xsrfCookieName: 'XSRF-TOKEN',                // Laravel default
  xsrfHeaderName: 'X-XSRF-TOKEN',              // Laravel default
})

export default api
