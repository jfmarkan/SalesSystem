<template>
  <div class="pctable">
    <div class="table-wrap">
      <table class="tbl">
        <thead>
          <tr>
            <th>PC</th>
            <th class="num">Ist</th>
            <th class="num">Prognose</th>
            <th class="num">Budget</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.pcId">
            <td class="pc-cell">
              <div class="pc-code">{{ r.pcIdNumeric ?? r.pcId }}</div>
              <div class="pc-name">{{ r.pcName }}</div>
            </td>
            <td class="num">{{ fmtAbs(r.sales) }}</td>
            <td class="num">{{ fmtAbs(r.forecast) }}</td>
            <td class="num">{{ fmtAbs(r.budget) }}</td>
          </tr>
        </tbody>

        <tfoot v-if="showTotals">
          <tr class="total">
            <td>Summe</td>
            <td class="num">{{ fmtAbs(totals.sales) }}</td>
            <td class="num">{{ fmtAbs(totals.forecast) }}</td>
            <td class="num">{{ fmtAbs(totals.budget) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup>
// Code/vars/comments in English
const props = defineProps({
  rows:      { type: Array,  required: true }, // [{ pcId, pcIdNumeric, pcName, sales, forecast, budget }]
  totals:    { type: Object, required: true }, // { sales, forecast, budget }
  unit:      { type: String, default: 'm³' },
  showTotals:{ type: Boolean, default: true }  // only true for EUR/M3, false for VK-EH
})

// Absolute numbers, dot as thousand separator (de-DE)
const nf0 = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 })
function fmtAbs(n){ return nf0.format(Math.round(n ?? 0)) }
</script>

<style scoped>
.pctable{ height: 100%; display: flex; flex-direction: column; }
.table-wrap{ overflow:auto; flex:1; }
.tbl{ width:100%; border-collapse:collapse; font-size: 0.86rem; }
.tbl th, .tbl td{ padding:.35rem .5rem; border-bottom:1px solid rgba(0,0,0,.05); vertical-align: middle; }
.tbl thead th{ position: sticky; top:0; background: rgba(255,255,255,.7); backdrop-filter: blur(4px); font-weight: 700; }
.tbl .num{ text-align:right; white-space: nowrap; }

.pc-cell{ line-height: 1.05; }
.pc-code{ font-weight: 800; font-size: 0.95rem; }
.pc-name{ font-size: 0.70rem; color:#374151; opacity: .9; }

.tbl tfoot .total td{ font-weight: 700; border-top: 2px solid rgba(0,0,0,.15); }
</style>