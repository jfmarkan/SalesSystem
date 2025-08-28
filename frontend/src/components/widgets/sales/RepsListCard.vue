<template>
  <div class="wrap">
    <span class="p-input-icon-left w-full mb-2">
      <i class="pi pi-search" />
      <input type="text" v-model="query" class="inp" placeholder="Suche" />
    </span>
    <ul class="list">
      <li :class="['row', selected==='ALL' && 'active']" @click="$emit('select','ALL')">Team gesamt</li>
      <li v-for="r in filtered" :key="r.value" :class="['row', selected===r.value && 'active']" @click="$emit('select', r.value)">
        <i class="pi pi-user" /> <span>{{ r.label }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
// Simple list card with search; emits 'select'
import { ref, computed } from 'vue'
const props = defineProps({
  options: { type:Array, default:()=>[] }, // [{label,value}]
  selected: { type:[String,Number], default:'ALL' },
})
defineEmits(['select'])
const query = ref('')
const filtered = computed(() => props.options.filter(o => o.label.toLowerCase().includes(query.value.toLowerCase())))
</script>

<style scoped>
.wrap{ display:flex; flex-direction:column; height:100%; }
.inp{ width:100%; border:1px solid rgba(0,0,0,.12); border-radius:8px; padding:.5rem .75rem; background:rgba(255,255,255,.6); }
.list{ list-style:none; padding:0; margin:0; overflow:auto; max-height:100%; }
.row{ display:flex; align-items:center; gap:8px; padding:.5rem .5rem; border-radius:8px; cursor:pointer; }
.row:hover{ background:rgba(255,255,255,.5); }
.active{ background:rgba(31,86,115,.12); font-weight:700; }
</style>
