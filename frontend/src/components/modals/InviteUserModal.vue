<template>
    <div v-if="visible" class="modal-overlay">
      <div class="modal-content glass">
        <h2>Invitar Usuario</h2>
        <form @submit.prevent="sendInvite">
          <label>Email:</label>
          <input type="email" v-model="email" required />
  
          <label>Rol:</label>
          <select v-model="role" required>
            <option value="employee">Empleado</option>
            <option value="golf_trainer">Golf Trainer</option>
            <option value="fitness_trainer">Fitness Trainer</option>
            <option value="user">Usuario</option>
          </select>
  
          <div class="actions">
            <button type="submit">Enviar Invitación</button>
            <button type="button" @click="$emit('close')">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </template>
  
  <script setup>
  import { ref, watch } from 'vue'
  import api from '@/plugins/axios'
  
  const props = defineProps({
    visible: Boolean,
  })
  
  const emit = defineEmits(['close'])
  
  const email = ref('')
  const role = ref('employee')
  
  // Limpiar campos cuando el modal se abre
  watch(() => props.visible, (val) => {
    if (val) {
      email.value = ''
      role.value = 'employee'
    }
  })
  
  const sendInvite = async () => {
    try {
      await api.get('/sanctum/csrf-cookie')
      await api.post('/api/invitations', { email: email.value, role: role.value })
      alert('Invitación enviada exitosamente 📩')
      emit('close')
    } catch (err) {
      console.error(err)
      alert('Error al enviar la invitación.')
    }
  }
  </script>
  
  <style scoped>
  .modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
  }
  .modal-content {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 2rem;
    border-radius: 12px;
    width: 400px;
    box-shadow: 0 0 40px rgba(0, 0, 0, 0.2);
  }
  input, select {
    width: 100%;
    padding: 0.6rem;
    margin: 0.5rem 0;
    border-radius: 6px;
    border: 1px solid #ccc;
  }
  .actions {
    display: flex;
    justify-content: space-between;
    margin-top: 1rem;
  }
  button {
    padding: 0.5rem 1.2rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
  }
  </style>
  