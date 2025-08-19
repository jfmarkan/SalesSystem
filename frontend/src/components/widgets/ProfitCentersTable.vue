<template>
  <div class="pctable">
    <div class="toolbar">
      <strong>Einheiten:</strong>
      <div class="btn-group">
        <button :class="{active: unit==='VK-EH'}" @click="$emit('unitChange','VK-EH')">VK-EH</button>
        <button :class="{active: unit==='M3'}"    @click="$emit('unitChange','M3')">m³</button>
        <button :class="{active: unit==='EUR'}"   @click="$emit('unitChange','EUR')">€</button>
      </div>
    </div>
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>PC</th>
            <th class="num">Verkäufe</th>
            <th class="num">Prognose</th>
            <th class="num">Budget</th>
            <th class="num">% Erfüllung</th>
            <th class="num">% Prognosefehler</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.pcId">
            <td><div class="pc-name">{{ r.pcName }}</div></td>
            <td class="num">{{ fmt(r.sales) }}</td>
            <td class="num">{{ fmt(r.forecast) }}</td>
            <td class="num">{{ fmt(r.budget) }}</td>
            <td class="num">{{ fmtPct(r.budget === 0 ? 0 : (r.sales / r.budget * 100)) }}</td>
            <td class="num">
              {{ fmtPct(r.sales === 0 ? 0 : (Math.abs(r.forecast - r.sales) / r.sales * 100)) }}
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="total">
            <td>Summe</td>
            <td class="num">{{ fmt(totals.sales) }}</td>
            <td class="num">{{ fmt(totals.forecast) }}</td>
            <td class="num">{{ fmt(totals.budget) }}</td>
            <td class="num" colspan="2"><small>Einheit: {{ unit }}</small></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup>
// Code/vars/comments in English
const props = defineProps({
  rows: { type: Array, required: true },
  totals: { type: Object, required: true },
  unit: { type: String, default: 'VK-EH' }
})
defineEmits(['unitChange'])

function fmt(n){
  const abs = Math.abs(n)
  const sign = n < 0 ? '-' : ''
  if (abs >= 1_000_000) return sign + (abs/1_000_000).toFixed(2) + 'M'
  if (abs >= 1_000) return sign + (abs/1_000).toFixed(1) + 'k'
  return sign + abs.toLocaleString(undefined, { maximumFractionDigits: 2 })
}
function fmtPct(p){ return `${p.toFixed(1)}%` }
</script>

<style scoped>
.pctable{ height: 100%; display: flex; flex-direction: column; }
.toolbar{ display:flex; gap:.5rem; align-items:center; margin-bottom:.5rem; justify-content:flex-end; }
.btn-group{
  background: rgba(255,255,255,.25); border:1px solid rgba(0,0,0,.1); border-radius:8px; overflow:hidden;
}
.btn-group button{ padding:.35rem .6rem; font-size:.8rem; border:0; background:transparent; cursor:pointer; }
.btn-group button.active{ background: rgba(255,255,255,.5); font-weight:700; }
.table-wrap{ overflow:auto; flex:1; }
.tbl{ width:100%; border-collapse:collapse; }
.tbl th, .tbl td{ padding:.5rem .6rem; border-bottom:1px solid rgba(0,0,0,.05); }
.tbl thead th{ position: sticky; top:0; background: rgba(255,255,255,.6); backdrop-filter: blur(4px); }
.tbl .num{ text-align:right; }
.tbl tfoot .total td{ font-weight: 700; border-top: 2px solid rgba(0,0,0,.15); }
.pc-name{ font-weight:600; }
</style>