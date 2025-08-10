<!-- src/components/widgets/CalendarCard.vue -->
<template>
  <div class="calendar-card">
    <vue-cal
      style="height: 100%;"
      :events="events"
      default-view="month"
      hide-view-selector
      :time="false"
    />
  </div>
</template>

<script setup>
import VueCal from 'vue-cal';
import 'vue-cal/dist/vuecal.css';

const events = [
  { start: '2025-07-18', end: '2025-07-18', title: '⛳ Torneo GolfFest', content: 'Cancha 2 - 9:00 AM' },
  { start: '2025-07-21', end: '2025-07-21', title: '📞 Llamada con Juan', content: 'Zoom - 11:00 AM' },
  { start: '2025-07-23', end: '2025-07-23', title: '🧾 Publicación Magazine', content: 'Edición mensual' }
];
</script>

<style scoped>
.calendar-card {
  height: 100%;
  width: 100%;
  border-radius: 12px;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.1);
  backdrop-filter: blur(10px);
  padding: 0.5rem;
}
</style>
