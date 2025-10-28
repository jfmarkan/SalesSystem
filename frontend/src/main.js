// usa Lara, sin primeflex. Tu theme y main siguen primero.
import './resources/scss/theme.scss'
import './resources/scss/main.scss'
import './resources/scss/fonts.scss'

import '@fortawesome/fontawesome-free/css/all.min.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'

import App from './App.vue'
import router from './router'
import setupPrimeVue from './plugins/primevue'
import { initTheme } from './composables/useTheme'

const app = createApp(App)
const pinia = createPinia()
pinia.use(createPersistedState)


setupPrimeVue(app)

// asegura tema antes de montar (login y navbar lo heredan)
initTheme()

app.use(pinia)
app.use(router)
app.mount('#app')
