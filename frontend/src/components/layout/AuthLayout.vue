<template>
    <div class="auth-layout">
      <!-- Fondo + Blur opcional -->
      <div class="auth-blur-overlay" :class="{ blurred: blurBackground }"></div>
  
      <!-- Contenedor centrado -->
      <div class="auth-card-wrapper">
        <div class="auth-card">
          <img src="@/assets/img/logos/stb.png" alt="Logo" class="logo" />
          <slot />
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  defineProps({
    blurBackground: {
      type: Boolean,
      default: false
    }
  });
  </script>
  
  <style scoped>
  .auth-layout {
    position: relative;
    height: 100vh;
    width: 100vw;
    background-image: url('@/assets/img/backgrounds/stb-factory.jpg');
    background-size: cover;
    background-position: center;
    overflow: hidden;
  }
  
  /* ✅ Fondo con blur dinámico */
  .auth-blur-overlay {
    position: absolute;
    inset: 0;
    z-index: 1;
  }
  
  .auth-blur-overlay.blurred {
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    background-color: rgba(0, 0, 0, 0.4);
  }
  
  /* ✅ Card encima del blur */
  .auth-card-wrapper {
    position: relative;
    z-index: 2;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .auth-card {
    background-color: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.40);
    border-radius: 16px;
    padding: 2rem;
    width: 100%;
    max-width: 400px;
    text-align: center;
  }
  
  .logo {
    width: 150px;
    margin: 0 auto 1.5rem;
  }
  </style>
  