<template>
    <div class="mini-card">
        <i :class="['pi', icon]" class="card-icon" />
        <div class="card-info">
            <span class="card-label">{{ label }}</span>
            <span class="card-value">{{ value }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    label: String,
    value: [String, Number],
    icon: String
});
</script>

<style scoped>
.mini-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    min-width: 200px;
}

.card-icon {
    font-size: 1.5rem;
    color: #333;
}

.card-info {
    display: flex;
    flex-direction: column;
}

.card-label {
    font-size: 0.75rem;
    color: #555;
    font-weight: 500;
}

.card-value {
    font-size: 1.25rem;
    font-weight: 600;
    color: #111;
}
</style>
