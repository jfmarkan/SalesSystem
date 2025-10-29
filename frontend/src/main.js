// tu orden de estilos primero
import './resources/scss/theme.scss'
import './resources/scss/main.scss'
import './resources/scss/fonts.scss'

// iconos
import '@fortawesome/fontawesome-free/css/all.min.css'

// sin Bootstrap
// import 'bootstrap/dist/css/bootstrap.min.css' // eliminar si existía

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'

import App from './App.vue'
import router from './router'

import setupPrimeVue from './plugins/primevue.js'
import { initTheme } from './composables/useTheme'

const app = createApp(App)

const pinia = createPinia()
pinia.use(createPersistedState)

setupPrimeVue(app)      // registra PrimeVue + tema
initTheme()             // asegura clase de modo antes de montar

app.use(pinia)
app.use(router)
app.mount('#app')