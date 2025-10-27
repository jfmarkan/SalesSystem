<template>
    <div class="container-fluid admin-home">
        <!-- Schnellaktionen -->
        <div class="row mt-16">
            <div class="span-12">
                <div class="sa-card card no-strip">
                    <div class="sa-title">Schnellaktionen</div>
                    <div class="sa-grid">
                        <button class="chip chip-kunden" @click="openBudgetDialog">
                            <i class="pi pi-percentage"></i>
                            Budget für GJ {{ activeFy }} erzeugen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Drei Panels -->
        <div class="row mt-16">
            <div class="span-12 md-span-4 xl-span-3">
                <div class="card h-full no-strip">
                    <UsersCompactList :users="users" :onlineMap="onlineMap" @create="openCreateUser"
                        @block="onBlockUser" @kick="onKickUser" @edit="onEditUser" />
                </div>
            </div>

            <div class="span-12 md-span-4 xl-span-6">
                <div class="card h-full no-strip">
                    <ClientsMiniList :items="clientsPageItems" :total="clientsTotal" :page="clientsPage"
                        :perPage="clientsPerPage" :query="clientsQuery"
                        @update:page="v => { clientsPage = v; loadClientsPaged() }"
                        @update:perPage="v => { clientsPerPage = v; clientsPage = 1; loadClientsPaged() }"
                        @update:query="v => { clientsQuery = v; clientsPage = 1; loadClientsPaged() }"
                        @create="goto('/admin/clients')" @view="openClientQuick" @edit="() => goto('/admin/clients')"
                        @delete="onDeleteClient" />
                </div>
            </div>

            <div class="span-12 md-span-4 xl-span-3">
                <div class="card h-full no-strip">
                    <ProfitCentersMiniList :items="pcsShort" :total="pcsTotal" :counts="pcClientCounts"
                        @create="goto('/admin/profit-centers')" @view="() => goto('/admin/profit-centers')"
                        @edit="() => goto('/admin/profit-centers')" />
                </div>
            </div>
        </div>

        <!-- Benutzer erstellen -->
        <Dialog v-model:visible="createUserVisible" header="Neuer Benutzer" :modal="true" class="unified-glass-dialog"
            :style="{ width: '560px' }">
            <div class="row">
                <div class="span-12 md-span-6">
                    <FloatLabel>
                        <InputText id="fn" v-model="newUser.first_name" class="input" />
                        <label for="fn">Vorname</label>
                    </FloatLabel>
                </div>
                <div class="span-12 md-span-6">
                    <FloatLabel>
                        <InputText id="ln" v-model="newUser.last_name" class="input" />
                        <label for="ln">Nachname</label>
                    </FloatLabel>
                </div>
                <div class="span-12 md-span-6">
                    <FloatLabel>
                        <InputText id="un" v-model="newUser.username" class="input" />
                        <label for="un">Benutzername</label>
                    </FloatLabel>
                </div>
                <div class="span-12 md-span-6">
                    <FloatLabel>
                        <InputText id="em" v-model="newUser.email" class="input" />
                        <label for="em">E-Mail</label>
                    </FloatLabel>
                </div>
                <div class="span-12 md-span-6">
                    <FloatLabel>
                        <Password id="pw" v-model="newUser.password" toggleMask :feedback="false" class="w-100" />
                        <label for="pw">Passwort</label>
                    </FloatLabel>
                </div>
                <div class="span-12 md-span-6">
                    <Dropdown v-model="newUser.role_id" :options="roles" optionLabel="label" optionValue="value"
                        placeholder="Rolle" class="w-100" />
                </div>
            </div>
            <template #footer>
                <Button label="Abbrechen" severity="secondary" @click="createUserVisible = false" />
                <Button label="Erstellen" icon="pi pi-check" @click="onCreateUser" />
            </template>
        </Dialog>

        <!-- Client Schnellansicht -->
        <ClientQuickView v-model="clientQuickVisible" :client="clientQuick" :pcs="clientQuickPcs"
            :loading="clientQuickLoading" @edit="() => { clientQuickVisible = false; goto('/admin/clients') }" />

        <!-- Budget-Dialog -->
        <Dialog v-model:visible="budgetDialogVisible" :modal="true" :draggable="false" class="unified-glass-dialog"
            :style="{ width: '680px', maxWidth: '92vw' }">
            <template #header>
                <div class="dlg-header">
                    <i class="pi pi-percentage"></i>
                    <div>
                        <div class="dlg-title">Budget erzeugen</div>
                        <div class="dlg-sub">GJ {{ activeFy }} · April–März</div>
                    </div>
                </div>
            </template>

            <div class="row gutter-16">
                <!-- C -->
                <div class="span-12 md-span-6">
                    <div class="inner">
                        <div class="sec-title">Kunden C</div>
                        <div class="field-row">
                            <label>Bestfall (%)</label>
                            <InputNumber v-model="form.best_case_c" :min="-100" :max="100" :step="0.5" suffix=" %"
                                inputClass="w-100" class="w-100" />
                        </div>
                        <div class="field-row">
                            <label>Schlechtfall (%)</label>
                            <InputNumber v-model="form.worst_case_c" :min="-100" :max="100" :step="0.5" suffix=" %"
                                inputClass="w-100" class="w-100" />
                        </div>
                    </div>
                </div>

                <!-- D / PA / PB -->
                <div class="span-12 md-span-6">
                    <div class="inner">
                        <div class="sec-title">Kunden D / PA / PB</div>
                        <div class="field-row">
                            <label>Bestfall (%)</label>
                            <InputNumber v-model="form.best_case_d" :min="-100" :max="100" :step="0.5" suffix=" %"
                                inputClass="w-100" class="w-100" />
                        </div>
                        <div class="field-row">
                            <label>Schlechtfall (%)</label>
                            <InputNumber v-model="form.worst_case_d" :min="-100" :max="100" :step="0.5" suffix=" %"
                                inputClass="w-100" class="w-100" />
                        </div>
                    </div>
                </div>

                <!-- Modus -->
                <div class="span-12">
                    <div class="inner">
                        <div class="sec-title">PA/PB Modus</div>
                        <Dropdown v-model="form.pa_pb_mode" :options="paPbModeOptions" optionLabel="label"
                            optionValue="value" class="w-100" placeholder="Modus auswählen" />
                        <small class="muted">D: nutzt D-Prozente. AB_BUDGET_CASES: liest aus budget_cases. AB_MANUAL:
                            unten je CPC eingeben.</small>
                    </div>
                </div>

                <!-- AB_MANUAL JSON -->
                <div class="span-12" v-if="form.pa_pb_mode === 'AB_MANUAL'">
                    <div class="inner">
                        <div class="sec-title">PA/PB manuell (JSON)</div>
                        <textarea v-model="paPbCasesText" class="codeblock" rows="8" spellcheck="false" placeholder='{
  "101": { "best": 5, "worst": -3 },
  "205": { "best": 0, "worst": 0 }
}'></textarea>
                        <small class="muted">Schema: { "cpc_id": { "best": number, "worst": number }, ... }</small>
                    </div>
                </div>

                <div class="span-12" v-if="submitError">
                    <div class="alert error">{{ submitError }}</div>
                </div>
                <div class="span-12" v-if="submitOk">
                    <div class="alert ok">✔️ {{ submitOk }}</div>
                </div>
            </div>

            <template #footer>
                <Button label="Abbrechen" severity="secondary" :disabled="submitting"
                    @click="budgetDialogVisible = false" />
                <Button label="Erzeugen" icon="pi pi-check" :loading="submitting" :disabled="!isValidForm || submitting"
                    @click="submitBudget" />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
/* All comments, function names, and logic in English */
import { ref, computed, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import FloatLabel from 'primevue/floatlabel'
import Password from 'primevue/password'
import InputNumber from 'primevue/inputnumber'
import { useToast } from 'primevue/usetoast'
import { useRouter } from 'vue-router'

import ClientQuickView from '@/components/admin/ClientQuickView.vue'
import UsersCompactList from '@/components/admin/UsersCompactList.vue'
import ClientsMiniList from '@/components/admin/ClientsMiniList.vue'
import ProfitCentersMiniList from '@/components/admin/ProfitCentersMiniList.vue'
import { useAdminApi } from '@/composables/useAdminApi'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'

const toast = useToast()
const router = useRouter()

/* pagination and lists */
const clientsPage = ref(1)
const clientsPerPage = ref(20)
const clientsQuery = ref('')
const clientsPageItems = ref([])
const _clientsCache = ref([])

/* API hooks */
const {
    getUsers,
    getSessionsOnline,
    getClients,
    getProfitCenters,
    getClientsSummary,
    getPcsSummary,
    getKpis,
    getProgress,
    createUser,
    blockUser,
    kickUser,
    deleteClient,
    getClientPcs
} = useAdminApi()

/* base state */
const loading = ref(false)
const errorMsg = ref('')

const users = ref([])
const onlineMap = ref({})

const clients = ref([])
const profitCenters = ref([])

const clientsSummaryState = ref({ total: 0, active: 0, blocked: 0, pc_counts: {} })
const pcsSummaryState = ref({ total: 0, active: 0, archived: 0, clients_per_pc: {} })

const kpisState = ref(null)
const progressState = ref(null)

const clientsTotal = ref(0)
const pcsTotal = ref(0)

const clientsShort = ref([])
const pcsShort = ref([])
const pcClientCounts = ref({})

/* fiscal year helper: FY Apr–Mar */
function fyOf(d = new Date()) {
    const y = d.getFullYear(), m = d.getMonth() + 1
    return m >= 4 ? y : y - 1
}
const activeFy = ref(fyOf())

/* util: normalize arrays */
function takeArray(res) {
    const d = res?.data ?? res
    if (Array.isArray(d)) return d
    if (!d || typeof d !== 'object') return []
    for (const k of ['items', 'list', 'rows', 'clients', 'profit_centers', 'pcs', 'data']) {
        if (Array.isArray(d[k])) return d[k]
    }
    return []
}

/* navigation */
function goto(path) { router.push(path) }

/* users: dialog and actions */
const createUserVisible = ref(false)
const newUser = ref({ first_name: '', last_name: '', username: '', email: '', password: '', role_id: 4 })
const roles = [
    { label: 'Superadmin', value: 1 },
    { label: 'Admin', value: 2 },
    { label: 'Manager', value: 3 },
    { label: 'Sales Rep', value: 4 }
]
function openCreateUser() { createUserVisible.value = true }

async function onCreateUser() {
    try {
        await createUser({ ...newUser.value })
        toast.add({ severity: 'success', summary: 'Erstellt', detail: 'Benutzer erstellt', life: 1600 })
        createUserVisible.value = false
        await loadUsers()
    } catch {
        toast.add({ severity: 'error', summary: 'Fehler', detail: 'Konnte nicht erstellen', life: 2200 })
    }
}
async function onBlockUser(u) {
    try {
        await blockUser(u.id)
        toast.add({ severity: 'warn', summary: 'Blockiert', detail: u.username, life: 1500 })
        await loadUsers()
    } catch {
        toast.add({ severity: 'error', summary: 'Fehler', detail: 'Blockieren fehlgeschlagen', life: 2200 })
    }
}
async function onKickUser(u) {
    try {
        await kickUser(u.id)
        toast.add({ severity: 'info', summary: 'Abgemeldet', detail: u.username, life: 1500 })
        await loadUsers()
    } catch {
        toast.add({ severity: 'error', summary: 'Fehler', detail: 'Aktion fehlgeschlagen', life: 2200 })
    }
}
async function onEditUser() { goto('/admin/users') }

/* clients actions */
async function onDeleteClient(c) {
    try {
        await deleteClient(c.client_group_number)
        toast.add({ severity: 'success', summary: 'Gelöscht', detail: c.client_name, life: 1500 })
        await loadClientsAndPcs()
    } catch {
        toast.add({ severity: 'error', summary: 'Fehler', detail: 'Löschen fehlgeschlagen', life: 2200 })
    }
}

/* load collections */
async function loadUsers() {
    const [uRes, omRes] = await Promise.all([getUsers(), getSessionsOnline()])
    users.value = uRes?.data ?? []
    onlineMap.value = omRes?.data ?? {}
}
async function loadClientsAndPcs() {
    const [rC, rPC, rCS, rPS] = await Promise.allSettled([
        getClients(),
        getProfitCenters(),
        getClientsSummary(),
        getPcsSummary()
    ])
    if (rC.status === 'fulfilled') {
        const list = takeArray(rC.value)
        clients.value = list
        clientsShort.value = list.slice(0, 50)
        clientsTotal.value = list.length
    }
    if (rCS.status === 'fulfilled' && rCS.value?.data) {
        clientsSummaryState.value = rCS.value.data
        if (!clientsTotal.value) clientsTotal.value = Number(rCS.value.data?.total ?? 0)
    }
    if (rPC.status === 'fulfilled') {
        const list = takeArray(rPC.value)
        profitCenters.value = list
        pcsShort.value = list.slice(0, 50)
        pcsTotal.value = list.length
    }
    if (rPS.status === 'fulfilled' && rPS.value?.data) {
        pcsSummaryState.value = rPS.value.data
        pcClientCounts.value = rPS.value.data?.clients_per_pc || {}
        if (!pcsTotal.value) pcsTotal.value = Number(rPS.value.data?.total ?? 0)
    }
}
async function loadKpisAndProgress() {
    const [rK, rPr] = await Promise.allSettled([getKpis(), getProgress(activeFy.value)])
    if (rK.status === 'fulfilled') kpisState.value = rK.value?.data ?? null
    if (rPr.status === 'fulfilled') progressState.value = rPr.value?.data ?? null
}
async function load() {
    loading.value = true
    errorMsg.value = ''
    try {
        await loadUsers()
        await Promise.all([loadClientsAndPcs(), loadKpisAndProgress()])
    } finally {
        loading.value = false
    }
}

/* client pagination with server fallback */
function parseClientsResponse(res) {
    const d = res?.data ?? res
    if (Array.isArray(d)) return { items: d, total: d.length, serverPaged: false }
    if (d && typeof d === 'object') {
        const items = Array.isArray(d.data) ? d.data
            : Array.isArray(d.items) ? d.items
                : Array.isArray(d.clients) ? d.clients
                    : []
        const total = Number(d?.meta?.total ?? d?.total ?? items.length) || items.length
        const serverPaged = !!(d?.meta?.total || d?.total)
        return { items, total, serverPaged }
    }
    return { items: [], total: 0, serverPaged: false }
}
async function loadClientsPaged() {
    try {
        const res = await getClients({
            page: clientsPage.value,
            per_page: clientsPerPage.value,
            q: clientsQuery.value || undefined
        })
        const { items, total, serverPaged } = parseClientsResponse(res)
        if (serverPaged) {
            clientsPageItems.value = items
            clientsTotal.value = total
            return
        }
        if (!_clientsCache.value.length) _clientsCache.value = items
        const list = clientsQuery.value
            ? _clientsCache.value.filter(c =>
                String(c.client_name).toLowerCase().includes(clientsQuery.value.toLowerCase()) ||
                String(c.client_group_number).includes(clientsQuery.value))
            : _clientsCache.value
        clientsTotal.value = list.length
        const start = (clientsPage.value - 1) * clientsPerPage.value
        clientsPageItems.value = list.slice(start, start + clientsPerPage.value)
    } catch {
        clientsPageItems.value = []
        clientsTotal.value = 0
    }
}

/* quick view */
const clientQuickVisible = ref(false)
const clientQuick = ref(null)
const clientQuickPcs = ref([])
const clientQuickLoading = ref(false)
async function openClientQuick(c) {
    clientQuick.value = c
    clientQuickPcs.value = []
    clientQuickLoading.value = true
    clientQuickVisible.value = true
    try {
        const res = await getClientPcs(c.client_group_number)
        const d = res?.data ?? res
        clientQuickPcs.value = Array.isArray(d) ? d
            : (Array.isArray(d?.items) ? d.items
                : (Array.isArray(d?.pcs) ? d.pcs : []))
    } catch {
        clientQuickPcs.value = []
    } finally {
        clientQuickLoading.value = false
    }
}

/* budget dialog state */
const budgetDialogVisible = ref(false)
const submitting = ref(false)
const submitError = ref('')
const submitOk = ref('')

/* form state */
const form = ref({
    best_case_c: 5,
    worst_case_c: -5,
    best_case_d: 0,
    worst_case_d: 0,
    pa_pb_mode: 'D' // 'D' | 'AB_BUDGET_CASES' | 'AB_MANUAL'
})
const paPbModeOptions = [
    { label: 'D', value: 'D' },
    { label: 'AB_BUDGET_CASES', value: 'AB_BUDGET_CASES' },
    { label: 'AB_MANUAL', value: 'AB_MANUAL' }
]
const paPbCasesText = ref('')

/* validation */
const isValidPct = v => typeof v === 'number' && v >= -100 && v <= 100
function isValidManualJson() {
    if (!paPbCasesText.value.trim()) return true
    try { const obj = JSON.parse(paPbCasesText.value); return obj && typeof obj === 'object' } catch { return false }
}
const isValidForm = computed(() =>
    isValidPct(form.value.best_case_c) &&
    isValidPct(form.value.worst_case_c) &&
    isValidPct(form.value.best_case_d) &&
    isValidPct(form.value.worst_case_d) &&
    ['D', 'AB_BUDGET_CASES', 'AB_MANUAL'].includes(form.value.pa_pb_mode) &&
    (form.value.pa_pb_mode !== 'AB_MANUAL' || isValidManualJson())
)

/* open dialog */
function openBudgetDialog() {
    submitError.value = ''
    submitOk.value = ''
    budgetDialogVisible.value = true
}

/* endpoint constant (API route) */
const BUDGET_ENDPOINT = '/api/budgets/generate'

/* submit to backend controller BudgetGenerationController@generate */
async function submitBudget() {
    submitError.value = ''
    submitOk.value = ''
    if (!isValidForm.value) { submitError.value = 'Bitte Prozentsätze oder JSON prüfen.'; return }

    submitting.value = true
    try {
        const payload = {
            best_case_c: Number(form.value.best_case_c),
            worst_case_c: Number(form.value.worst_case_c),
            best_case_d: Number(form.value.best_case_d),
            worst_case_d: Number(form.value.worst_case_d),
            pa_pb_mode: form.value.pa_pb_mode
        }

        if (form.value.pa_pb_mode === 'AB_MANUAL' && paPbCasesText.value.trim()) {
            // safe parse
            try {
                payload.pa_pb_cases = JSON.parse(paPbCasesText.value)
            } catch {
                throw new Error('Ungültiges JSON für PA/PB')
            }
        }

        // Ensure CSRF + use Axios plugin (same pattern as rest of app)
        await ensureCsrf()
        const { data } = await api.post(BUDGET_ENDPOINT, payload, {
            withCredentials: true,
            headers: { Accept: 'application/json' }
        })

        const msg = data?.message || 'Budget erzeugt'
        const b = Number(data?.budgets_rows ?? 0)
        const f = Number(data?.forecasts_rows ?? 0)
        submitOk.value = `${msg} · Budgets: ${b} · Forecasts: ${f}`

        toast.add({ severity: 'success', summary: 'Erfolg', detail: submitOk.value, life: 2200 })
        budgetDialogVisible.value = false
    } catch (e) {
        submitError.value = String(e?.response?.data?.message || e?.message || 'Unbekannter Fehler')
        toast.add({ severity: 'error', summary: 'Fehler', detail: submitError.value, life: 2600 })
    } finally {
        submitting.value = false
    }
}

/* lifecycle */
onMounted(async () => {
    await load()
    await loadClientsPaged()
})
</script>

<style scoped>
/* UNIFIED GLASSMORPHISM MODAL SYSTEM */

/* Backdrop: dim + blur 10px */
:deep(.p-dialog-mask) {
    background: rgba(0, 0, 0, 0.4) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    backdrop-filter: blur(10px) !important;
}

/* Dialog shell: transparent container */
.unified-glass-dialog :deep(.p-dialog) {
    background: transparent !important;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Light theme glassmorphism */
:root:not(.dark) .unified-glass-dialog :deep(.p-dialog-header),
:root:not(.dark) .unified-glass-dialog :deep(.p-dialog-footer) {
    background: rgba(255, 255, 255, 0.3) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    backdrop-filter: blur(10px) !important;
    color: #1a1a1a;
    border-color: rgba(0, 0, 0, 0.1);
}

:root:not(.dark) .unified-glass-dialog :deep(.p-dialog-content) {
    background: rgba(255, 255, 255, 0.3) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    backdrop-filter: blur(10px) !important;
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

/* Dark theme glassmorphism */
:root.dark .unified-glass-dialog :deep(.p-dialog-header),
:root.dark .unified-glass-dialog :deep(.p-dialog-footer) {
    background: rgba(0, 0, 0, 0.3) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    backdrop-filter: blur(10px) !important;
    color: #e9eef5;
    border-color: rgba(255, 255, 255, 0.1);
}

:root.dark .unified-glass-dialog :deep(.p-dialog-content) {
    background: rgba(0, 0, 0, 0.3) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    backdrop-filter: blur(10px) !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

/* Dialog header layout */
.dlg-header {
    display: flex;
    align-items: center;
    gap: 12px
}

.dlg-header .pi {
    font-size: 1.2rem
}

.dlg-title {
    font-weight: 800
}

.dlg-sub {
    font-size: 0.85rem;
    opacity: 0.75
}

/* Inner content blocks */
.inner {
    padding: 14px;
    border-radius: 12px;
    background: transparent !important;
    border: 0 !important
}

.sec-title {
    font-weight: 700;
    margin-bottom: 8px
}

.field-row {
    display: grid;
    gap: 6px;
    margin-bottom: 10px
}

.muted {
    opacity: 0.8
}

/* JSON textarea */
:root.dark .codeblock {
    width: 100%;
    border-radius: 12px;
    padding: 10px 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 0.9rem;
    background: rgba(0, 0, 0, 0.28);
    color: #e6e6e6;
    border: 1px solid rgba(255, 255, 255, 0.08);
    outline: none;
    resize: vertical;
}

:root:not(.dark) .codeblock {
    width: 100%;
    border-radius: 12px;
    padding: 10px 12px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 0.9rem;
    background: rgba(255, 255, 255, 0.92);
    color: #1d1d1f;
    border: 1px solid rgba(0, 0, 0, 0.08);
    outline: none;
    resize: vertical;
}

/* Alerts */
.alert {
    padding: 10px 12px;
    border-radius: 12px;
    font-weight: 600
}

:root.dark .alert.ok {
    background: rgba(56, 142, 60, 0.18);
    color: #b9e6c9;
    border: 1px solid rgba(56, 142, 60, 0.35)
}

:root.dark .alert.error {
    background: rgba(211, 47, 47, 0.18);
    color: #ffc9c9;
    border: 1px solid rgba(211, 47, 47, 0.35)
}

:root:not(.dark) .alert.ok {
    background: rgba(56, 142, 60, 0.12);
    color: #2e7d32;
    border: 1px solid rgba(56, 142, 60, 0.3)
}

:root:not(.dark) .alert.error {
    background: rgba(211, 47, 47, 0.12);
    color: #c62828;
    border: 1px solid rgba(211, 47, 47, 0.3)
}

/* Schnellaktionen */
.sa-card {
    padding: 14px
}

.sa-title {
    font-weight: 800;
    margin-bottom: 10px
}

.sa-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px
}

.chip {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-radius: 14px;
    color: #fff;
    border: 0;
    cursor: pointer;
    font-weight: 700;
    letter-spacing: 0.2px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
    transition: transform 0.08s ease, box-shadow 0.2s ease;
}

.chip i {
    font-size: 1.2rem
}

.chip:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22)
}

.chip-kunden {
    background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82)
}

/* Cards */
.no-strip {
    padding-top: 10px
}

.h-full {
    height: 100%;
    min-height: 360px
}

/* Layout spacing */
.row.gutter-16 {
    row-gap: 16px
}
</style>
