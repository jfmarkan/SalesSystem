<template>
  <section class="hero" :style="{'--hero-url': `url(${heroImage})`}">
    <!-- Text column -->
    <div class="hero-left">
      <div class="hero-content">
        <h1 class="hero-title">
          <span>Intelligenteres Forecasting.</span>
          <span class="accent"> Totale Integration.</span>
        </h1>
        <p class="hero-sub">
          Wir verwandeln isolierte Zahlen in 
          unternehmensweite Erkenntnisse und Maßnahmen.
        </p>
        <div class="hero-actions">
          <Button label="Anmelden" class="btn-hero" @click="goToLogin"/>
          <Button label="Registrieren" outlined class="btn-hero-outline" />
        </div>
      </div>
    </div>

    <!-- Image column (only lg+) -->
    <div class="hero-right">
      <img :src="heroImage" alt="hero" class="hero-img" />
    </div>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
// Local asset example:
import heroImg from '@/assets/img/backgrounds/stb-factory.jpg'
const heroImage = heroImg
// const heroImage = 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=1600&auto=format&fit=crop'

import { useRouter } from 'vue-router'

const router = useRouter()

const goToLogin = () => {
  router.push('/login')
}

const goToRegister = () => {
  router.push('/register')
}
</script>

<<style scoped>
/* Base: mobile (<1024px) -> background image + dark overlay */
.hero {
  position: relative;
  height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 2rem 1.25rem;
  overflow: hidden;
  background: #fff;
}
.hero::before {
  content: "";
  position: absolute;
  inset: 0;
  background-image: var(--hero-url);
  background-size: cover;
  background-position: center;
  z-index: 0;
}
.hero::after {
  content: "";
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 0;
}
.hero-left, .hero-right { position: relative; z-index: 1; }
.hero-right { display: none; }
.hero-content { max-width: 780px; margin: 0 auto; text-align: center; }
.hero-title { font-size: clamp(1.9rem, 4vw, 3rem); line-height: 1.15; font-weight: 800; color: #ffffff; margin-bottom: 1rem; }
.accent { display: block; color: #22c55e; }
.hero-sub { color: #e5e7eb; font-size: 1.05rem; margin-bottom: 1.5rem; }
.hero-actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }

/* LG (≥1024px): two-column layout, vertical cut, no overlay */
@media (min-width: 1024px) {
  .hero {
    flex-direction: row;
    padding: 0;
  }
  .hero::before, .hero::after { display: none; }
  .hero-left {
    flex: 1 1 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 2.5rem;
  }
  .hero-content { text-align: left; margin: 0; }
  .hero-title { color: #0f172a; }
  .hero-sub { color: #475569; }
  .hero-right {
    display: block;
    position: relative;
    flex: 1 1 50%;
    min-height: 420px;
  }
  .hero-img {
    position: absolute; inset: 0;
    width: 100%; height: 100%; object-fit: cover;
    clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
  }
}

/* XL (≥1280px): diagonal cut */
@media (min-width: 1280px) {
  .hero-img {
    clip-path: polygon(12% 0, 100% 0, 100% 100%, 0 100%);
  }
}

/* Custom buttons */
.btn-hero { background-color: #22c55e; border: none; color: #fff; }
.btn-hero:hover { background-color: #16a34a; }
.btn-hero-outline { border: 2px solid #22c55e; color: #22c55e; }
.btn-hero-outline:hover { background-color: #22c55e; color: #fff; }
</style>
