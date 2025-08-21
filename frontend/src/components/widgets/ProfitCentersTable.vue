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
            <td>
              <div class="pc-code">{{ r.pcId }}</div>
              <div class="pc-name">{{ r.pcName }}</div>
            </td>
            <td class="num">{{ fmt(r.sales) }}</td>
            <td class="num">{{ fmt(r.forecast) }}</td>
            <td class="num">{{ fmt(r.budget) }}</td>
          </tr>
        </tbody>

        <!-- show totals only when unit != VKEH -->
        <tfoot v-if="unit !== 'VKEH'">
          <tr class="total">
            <td>Summe</td>
            <td class="num">{{ fmt(totals.sales) }}</td>
            <td class="num">{{ fmt(totals.forecast) }}</td>
            <td class="num">{{ fmt(totals.budget) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</template>

<script setup>
// Code in English
const props = defineProps({
  rows: { type: Array, required: true },   // [{pcId, pcName, sales, forecast, budget}]
  totals: { type: Object, required: true },// {sales, forecast, budget}
  unit: { type: String, default: 'VKEH' }
})

function fmt(n){
  return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 2 }).format(Number(n || 0))
}
</script>

<style scoped>
.pctable{ height: 100%; display: flex; flex-direction: column; }
.table-wrap{ overflow:auto; flex:1; }
.tbl{ width:100%; border-collapse:collapse; }
.tbl th, .tbl td{ padding:.45rem .55rem; border-bottom:1px solid rgba(0,0,0,.06); white-space:nowrap; }
.tbl thead th{ position: sticky; top:0; background: rgba(255,255,255,.6); backdrop-filter: blur(4px); }
.tbl .num{ text-align:right; }
.tbl tfoot .total td{ font-weight: 700; border-top: 2px solid rgba(0,0,0,.15); }
.pc-code{ font-weight:700; font-size:.95rem; line-height:1; }
.pc-name{ font-size:.75rem; opacity:.9; line-height:1.1; }
</style>