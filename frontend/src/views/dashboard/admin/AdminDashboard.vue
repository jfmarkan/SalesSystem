<template>
	<div class="container-fluid admin-home">
		<!-- Tres paneles -->
		<div class="row mt-16">
			<div class="span-12 md-span-4 xl-span-3">
				<div class="glass card h-full no-strip">
					<UsersCompactList
						:users="users"
						:onlineMap="onlineMap"
						@create="openCreateUser"
						@block="onBlockUser"
						@kick="onKickUser"
						@edit="onEditUser"
					/>
				</div>
			</div>

			<div class="span-12 md-span-4 xl-span-6">
                <div class="glass card h-full no-strip">
                    <ClientsMiniList
                    :items="clientsPageItems"
                    :total="clientsTotal"
                    :page="clientsPage"
                    :perPage="clientsPerPage"
                    :query="clientsQuery"
                    @update:page="v => { clientsPage = v; loadClientsPaged() }"
                    @update:perPage="v => { clientsPerPage = v; clientsPage = 1; loadClientsPaged() }"
                    @update:query="v => { clientsQuery = v; clientsPage = 1; loadClientsPaged() }"
                    @create="goto('/admin/clients')"
                    @view="openClientQuick"
                    @edit="() => goto('/admin/clients')"
                    @delete="onDeleteClient"
                    />
                </div>
            </div>

			<div class="span-12 md-span-4 xl-span-3">
				<div class="glass card h-full no-strip">
					<ProfitCentersMiniList
						:items="pcsShort"
						:total="pcsTotal"
						:counts="pcClientCounts"
						@create="goto('/admin/profit-centers')"
						@view="(p) => goto('/admin/profit-centers')"
						@edit="(p) => goto('/admin/profit-centers')"
					/>
				</div>
			</div>
		</div>

		<!-- Create user -->
		<Dialog
			v-model:visible="createUserVisible"
			header="Neuer Benutzer"
			:modal="true"
			:style="{ width: '560px' }"
		>
			<div class="row">
				<div class="span-12 md-span-6">
					<FloatLabel
						><InputText id="fn" v-model="newUser.first_name" class="input" /><label
							for="fn"
							>Vorname</label
						></FloatLabel
					>
				</div>
				<div class="span-12 md-span-6">
					<FloatLabel
						><InputText id="ln" v-model="newUser.last_name" class="input" /><label
							for="ln"
							>Nachname</label
						></FloatLabel
					>
				</div>
				<div class="span-12 md-span-6">
					<FloatLabel
						><InputText id="un" v-model="newUser.username" class="input" /><label
							for="un"
							>Benutzername</label
						></FloatLabel
					>
				</div>
				<div class="span-12 md-span-6">
					<FloatLabel
						><InputText id="em" v-model="newUser.email" class="input" /><label for="em"
							>E-Mail</label
						></FloatLabel
					>
				</div>
				<div class="span-12 md-span-6">
					<FloatLabel
						><Password
							id="pw"
							v-model="newUser.password"
							toggleMask
							:feedback="false"
							class="w-100"
						/><label for="pw">Passwort</label></FloatLabel
					>
				</div>
				<div class="span-12 md-span-6">
					<Dropdown
						v-model="newUser.role_id"
						:options="roles"
						optionLabel="label"
						optionValue="value"
						placeholder="Rolle"
						class="w-100"
					/>
				</div>
			</div>
			<template #footer>
				<Button label="Abbrechen" severity="secondary" @click="createUserVisible = false" />
				<Button label="Erstellen" icon="pi pi-check" @click="onCreateUser" />
			</template>
		</Dialog>
        <ClientQuickView
  v-model="clientQuickVisible"
  :client="clientQuick"
  :pcs="clientQuickPcs"
  :loading="clientQuickLoading"
  @edit="() => { clientQuickVisible=false; goto('/admin/clients') }"
/>
	</div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Dropdown from 'primevue/dropdown'
import InputText from 'primevue/inputtext'
import FloatLabel from 'primevue/floatlabel'
import Password from 'primevue/password'
import { useToast } from 'primevue/usetoast'
import { useRouter } from 'vue-router'

import ClientQuickView from '@/components/admin/ClientQuickView.vue'
import UsersCompactList from '@/components/admin/UsersCompactList.vue'
import ClientsMiniList from '@/components/admin/ClientsMiniList.vue'
import ProfitCentersMiniList from '@/components/admin/ProfitCentersMiniList.vue'
import { useAdminApi } from '@/composables/useAdminApi'

const toast = useToast()
const router = useRouter()
const clientsPage = ref(1)
const clientsPerPage = ref(20)
const clientsQuery = ref('')
const clientsPageItems = ref([])

// cache para fallback client-side si el backend no pagina
const _clientsCache = ref([])

const {
  // Admin API
  getUsers,                // GET /api/settings/users
  getSessionsOnline,       // GET /api/settings/sessions/online
  getClients,              // GET /api/extra-quota/clients
  getProfitCenters,        // GET /api/analytics/pc/list
  getClientsSummary,       // GET /api/settings/clients/summary
  getPcsSummary,           // GET /api/settings/profit-centers/summary
  getKpis,                 // GET /api/settings/kpis/summary
  getProgress,             // GET /api/settings/progress/summary
  // acciones
  createUser,              // POST /api/settings/users
  blockUser,               // POST /api/settings/users/{id}/block
  kickUser,                // POST /api/settings/users/{id}/kick
  deleteClient             // DELETE /api/settings/clients/{clientGroup}
} = useAdminApi()

/* ---------- state ---------- */
const loading = ref(false)
const errorMsg = ref('')

const users = ref([])
const onlineMap = ref({})

const clients = ref([])
const profitCenters = ref([])

const clientsSummaryState = ref({ total: 0, active: 0, blocked: 0, pc_counts: {} })
const pcsSummaryState     = ref({ total: 0, active: 0, archived: 0, clients_per_pc: {} })

const kpisState = ref(null)
const progressState = ref(null)

const clientsTotal = ref(0)
const pcsTotal     = ref(0)

const clientsShort = ref([])
const pcsShort     = ref([])
const pcClientCounts = ref({})

/* ---------- utils ---------- */
function fyOf(d = new Date()) {
  const y = d.getFullYear(), m = d.getMonth() + 1
  return m >= 4 ? y : y - 1 // FY Apr–Mar
}
const activeFy = ref(fyOf())

function takeArray(res) {
  const d = res?.data ?? res
  if (Array.isArray(d)) return d
  if (!d || typeof d !== 'object') return []
  for (const k of ['items','list','rows','clients','profit_centers','pcs','data']) {
    if (Array.isArray(d[k])) return d[k]
  }
  return []
}

/* ---------- nav ---------- */
function goto(path) {
  router.push(path)
}

/* ---------- acciones usuarios ---------- */
const createUserVisible = ref(false)
const newUser = ref({
  first_name: '', last_name: '', username: '',
  email: '', password: '', role_id: 4
})
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

/* ---------- acciones clientes ---------- */
async function onDeleteClient(c) {
  try {
    await deleteClient(c.client_group_number)
    toast.add({ severity: 'success', summary: 'Gelöscht', detail: c.client_name, life: 1500 })
    await loadClientsAndPcs() // refresca listas
  } catch {
    toast.add({ severity: 'error', summary: 'Fehler', detail: 'Löschen fehlgeschlagen', life: 2200 })
  }
}

/* ---------- loaders ---------- */
async function loadUsers() {
  const [uRes, omRes] = await Promise.all([ getUsers(), getSessionsOnline() ])
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

  // clients
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

  // profit centers
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
  const [rK, rPr] = await Promise.allSettled([
    getKpis(),
    getProgress(activeFy.value)
  ])
  if (rK.status === 'fulfilled')  kpisState.value = rK.value?.data ?? null
  if (rPr.status === 'fulfilled') progressState.value = rPr.value?.data ?? null
}

async function load() {
  loading.value = true
  errorMsg.value = ''
  try {
    await loadUsers()
    await Promise.all([
      loadClientsAndPcs(),
      loadKpisAndProgress()
    ])
  } catch (e) {
    errorMsg.value = 'Fehler beim Laden'
  } finally {
    loading.value = false
  }
}

function parseClientsResponse(res) {
  const d = res?.data ?? res
  if (Array.isArray(d)) {
    return { items: d, total: d.length, serverPaged: false }
  }
  if (d && typeof d === 'object') {
    // formatos comunes: { data:[], meta:{total:...} } o { items:[], total:... }
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
  // intento server-side pagination
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
    // fallback: cache + paginado local
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

// quick view
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
    // usa tu ruta ya definida: /api/settings/clients/{clientGroup}/pcs
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

onMounted(async () => {
  await load()        // lo que ya tenés para users, kpis, etc.
  await loadClientsPaged()
})
</script>

<style scoped>
/* Schnellaktionen */
.sa-card {
	padding: 14px;
}
.sa-title {
	font-weight: 800;
	margin-bottom: 10px;
}
.sa-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 10px;
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
	transition:
		transform 0.08s ease,
		box-shadow 0.2s ease;
}
.chip i {
	font-size: 1.2rem;
}
.chip:hover {
	transform: translateY(-1px);
	box-shadow: 0 10px 24px rgba(0, 0, 0, 0.22);
}
.chip-benutzer {
	background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
}
:root:not(.dark) .chip-kunden {
	background: linear-gradient(60deg, #5073b8, #1098ad, #07b39b, #6fba82);
}
:root.dark .chip-kunden {
	background: linear-gradient(60deg, #f79533, #f37055, #ef4e7b, #a166ab);
}
.chip-pc {
	background: linear-gradient(60deg, #6fba82, #07b39b, #1098ad, #5073b8);
}

/* Cards */
.no-strip {
	padding-top: 10px;
}
.h-full {
	height: 100%;
	min-height: 360px;
}
</style>
