<template>
    <div class="p-4">
        <div class="flex align-items-center justify-content-between mb-3">
            <h2 class="m-0">Benutzerverwaltung</h2>
            <small class="text-500">Nur für Superadmin</small>
        </div>

        <div v-if="loading" class="flex justify-content-center py-6">
            <ProgressSpinner />
        </div>

        <div v-else class="grid">
            <div v-for="u in users" :key="u.id" class="col-12 md:col-6 lg:col-4">
                <Card class="shadow-2">
                    <template #title>
                        <div class="flex align-items-center gap-2">
                            <span :class="['user-name', u.disabled ? 'user-name--disabled' : '']">
                                {{ u.name || u.first_name + ' ' + u.last_name }}
                            </span>
                            <i
                                :class="
                                    u.disabled
                                        ? 'pi pi-times text-red-500'
                                        : 'pi pi-check text-green-500'
                                "
                            ></i>
                        </div>
                    </template>

                    <template #content>
                        <div class="text-800 mb-2">{{ u.email }}</div>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <Tag
                                :value="u.disabled ? 'Gesperrt' : 'Aktiv'"
                                :severity="u.disabled ? 'danger' : 'success'"
                            />
                            <Tag v-if="u.roles?.length" :value="u.roles[0]" severity="info" />
                            <Tag
                                v-for="tid in u.teamIds"
                                :key="tid"
                                :value="teamName(tid)"
                                severity="secondary"
                            />
                        </div>

                        <div class="flex justify-content-end gap-2">
                            <!-- Toggle Active Dial -->
                            <SpeedDial
                                :model="[
                                    {
                                        label: 'Aktivieren',
                                        icon: 'pi pi-check',
                                        class: 'p-button-rounded p-button-success',
                                        command: () => toggleBlock(u, false),
                                    },
                                    {
                                        label: 'Sperren',
                                        icon: 'pi pi-times',
                                        class: 'p-button-rounded p-button-danger',
                                        command: () => toggleBlock(u, true),
                                    },
                                ]"
                                direction="left"
                                type="linear"
                                :showIcon="u.disabled ? 'pi pi-times' : 'pi pi-check'"
                                hideIcon="pi pi-times"
                                buttonClass="p-button-rounded p-button-secondary"
                                appendTo="body"
                                v-tooltip.top="'Sperren/Aktivieren'"
                            />
                            <!-- Kunden übertragen -->
                            <Button
                                icon="pi pi-sync"
                                rounded
                                severity="info"
                                @click="openTransfer(u)"
                                v-tooltip.top="'Kunden übertragen'"
                            />
                            <!-- Teams -->
                            <Button
                                icon="pi pi-sitemap"
                                rounded
                                severity="help"
                                @click="openTeams(u)"
                                v-tooltip.top="'Teams ändern'"
                            />
                            <!-- Rolle -->
                            <Button
                                icon="pi pi-sliders-h"
                                rounded
                                severity="warning"
                                @click="openRole(u)"
                                v-tooltip.top="'Rolle ändern'"
                            />
                        </div>
                    </template>
                </Card>
            </div>
        </div>

        <!-- Teams Dialog -->
        <Dialog
            v-model:visible="dlgTeams"
            :modal="true"
            :style="{ width: '520px' }"
            header="Teams ändern"
            appendTo="body"
        >
            <div class="mb-2 text-700">
                Benutzer: <b>{{ ctxUser?.name }}</b>
            </div>
            <div class="p-float-label">
                <MultiSelect
                    id="teams"
                    v-model="teamsLocal"
                    :options="teams"
                    optionLabel="name"
                    optionValue="id"
                    display="chip"
                    class="w-full"
                />
                <label for="teams">Teams</label>
            </div>
            <Message severity="warn" class="mt-3" :closable="false"
                >Das gesamte Set wird ersetzt.</Message
            >
            <template #footer>
                <Button
                    label="Abbrechen"
                    text
                    severity="secondary"
                    type="button"
                    @click="dlgTeams = false"
                />
                <Button
                    label="Speichern"
                    icon="pi pi-check"
                    severity="primary"
                    :loading="savingTeams"
                    type="button"
                    @click.prevent.stop="confirmTeams()"
                />
            </template>
        </Dialog>

        <!-- Role Dialog -->
        <Dialog
            v-model:visible="dlgRole"
            :modal="true"
            :style="{ width: '420px' }"
            header="Rolle ändern"
            appendTo="body"
        >
            <div class="mb-2 text-700">
                Benutzer: <b>{{ ctxUser?.name }}</b>
            </div>
            <div class="p-float-label">
                <Dropdown id="role" v-model="roleLocal" :options="roles" class="w-full" />
                <label for="role">Rolle</label>
            </div>
            <template #footer>
                <Button
                    label="Abbrechen"
                    text
                    severity="secondary"
                    type="button"
                    @click="dlgRole = false"
                />
                <Button
                    label="Speichern"
                    icon="pi pi-check"
                    severity="warning"
                    :disabled="!roleLocal"
                    type="button"
                    @click.prevent.stop="confirmRole()"
                />
            </template>
        </Dialog>

        <!-- Transfer Clients Dialog -->
        <Dialog
            v-model:visible="dlg.transfer"
            :modal="true"
            :style="{ width: '960px' }"
            header="Kunden übertragen"
            appendTo="body"
        >
            <div class="grid">
                <div class="col-12 md:col-4">
                    <div class="p-float-label mb-3">
                        <SelectButton
                            v-model="transfer.mode"
                            :options="modeOptions"
                            optionLabel="label"
                            optionValue="value"
                        />
                    </div>
                    <div class="p-float-label mb-3">
                        <Dropdown
                            id="target"
                            v-model="transfer.globalTarget"
                            :options="targetsOpts"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Zielbenutzer"
                            class="w-full"
                        />
                        <label for="target">Zielbenutzer</label>
                    </div>
                    <Message v-if="transfer.mode === 'all'" severity="info" :closable="false"
                        >Alle Kunden (alle Profitcenter) werden vollständig übertragen.</Message
                    >
                    <Message v-else-if="transfer.mode === 'pick'" severity="info" :closable="false"
                        >Nur ausgewählte Kunden werden übertragen.</Message
                    >
                    <Message v-else severity="info" :closable="false"
                        >Pro Zeile Kunden einem Ziel zuordnen.</Message
                    >
                </div>

                <div class="col-12 md:col-8">
                    <div class="flex align-items-center justify-content-between mb-2">
                        <span class="font-semibold">Kundenliste</span>
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText v-model="transfer.search" placeholder="Suchen" />
                        </span>
                    </div>

                    <DataTable
                        :value="filteredClients"
                        dataKey="clientGroupNumber"
                        v-model:selection="transfer.selection"
                        :selectionMode="transfer.mode === 'pick' ? 'checkbox' : null"
                        :loading="transfer.loading"
                        :rows="10"
                        paginator
                        responsiveLayout="scroll"
                        :rowHover="true"
                        :emptyMessage="'Keine Kunden'"
                    >
                        <Column
                            v-if="transfer.mode === 'pick'"
                            selectionMode="multiple"
                            headerStyle="width:3rem"
                        />
                        <Column
                            field="clientGroupNumber"
                            header="Kundengruppe"
                            style="width: 140px"
                        />
                        <Column field="clientName" header="Kunde" />
                        <Column field="count" header="CPC" style="width: 100px" />
                        <Column
                            v-if="transfer.mode === 'perRow'"
                            header="Zielbenutzer"
                            style="width: 260px"
                        >
                            <template #body="{ data }">
                                <Dropdown
                                    v-model="transfer.rowTargets[data.clientGroupNumber]"
                                    :options="targetsOpts"
                                    optionLabel="name"
                                    optionValue="id"
                                    placeholder="Auswählen"
                                    class="w-full"
                                />
                            </template>
                        </Column>
                    </DataTable>

                    <Message
                        v-if="!transfer.loading && filteredClients.length === 0"
                        severity="warn"
                        :closable="false"
                        class="mt-2"
                    >
                        Keine Kunden gefunden.
                    </Message>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Abbrechen"
                    text
                    severity="secondary"
                    type="button"
                    @click="dlg.transfer = false"
                />
                <Button
                    label="Bestätigen"
                    icon="pi pi-check"
                    :disabled="!canConfirmTransfer"
                    type="button"
                    @click.prevent.stop="confirmTransfer()"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/plugins/axios'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import SpeedDial from 'primevue/speeddial'
import Dialog from 'primevue/dialog'
import Dropdown from 'primevue/dropdown'
import MultiSelect from 'primevue/multiselect'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Tooltip from 'primevue/tooltip'
import SelectButton from 'primevue/selectbutton'
import InputText from 'primevue/inputtext'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

// ✅ local directive
const vTooltip = Tooltip

const users = ref([])
const teams = ref([])
const roles = ref([]) // role names
const allUsers = ref([]) // transfer targets
const loading = ref(false)
const savingTeams = ref(false)

const ctxUser = ref(null)
const dlgTeams = ref(false)
const dlgRole = ref(false)
const teamsLocal = ref([])
const roleLocal = ref(null)

const dlg = ref({ transfer: false })
const ctx = ref({ user: null })

const teamName = (id) => teams.value.find((t) => t.id === id)?.name || `#${id}`
const fullName = (u) =>
    (u?.name && String(u.name).trim()) || `${u?.first_name ?? ''} ${u?.last_name ?? ''}`.trim()

const targetsOpts = computed(() =>
    (allUsers.value || [])
        .filter((x) => x.id !== ctx.value.user?.id)
        .map((x) => ({ id: x.id, name: fullName(x) || x.email || `User #${x.id}` })),
)

async function load() {
    loading.value = true
    try {
        const [u, t, r, au] = await Promise.all([
            api.get('/api/sales-force/users'),
            api.get('/api/sales-force/teams'),
            api.get('/api/sales-force/roles'),
            api.get('/api/sales-force/users'),
        ])
        users.value = u.data || []
        teams.value = t.data || []
        roles.value = r.data || []
        allUsers.value = au.data || []
    } finally {
        loading.value = false
    }
}
onMounted(load)

/* Block/Unblock */
async function toggleBlock(u, disabled) {
    await api.patch(`/api/sales-force/users/${u.id}/block`, { disabled })
    const i = users.value.findIndex((x) => x.id === u.id)
    if (i !== -1) users.value[i] = { ...users.value[i], disabled }
}

/* Teams */
function openTeams(u) {
    ctxUser.value = u
    teamsLocal.value = [...(u.teamIds || [])]
    dlgTeams.value = true
}
async function confirmTeams() {
    if (!ctxUser.value || savingTeams.value) return
    try {
        savingTeams.value = true
        await api.patch(`/api/sales-force/users/${ctxUser.value.id}/teams`, {
            teamIds: teamsLocal.value,
        })
        await load() // 🔁 ensure UI reflects server state
        dlgTeams.value = false
    } finally {
        savingTeams.value = false
    }
}

/* Role */
function openRole(u) {
    ctxUser.value = u
    roleLocal.value = (u.roles && u.roles[0]) || null
    dlgRole.value = true
}
async function confirmRole() {
    if (!ctxUser.value || !roleLocal.value) return
    await api.patch(`/api/sales-force/users/${ctxUser.value.id}/roles`, { role: roleLocal.value })
    const i = users.value.findIndex((x) => x.id === ctxUser.value.id)
    if (i !== -1) users.value[i] = { ...users.value[i], roles: [roleLocal.value] }
    dlgRole.value = false
}

/* Transfer – list by CLIENT */
const transfer = ref({
    loading: false,
    mode: 'all', // 'all' | 'pick' | 'perRow'
    globalTarget: null,
    clients: [],
    selection: [],
    rowTargets: {},
    search: '',
})
const modeOptions = [
    { label: 'Alle', value: 'all' },
    { label: 'Auswahl', value: 'pick' },
    { label: 'Pro Zeile', value: 'perRow' },
]
const filteredClients = computed(() => {
    const s = (transfer.value.search || '').toLowerCase()
    return transfer.value.clients.filter(
        (r) =>
            String(r.clientGroupNumber).includes(s) ||
            (r.clientName || '').toLowerCase().includes(s),
    )
})
const canConfirmTransfer = computed(() => {
    if (!ctx.value.user?.id) return false
    if (transfer.value.mode === 'all') return !!transfer.value.globalTarget
    if (transfer.value.mode === 'pick')
        return !!transfer.value.globalTarget && transfer.value.selection.length > 0
    if (transfer.value.mode === 'perRow')
        return (
            filteredClients.value.length > 0 &&
            filteredClients.value.every((r) => !!transfer.value.rowTargets[r.clientGroupNumber])
        )
    return false
})

function openTransfer(u) {
    ctx.value.user = u
    dlg.value.transfer = true
    loadClientsForUser()
}

async function loadClientsForUser() {
    transfer.value.loading = true
    try {
        const { data } = await api.get('/api/sales-force/clients', {
            params: { userId: ctx.value.user.id },
        })
        transfer.value.clients = Array.isArray(data) ? data : []
        transfer.value.selection = []
        transfer.value.rowTargets = {}
        transfer.value.globalTarget = null
        transfer.value.mode = 'all'
        transfer.value.search = ''
    } finally {
        transfer.value.loading = false
    }
}

async function confirmTransfer() {
    const from = ctx.value.user.id

    if (transfer.value.mode === 'perRow') {
        const byUser = new Map()
        filteredClients.value.forEach((row) => {
            const to = transfer.value.rowTargets[row.clientGroupNumber]
            if (!to) return
            if (!byUser.has(to)) byUser.set(to, [])
            byUser.get(to).push(row.clientGroupNumber)
        })
        for (const [toUserId, clientGroupNumbers] of byUser.entries()) {
            await api.post(`/api/sales-force/users/${from}/transfer`, {
                toUserId,
                clientGroupNumbers,
            })
        }
    } else if (transfer.value.mode === 'pick') {
        await api.post(`/api/sales-force/users/${from}/transfer`, {
            toUserId: transfer.value.globalTarget,
            clientGroupNumbers: transfer.value.selection.map((s) => s.clientGroupNumber),
        })
    } else {
        await api.post(`/api/sales-force/users/${from}/transfer`, {
            toUserId: transfer.value.globalTarget,
            clientGroupNumbers: transfer.value.clients.map((s) => s.clientGroupNumber),
        })
    }

    dlg.value.transfer = false
}
</script>

<style scoped>
.user-name {
    font-weight: 700;
    color: #0f172a;
}
.user-name--disabled {
    color: #ef4444;
}
@media (prefers-color-scheme: dark) {
    .user-name {
        color: #f8fafc;
    }
    .user-name--disabled {
        color: #f87171;
    }
}
</style>