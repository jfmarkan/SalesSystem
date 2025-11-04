<script setup>
import { computed } from 'vue'

const props = defineProps({
	rows: { type: Array, default: () => [] }, // [{ pcId, pcName, sales, forecast, budget }]
	totals: { type: Object, default: () => ({ sales: 0, forecast: 0, budget: 0 }) },
	unit: { type: String, default: '' },
	sortLocale: { type: String, default: '' }     // opcional: ej. 'de', 'es'
})

function fmt(n) {
	const v = Number(n) || 0
	return new Intl.NumberFormat('de-DE', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2
	}).format(v)
}

const showTotals = computed(() => {
	const U = String(props.unit || '').toUpperCase()
	return U === 'M3' || U === 'EUR'
})

function normalizeName(s) {
	const t = (s ?? '').toString()
		.normalize('NFKC')
		.replace(/[\u00A0\u2000-\u200D\uFEFF]/g, ' ') // NBSP y ZWSP
		.replace(/\s+/g, ' ')
		.trim()
	return t
}

const collator = computed(() =>
	new Intl.Collator(props.sortLocale || undefined, { numeric: true, sensitivity: 'base' })
)

const sortedRows = computed(() => {
	const arr = [...(props.rows || [])]
	arr.sort((a, b) => {
		const an = normalizeName(a.pcName)
		const bn = normalizeName(b.pcName)
		const c = collator.value.compare(an, bn)
		if (c !== 0) return c
		// desempate estable
		return String(a.pcId ?? '').localeCompare(String(b.pcId ?? ''), props.sortLocale || undefined, { numeric: true, sensitivity: 'base' })
	})
	return arr
})
</script>

<template>
	<div class="pc-table">
		<div class="tbl-wrap">
			<table class="tbl">
				<colgroup>
					<col style="width:46%" />
					<col style="width:18%" />
					<col style="width:18%" />
					<col style="width:18%" />
				</colgroup>

				<thead>
					<tr>
						<th class="th-name">Profit-Center</th>
						<th>Ist</th>
						<th>Prognose</th>
						<th>Budget</th>
					</tr>
				</thead>

				<tbody>
					<tr v-for="r in sortedRows" :key="r.pcId || r.pcName">
						<td class="td-name">
							<div class="pc-name">{{ r.pcName }}</div>
						</td>
						<td class="num">{{ fmt(r.sales) }}</td>
						<td class="num">{{ fmt(r.forecast) }}</td>
						<td class="num">{{ fmt(r.budget) }}</td>
					</tr>
				</tbody>

				<tfoot v-if="showTotals">
					<tr class="totals">
						<td>Total</td>
						<td class="num">{{ fmt(totals.sales) }}</td>
						<td class="num">{{ fmt(totals.forecast) }}</td>
						<td class="num">{{ fmt(totals.budget) }}</td>
					</tr>
				</tfoot>
			</table>
		</div>
	</div>
</template>

<style scoped>
.pc-table {
	height: 100%;
	display: flex;
}

.tbl-wrap {
	flex: 1 1 auto;
	min-height: 0;
	overflow: auto;
}

.tbl {
	width: 100%;
	border-collapse: separate;
	border-spacing: 0;
	table-layout: fixed;
	font-size: .9rem;
	line-height: 1.25;
}

/* Cabecera compacta, sticky */
thead th {
	position: sticky;
	top: 0;
	z-index: 1;
	text-align: left;
	font-weight: 600;
	padding: 8px 10px;
	border-bottom: 1px solid rgba(2, 6, 23, .12);
	background: rgba(255, 255, 255, .35);
	backdrop-filter: blur(6px);
}

tbody td {
	padding: 6px 10px;
	border-bottom: 1px solid rgba(2, 6, 23, .08);
	vertical-align: top;
}

.td-name {
	color: inherit;
}

.pc-name {
	display: -webkit-box;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: normal;
	line-height: 1.25;
	max-height: calc(1.25em * 2);
}

@media (min-width: 1400px) {
	.pc-name {
		max-height: calc(1.25em * 3);
	}
}

/* Números derecha */
.num {
	text-align: right;
}

/* Totales solo para M3 y EUR */
tfoot td {
	padding: 8px 10px;
	font-weight: 700;
	border-top: 1px solid rgba(2, 6, 23, .12);
	background: rgba(255, 255, 255, .25);
}

th,
td {
	outline: 0;
}
</style>
