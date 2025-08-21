<template>
  <div id="loader" v-show="visible">
    <div class="logo-container">
      <img id="logo" :src="logoSrc" alt="Logo" />
      <div class="shine"></div>
    </div>
  </div>
</template>

<script>
import { gsap } from "gsap";

export default {
  name: "LogoLoader",
  props: {
    logoSrc: {
      type: String,
      default: require('@/assets/img/logos/stb-simple.png')
    },
    visible: {
      type: Boolean,
      default: true
    }
  },
  mounted() {
    // Entrada
    gsap.from(".logo-container", {
      scale: 0,
      duration: 1.2,
      ease: "back.out(1.7)"
    });

    // Giro infinito
    gsap.to(".logo-container", {
      rotationY: 360,
      repeat: -1,
      duration: 5,
      ease: "none",
      transformOrigin: "50% 50%"
    });

    // Reflejo de luz
    gsap.to(".shine", {
      x: "100%",
      duration: 2,
      repeat: -1,
      ease: "power2.inOut",
      delay: 0.5
    });
  }
};
</script>

<style scoped>
#loader {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  perspective: 800px;
}

.logo-container {
  position: relative;
  width: 120px;
  height: 120px;
  transform-style: preserve-3d;
}

#logo {
  width: 100%;
  display: block;
  filter: drop-shadow(0px 10px 20px rgba(0, 0, 0, 0.25));
  backface-visibility: hidden;
}

.shine {
  position: absolute;
  top: 0;
  left: -50%;
  width: 200%;
  height: 100%;
  background: linear-gradient(
    120deg,
    rgba(255, 255, 255, 0) 30%,
    rgba(255, 255, 255, 0.5) 50%,
    rgba(255, 255, 255, 0) 70%
  );
  mix-blend-mode: screen;
  pointer-events: none;
}
</style>