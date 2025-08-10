<template>
  <AuthLayout>
    <form @submit.prevent="register" class="register-form">
      <InputText v-model="first_name" placeholder="Nombre" class="glass-field" />
      <InputText v-model="last_name" placeholder="Apellido" class="glass-field" />
      <SelectButton
        v-model="gender"
        :options="genderOptions"
        optionLabel="label"
        optionValue="value"
        class="glass-gender-select"
      />
      <InputText v-model="email" placeholder="Email" class="glass-field" />
      <Password
        v-model="password"
        placeholder="Contraseña"
        toggleMask
        :feedback="true"
        :pt="{
          info: { class: 'ml-3' },
          meter: { class: 'ml-3' },
        }"
        class="glass-field"
        inputClass="password-inner"
      />
      <div>
        <Password
          v-model="password_confirmation"
          placeholder="Confirmar Contraseña"
          toggleMask
          :feedback="false"
          class="glass-field"
          inputClass="password-inner"
          :class="{ 'error-field': password_confirmation && password !== password_confirmation }"
        />
        <div class="error-message-space">
          <span
            v-if="password_confirmation && password !== password_confirmation"
            class="error-message"
          >
            Las contraseñas no coinciden
          </span>
        </div>
      </div>
      <div class="form-button-wrapper">
        <Button
          label="Registrarse"
          icon="pi pi-user-plus"
          type="submit"
          class="custom-black-button"
        />
      </div>
    </form>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/plugins/axios'
import AuthLayout from '@/components/layout/AuthLayout.vue'
import { useModal } from '@/composables/useModal'

const router = useRouter()
const modal = useModal()

const first_name = ref('')
const last_name = ref('')
const gender = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')

const genderOptions = [
  { label: 'Hombre', value: 'M' },
  { label: 'Mujer', value: 'F' },
]

const register = async () => {
    try {
        await api.get('/sanctum/csrf-cookie');
        await api.post('/register', {
            first_name: first_name.value,
            last_name: last_name.value,
            gender: gender.value,
            email: email.value,
            password: password.value,
            password_confirmation: password_confirmation.value,
        })
        modal.show('Registro exitoso', 'Te registraste correctamente. Verificá tu correo.')
        router.push({ path: '/verify-otp', query: { email: email.value } })
    } catch (err) {
        modal.show('Error', 'Error al registrarse. Verificá los datos.')
    }
}
</script>

<style scoped>
.register-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  width: 100%;
  max-width: 400px;
  margin-top: 1rem;
}

.form-button-wrapper {
  margin-top: 1.5rem;
  display: flex;
  justify-content: flex-end;
}

.glass-field {
  width: 100%;
  height: 48px;
  font-size: 15px;
  background-color: rgba(255, 255, 255, 0.5) !important;
  border: 1px solid #000 !important;
  border-radius: 8px;
  backdrop-filter: blur(6px);
  padding: 0 1rem;
  box-sizing: border-box;
  color: #000;
}

.glass-field::placeholder {
  color: rgba(0, 0, 0, 0.8);
}

.error-field {
  border: 1px solid #dc3545 !important;
}

.error-message-space {
  height: 18px;
  display: flex;
  align-items: center;
  padding: 0;
  margin: 0;
  font-size: 13px;
  text-align: left;
  overflow: hidden;
}

.error-message {
  color: #dc3545;
  font-size: 13px;
  line-height: 1;
}

.select-group {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.glass-gender-select {
  display: flex;
  justify-content: center;
  background-color: transparent;
  border: none;
}

.glass-select .p-button {
  background-color: transparent !important;
  border: none !important;
  color: #000;
  border-radius: 0;
  height: 100%;
  font-size: 15px;
  flex: 1;
  justify-content: center;
}

.glass-select .p-button.p-highlight {
  background-color: #000 !important;
  color: #fff !important;
}

.custom-black-button {
  background-color: #000;
  border: none;
  color: #fff;
  height: 40px;
  font-size: 14px;
  border-radius: 6px;
  padding: 0 2rem;
}

.custom-black-button:hover {
  background-color: #222;
}

::v-deep(.password-inner) {
  background-color: transparent !important;
  border: none !important;
  color: #000;
  padding-left: 0;
  padding-right: 2.5rem !important;
  font-size: 15px;
  height: 48px;
  box-sizing: border-box;
}

::v-deep(.password-inner::placeholder) {
  color: rgba(0, 0, 0, 0.8);
}
</style>
