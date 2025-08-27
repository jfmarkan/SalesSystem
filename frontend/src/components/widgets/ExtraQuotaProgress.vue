<template>
  <div class="eqp-wrap">
    <div v-if="!items?.length" class="empty">Keine Daten</div>

    <div v-for="row in items" :key="row.pcCode" class="bar-row">
      <div class="label">
        <span class="pc">{{ row.pcName }}</span>
        <span class="code">({{ row.pcCode }})</span>
      </div>

      <div class="bar">
        <div
          class="fill"
          :style="{ width: fillPct(row) + '%' }"
          :title="tooltip(row)"
        ></div>
      </div>

      <div class="vals">
        <span class="alloc">Allokiert: {{ fmt(row.allocated) }}</span>
        <span class="sep">/</span>
        <span class="assign">Zuweisung: {{ fmt(row.assigned) }}</span>
        <span class="pct" v-if="row.assigned > 0">({{ Math.round(100*row.allocated/row.assigned) }}%)</span>
      </div>
    </div>
  </div>
</template>

<script setup>
// Code in English; UI in German.
const props = defineProps({
  // [{ pcCode, pcName, assigned:Number, allocated:Number }]
  items: { type: Array, default: () => [] },
  unit: { type: String, default: 'EUR' }
})

function fillPct(r){
  if (!r || !r.assigned) return 0
  const pct = (r.allocated / r.assigned) * 100
  return Math.max(0, Math.min(100, pct))
}
function tooltip(r){
  return `${r.pcName} • ${fmt(r.allocated)} / ${fmt(r.assigned)}`
}
function fmt(v){
  const n = Number(v || 0)
  return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(n)
}
</script>

<style scoped>
.eqp-wrap{
  display:flex; flex-direction:column; gap:10px; height:100%; overflow:auto;
}
.empty{
  padding:12px; border:1px dashed rgba(0,0,0,.2); border-radius:8px; text-align:center; color:#4b5563;
  background: rgba(255,255,255,.5);
}
.bar-row{
  display:grid; grid-template-columns: 1fr; gap:6px;
  background: rgba(255,255,255,.45);
  border: 1px solid rgba(0,0,0,.08);
  border-radius: 10px;
  padding: 8px 10px;
}
.label{
  display:flex; gap:6px; align-items:baseline; color:#111827; font-weight:700;
}
.label .code{ color:#6b7280; font-weight:600; }
.bar{
  height: 14px;
  width: 100%;
  border-radius: 8px;
  background: linear-gradient(180deg, rgba(17,24,39,.06), rgba(17,24,39,.12)); /* grey "total" */
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.1);
  overflow: hidden;
}
.fill{
  height: 100%;
  background: linear-gradient(90deg, rgba(22,163,74,.9), rgba(22,163,74,.75)); /* green "allocated" */
  box-shadow: inset 0 0 0 1px rgba(0,0,0,.08);
}
.vals{
  display:flex; align-items:center; gap:6px; color:#111827; font-size:.9rem;
}
.sep{ opacity:.6; }
.pct{ font-weight:700; }
</style>