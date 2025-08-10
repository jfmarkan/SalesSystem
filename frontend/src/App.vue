<script setup>
import { RouterLink, RouterView } from 'vue-router'
import AppDialog from '@/components/ui/AppDialog.vue';
</script>

<template>
    <RouterView />
    <Toast />
    <AppDialog />
</template>