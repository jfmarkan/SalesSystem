<!-- src/views/admin/profit-centers/ProfitCentersIndex.vue -->
<template>
        <div class="container-fluid admin-index pcs-index">
		<div class="row">
			<!-- Lista -->
			<div class="span-12 md-span-4 xl-span-3">
				<GlassCard :title="''" class="no-strip h-full">
					<div class="p-2">
						<InputText v-model="q" class="input" placeholder="Suchen…" />
					</div>
					<div class="list">
						<div
							v-for="p in filtered"
							:key="p.profit_center_code"
							class="item"
							:class="{
								active: selected?.profit_center_code === p.profit_center_code,
							}"
							@click="select(p)"
						>
							<div class="line1">{{ p.profit_center_name }}</div>
							<div class="line2">#{{ p.profit_center_code }}</div>
						</div>
					</div>
				</GlassCard>
			</div>

			<!-- Editor -->
			<div class="span-12 md-span-8 xl-span-9">
				<GlassCard :title="''" class="no-strip h-full">
					<div v-if="!selected" class="placeholder">Profit-Center auswählen</div>

					<div v-else class="editor">
						<div class="row">
							<div class="span-12 md-span-4">
								<FloatLabel>
									<InputNumber
										inputId="pc"
										v-model="form.profit_center_code"
										:useGrouping="false"
										disabled
										class="w-100"
									/>
									<label for="pc">Code</label>
								</FloatLabel>
							</div>
							<div class="span-12 md-span-8">
								<FloatLabel>
									<InputText
										id="pn"
										v-model="form.profit_center_name"
										class="input"
									/>
									<label for="pn">Name</label>
								</FloatLabel>
							</div>

							<div class="span-12 md-span-6">
								<FloatLabel>
									<InputText id="unit" v-model="conv.from_unit" class="input" />
									<label for="unit">Einheit</label>
								</FloatLabel>
							</div>
							<div class="span-12 md-span-3">
								<FloatLabel>
									<InputNumber
										inputId="m3"
										v-model="conv.factor_to_m3"
										:minFractionDigits="2"
										:maxFractionDigits="6"
										class="w-100"
									/>
									<label for="m3">Faktor → m³</label>
								</FloatLabel>
							</div>
							<div class="span-12 md-span-3">
								<FloatLabel>
									<InputNumber
										inputId="eur"
										v-model="conv.factor_to_euro"
										:minFractionDigits="2"
										:maxFractionDigits="6"
										class="w-100"
									/>
									<label for="eur">Faktor → €</label>
								</FloatLabel>
							</div>
						</div>

						<hr class="div" />

						<div class="season">
							<div class="pcs-title">Saisonalität</div>
							<div class="season-grid">
								<div v-for="m in months" :key="m.key" class="s-item">
									<label :for="m.key">{{ m.label }}</label>
									<InputNumber
										:inputId="m.key"
										v-model="season[m.key]"
										:minFractionDigits="0"
										:maxFractionDigits="2"
										class="w-100"
									/>
								</div>
							</div>
						</div>

						<div class="actions">
							<Button label="Speichern" icon="pi pi-save" @click="onSave" />
						</div>
					</div>
				</GlassCard>
			</div>
		</div>
	</div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import GlassCard from '@/components/ui/GlassCard.vue'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import FloatLabel from 'primevue/floatlabel'
import Button from 'primevue/button'
import { useToast } from 'primevue/usetoast'
import { useAdminApi } from '@/composables/useAdminApi'

const toast = useToast()
const { listPcs, getPcDetail, savePc, savePcConversion, savePcSeasonality } = useAdminApi()

const q = ref('')
const pcs = ref([])
const selected = ref(null)
const form = ref({ profit_center_code: null, profit_center_name: '' })
const conv = ref({ from_unit: '', factor_to_m3: 1, factor_to_euro: 1 })
const season = ref({
	apr: 0,
	may: 0,
	jun: 0,
	jul: 0,
	aug: 0,
	sep: 0,
	oct: 0,
	nov: 0,
	dec: 0,
	jan: 0,
	feb: 0,
	mar: 0,
})
const months = [
	{ key: 'apr', label: 'Apr' },
	{ key: 'may', label: 'Mai' },
	{ key: 'jun', label: 'Jun' },
	{ key: 'jul', label: 'Jul' },
	{ key: 'aug', label: 'Aug' },
	{ key: 'sep', label: 'Sep' },
	{ key: 'oct', label: 'Okt' },
	{ key: 'nov', label: 'Nov' },
	{ key: 'dec', label: 'Dez' },
	{ key: 'jan', label: 'Jan' },
	{ key: 'feb', label: 'Feb' },
	{ key: 'mar', label: 'Mär' },
]

const filtered = computed(() => {
	const s = q.value.trim().toLowerCase()
	if (!s) return pcs.value
	return pcs.value.filter(
		(p) =>
			String(p.profit_center_name).toLowerCase().includes(s) ||
			String(p.profit_center_code).includes(s),
	)
})

async function select(p) {
	selected.value = p
	form.value = {
		profit_center_code: p.profit_center_code,
		profit_center_name: p.profit_center_name,
	}
	const { pc, conv: c } = await getPcDetail(p.profit_center_code)
	if (pc?.seasonality) {
		const s = pc.seasonality
		season.value = {
			apr: s.apr,
			may: s.may,
			jun: s.jun,
			jul: s.jul,
			aug: s.aug,
			sep: s.sep,
			oct: s.oct,
			nov: s.nov,
			dec: s.dec,
			jan: s.jan,
			feb: s.feb,
			mar: s.mar,
		}
	} else {
		season.value = {
			apr: 0,
			may: 0,
			jun: 0,
			jul: 0,
			aug: 0,
			sep: 0,
			oct: 0,
			nov: 0,
			dec: 0,
			jan: 0,
			feb: 0,
			mar: 0,
		}
	}
	if (c)
		conv.value = {
			from_unit: c.from_unit,
			factor_to_m3: Number(c.factor_to_m3),
			factor_to_euro: Number(c.factor_to_euro),
		}
	else conv.value = { from_unit: '', factor_to_m3: 1, factor_to_euro: 1 }
}

async function onSave() {
	try {
		await savePc(form.value.profit_center_code, {
			profit_center_name: form.value.profit_center_name,
		})
		await savePcConversion(form.value.profit_center_code, {
			from_unit: conv.value.from_unit,
			factor_to_m3: conv.value.factor_to_m3,
			factor_to_euro: conv.value.factor_to_euro,
		})
		await savePcSeasonality(form.value.profit_center_code, { ...season.value })
		toast.add({
			severity: 'success',
			summary: 'Gespeichert',
			detail: 'Profit-Center aktualisiert',
			life: 1800,
		})
	} catch {
		toast.add({
			severity: 'error',
			summary: 'Fehler',
			detail: 'Speichern fehlgeschlagen',
			life: 2200,
		})
	}
}

async function load() {
	pcs.value = await listPcs()
}
onMounted(load)
</script>

<style scoped>
.season-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 8px;
}
@media (max-width: 991.98px) {
	.season-grid {
		grid-template-columns: repeat(3, minmax(0, 1fr));
	}
}
.s-item label {
        font-size: 0.8rem;
        color: var(--muted);
}
</style>
