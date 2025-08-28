<template>
  <div class="table-wrap">
    <DataTable :value="rows" stripedRows responsiveLayout="scroll" :size="'small'">
      <Column field="name" header="Mitarbeiter" />
      <Column header="Ist" :body="(r)=>fmt(mapVal(r.sales))" />
      <Column header="Forecast" :body="(r)=>fmt(mapVal(r.forecast))" />
      <Column header="Budget" :body="(r)=>fmt(mapVal(r.budget))" />
      <Column header="Erfüllung Budget" :body="(r)=>tagPct(r.achievement?.vs_budget_pct)" />
      <Column header="Erfüllung Forecast" :body="(r)=>tagPct(r.achievement?.vs_forecast_pct)" />
    </DataTable>
  </div>
</template>

<script setup>
// Team breakdown table in glass card aesthetic
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import { h } from 'vue'

const props = defineProps({
  rows: { type:Array, default:()=>[] },
  unit: { type:String, default:'EUR' },
})
const fmtNumber = (n)=> new Intl.NumberFormat('de-DE').format(Number(n||0))
const fmtEuro   = (n)=> new Intl.NumberFormat('de-DE',{style:'currency',currency:'EUR'}).format(Number(n||0))
function fmt(n){ return props.unit==='EUR' ? fmtEuro(n) : props.unit==='M3' ? `${fmtNumber(n)} m³` : fmtNumber(n) }
function mapVal(obj){ return props.unit==='EUR' ? (obj?.euros||0) : props.unit==='M3' ? (obj?.m3||0) : (obj?.units||0) }
const pct = (v)=> `${(Number(v||0)).toFixed(1)}%`
const severity = (p)=> p>=100 ? 'success' : (p>=85 ? 'warning' : 'danger')
function tagPct(val){ return h(Tag, { value:pct(val), severity:severity(val) }) }
</script>

<style scoped>
.table-wrap{ height:100%; width:100%; }
</style>
