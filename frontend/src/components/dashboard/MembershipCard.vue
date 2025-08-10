<template>
    <div class="card-container" @click="flipped = !flipped">
        <div class="card" :class="{ flipped }">
            <!-- Frente -->
            <div class="card-face front">
                <img src="@/assets/img/logos/stb.png" alt="Golf Logo" class="logo" />
                <div class="membership-info">
                    <span class="membership-legend">GOLD MEMBER</span>
                    <span class="member-id">{{ paddedId }}</span>
                </div>
            </div>

            <!-- Dorso -->
            <div class="card-face back">
    <!-- Lado izquierdo -->
    <div class="back-left">
        <img :src="userPhoto" alt="Foto del usuario" class="user-photo" />
        <span class="user-name">{{ user.first_name }}</span>
        <span class="user-lastname">{{ user.last_name }}</span>
        <span class="member-since">DESDE 03/2023</span>
    </div>

    <!-- Lado derecho -->
    <div class="back-right">
        <div class="qr-wrapper">

            <img :src="qrCode" alt="QR code" class="qr-code" />
        </div>
    </div>
</div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Button from 'primevue/button';
import { useAuthStore } from '@/stores/auth';

const flipped = ref(false);
const user = useAuthStore().user;

const paddedId = computed(() => {
    if (!user?.id) return '000000';
    return user.id.toString().padStart(6, '0');
});

const defaultMale = new URL('@/assets/img/placeholders/user-male.svg', import.meta.url).href;
const defaultFemale = new URL('@/assets/img/placeholders/user-female.svg', import.meta.url).href;

const userPhoto = computed(() => {
    if (user?.photo) return user.photo;
    if (user?.gender === 'F') return defaultFemale;
    return defaultMale;
});

const qrCode = computed(() => {
    const data = `GOLFAPP-ID:${paddedId.value}`;
    return `https://api.qrserver.com/v1/create-qr-code/?size=130x130&data=${encodeURIComponent(data)}`;
});
</script>

<style scoped>
.card-container {
    perspective: 1000px;
    width: 100%;
    max-width: 280px;
    margin: 0 auto;
}

.card {
    width: 100%;
    height: 160px;
    position: relative;
    transform-style: preserve-3d;
    transition: transform 0.6s ease;
    cursor: pointer;
}

.card.flipped {
    transform: rotateY(180deg);
}

.card-face {
    position: absolute;
    width: 100%;
    height: 100%;
    backface-visibility: hidden;
    border-radius: 12px;
    padding: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.front {
    background-color: #231F20;
    color: #DDE1E2;
}

.membership-info {
    display: flex;
    width: 100%;
    justify-content: space-between;
}

.membership-legend,
.member-id {
    font-size: 0.75rem;
}

.logo {
    width: 175px;
}

.back {
    background-color: #231F20;
    color: #fff;
    transform: rotateY(180deg);
    display: flex;
    flex-direction: row;
    gap: 1rem;
    padding: 1rem;
}

.back-left, .back-right {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.user-photo {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: .5rem;
}

.user-name {
    font-size: 0.75rem;
    font-weight: 300;
}

.user-lastname {
    font-size: 0.9rem;
    font-weight: 400;
    margin-bottom: 0.7rem;
}

.member-since {
    font-size: 0.5rem;
    font-weight: 300;
    opacity: 0.8;
}

.qr-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-code {
    width: 100px;
    height: 100px;
    color: #231F20;
    filter: brightness(100%) invert(1); /* Blanco puro */
}

.wallet-icon {
    position: absolute;
    width: 24px;
    top: 8px;
    z-index: 2;
    opacity: 0.85;
}

</style>
