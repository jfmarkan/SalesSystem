<script setup>
    const props = defineProps({
    client: { type: String, default: '' },
    pc: { type: String, default: '' }
    })
</script>

<template>
    <div class="w-full">
        <div class="flex">
            <span class="text-700">{{ client || '—' }}</span>
            <span class="mx-2">|</span>
            <span class="text-500">{{ pc || '—' }}</span>
        </div>
    </div>
</template>
