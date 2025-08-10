<template>
  <AuthLayout>
    <div class="otp-form">
      <div class="title">Verificá tu cuenta</div>
      <p class="subtitle">Ingresá el código que enviamos a tu correo</p>
      <InputOtp v-model="otp" :length="6" class="otp-input">
        <template #default="{ attrs, events }">
          <input type="text" v-bind="attrs" v-on="events" class="otp-box" />
        </template>
      </InputOtp>
      <div class="otp-footer">
        <a @click="resendOtp" class="resend-code">Reenviar código</a>
        <Button label="Verificar" class="custom-black-button" @click="submitOtp" />
      </div>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { useModal } from '@/composables/useModal'

const route = useRoute()
const router = useRouter()
const modal = useModal()

const otp = ref('')
const email = ref('')

onMounted(() => {
  email.value = route.query.email || ''
})

const submitOtp = async () => {
  try {
    await api.get('/sanctum/csrf-cookie');
    await api.post('/api/verify-otp', {
      email: email.value,
      otp: otp.value,
    })

    modal.show('Verificado', 'Cuenta verificada con éxito')
    router.push('/dashboard')
  } catch (err) {
    modal.show('Código incorrecto', 'El OTP ingresado es inválido o expiró.')
  }
}

const resendOtp = async () => {
  try {
    await api.get('/sanctum/csrf-cookie');
    await api.post('/api/resend-otp', {
      email: email.value,
    })

    modal.show('Código reenviado', 'Te enviamos un nuevo código a tu correo.')
  } catch (err) {
    if (err.response?.status === 400) {
      modal.show('Ya verificada', 'Esta cuenta ya fue verificada.')
    } else if (err.response?.status === 404) {
      modal.show('Usuario no encontrado', 'No se pudo encontrar un usuario con ese correo.')
    } else {
      modal.show('Error', 'Ocurrió un error al reenviar el código.')
    }
  }
}
</script>

<style scoped>
.otp-form {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  max-width: 400px;
  margin-top: 2rem;
}

.title {
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 0.5rem;
  text-align: center;
  color: #000;
}

.subtitle {
  color: #000;
  text-align: center;
  font-size: 14px;
  margin-bottom: 1.5rem;
}

.otp-input {
  display: flex;
  justify-content: center;
  gap: 0.5rem;
  margin-bottom: 2rem;
}

.otp-box {
  width: 48px;
  height: 48px;
  font-size: 24px;
  text-align: center;
  background-color: rgba(255, 255, 255, 0.5);
  border: 1px solid #000;
  border-radius: 8px;
  backdrop-filter: blur(6px);
  color: #000;
  outline: none;
  transition: all 0.3s;
}

.otp-box:focus {
  border-color: #000;
  box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.2);
}

.otp-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}

.resend-code {
  font-size: 13px;
  color: #000;
  text-decoration: underline;
  cursor: pointer;
}

.custom-black-button {
  background-color: #000;
  border: none;
  color: #fff;
  height: 40px;
  font-size: 14px;
  border-radius: 6px;
  padding: 0 1.5rem;
}

.custom-black-button:hover {
  background-color: #222;
}
</style>
