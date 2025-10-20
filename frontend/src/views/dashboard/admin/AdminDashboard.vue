<script setup>
import { onMounted, ref, computed } from 'vue'
import { useAdmin } from '@/stores/admin'
import { useTheme } from '@/composables/useTheme'

// PrimeVue components (unstyled)
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Dialog from 'primevue/dialog'
import Calendar from 'primevue/calendar'

const adm = useAdmin()
const { theme, toggle } = useTheme()

const tab = ref('overview')
const fy = ref(new Date().getFullYear())
const nextFy = computed(() => new Date().getFullYear() + 1)

const formClient = ref({ client_group_number: 0, client_name: '', classification_id: 1 })
const formUser = ref({ first_name: '', last_name: '', username: '', email: '', password: '', role_id: 1, disabled: false })
const rel = ref({ client_group_number: 0, profit_center_code: 0 })
const salesFrom = ref(new Date())

const showNewClient = ref(false)
const showNewUser = ref(false)

onMounted(async () => {
  if (!adm.loaded) await adm.loadAll()
  await adm.loadLogs()
  await adm.loadBudgetCases(fy.value)
})

const clientsSorted = computed(() => [...adm.clients].sort((a, b) => a.client_name.localeCompare(b.client_name, 'de')))
const usersSorted = computed(() => [...adm.users].sort((a, b) => a.username.localeCompare(b.username, 'de')))
const pcsOptions = computed(() => adm.profitCenters.map(p => ({ label: `${p.profit_center_code} — ${p.profit_center_name}`, value: p.profit_center_code })))
const clientsOptions = computed(() => clientsSorted.value.map(c => ({ label: c.client_name, value: c.client_group_number })))

</script>

<template>
  <div class="container">
    <!-- Navbar -->
    <div class="navbar card">
      <div class="flex items-center gap-12">
        <div class="brand">
          <img :src="logoSrc" alt="logo" />
          <strong>Admin</strong>
        </div>
        <div class="spacer"></div>
        <div class="tabs">
          <button :class="['tab',{active:tab==='overview'}]" @click="tab='overview'">Übersicht</button>
          <button :class="['tab',{active:tab==='clients'}]" @click="tab='clients'">Kunden</button>
          <button :class="['tab',{active:tab==='users'}]" @click="tab='users'">Benutzer</button>
          <button :class="['tab',{active:tab==='profit'}]" @click="tab='profit'">Profit Center</button>
          <button :class="['tab',{active:tab==='budget'}]" @click="tab='budget'">Budget</button>
          <button :class="['tab',{active:tab==='logs'}]" @click="tab='logs'">Protokoll</button>
          <button :class="['tab',{active:tab==='tools'}]" @click="tab='tools'">Tools</button>
        </div>
        <div class="spacer"></div>
        <label class="switch">
          <input type="checkbox" :checked="theme==='dark'" @change="toggle" />
          <span>{{ theme==='dark' ? 'Dark' : 'Light' }}</span>
        </label>
      </div>
    </div>

    <!-- Flags -->
    <div class="card">
      <div class="flex items-center gap-12">
        <label class="switch">
          <input type="checkbox" :checked="adm.flags.maintenance" @change="adm.setMaintenance($event.target.checked)" />
          <span>Wartungsmodus</span>
        </label>
        <label class="switch">
          <input type="checkbox" :checked="adm.flags.budget_period_active" @change="adm.setBudgetPeriod($event.target.checked)" />
          <span>Budgetperiode aktiv</span>
        </label>
      </div>
    </div>

    <!-- Overview -->
    <div v-if="tab==='overview'" class="row mt-12">
      <div class="card span-12 md-span-6 xl-span-4">
        <div class="card-header"><h3 class="card-title">Status</h3></div>
        <ul class="m-0">
          <li>Kunden: <strong>{{ adm.clients.length }}</strong></li>
          <li>Benutzer: <strong>{{ adm.users.length }}</strong> (online: {{ adm.users.filter(u=>u.online).length }})</li>
          <li>Profit Center: <strong>{{ adm.profitCenters.length }}</strong></li>
        </ul>
      </div>
      <div class="card span-12 md-span-6 xl-span-8">
        <div class="card-header"><h3 class="card-title">Schnellaktionen</h3></div>
        <div class="flex gap-12">
          <Button label="Neuer Kunde" @click="showNewClient=true" />
          <Button label="Neuer Benutzer" @click="showNewUser=true" />
          <Button label="Kunden öffnen" @click="tab='clients'" />
          <Button label="Logs öffnen" @click="tab='logs'" />
        </div>
      </div>
    </div>

    <!-- Clients -->
    <div v-if="tab==='clients'" class="card mt-12">
      <div class="card-header">
        <h3 class="card-title">Kunden</h3>
        <Button label="Neu" @click="showNewClient=true" />
      </div>

      <div class="row">
        <div class="span-12">
          <table class="table">
            <thead>
              <tr><th>Code</th><th>Name</th><th>Klassifikation</th><th>PCs</th><th>Benutzer</th><th class="text-right">Aktionen</th></tr>
            </thead>
            <tbody>
              <tr v-for="c in clientsSorted" :key="c.client_group_number">
                <td>{{ c.client_group_number }}</td>
                <td>{{ c.client_name }}</td>
                <td>{{ c.classification || c.classification_id }}</td>
                <td>{{ c.cpc_count }}</td>
                <td>{{ c.user_count }}</td>
                <td class="text-right">
                  <Button label="Details" @click="adm.loadClient(c.client_group_number)" />
                  <Button label="Löschen" severity="danger" @click="adm.deleteClient(c.client_group_number)" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="adm.clientDetail" class="span-12 mt-12">
          <div class="card">
            <div class="card-header">
              <h3 class="card-title">Details: {{ adm.clientDetail.client.client_name }}</h3>
            </div>
            <table class="table">
              <thead><tr><th>Relation-ID</th><th>PC-Code</th><th>Name</th><th>Aktion</th></tr></thead>
              <tbody>
                <tr v-for="r in adm.clientDetail.profit_centers" :key="r.id">
                  <td>{{ r.id }}</td>
                  <td>{{ r.profit_center_code }}</td>
                  <td>{{ r.profit_center_name }}</td>
                  <td><Button label="Löschen" severity="danger" @click="adm.unlinkClientPC(r.id).then(()=>adm.loadClient(adm.clientDetail.client.client_group_number))" /></td>
                </tr>
              </tbody>
            </table>
            <div class="flex gap-12 mt-12">
              <Dropdown class="input" :options="clientsOptions" optionLabel="label" optionValue="value" v-model="rel.client_group_number" placeholder="Kunde wählen…" />
              <Dropdown class="input" :options="pcsOptions" optionLabel="label" optionValue="value" v-model="rel.profit_center_code" placeholder="Profit Center wählen…" />
              <Button label="Relation erstellen" severity="success" :disabled="!rel.client_group_number || !rel.profit_center_code"
                      @click="adm.linkClientPC(rel).then(()=>{adm.loadClient(adm.clientDetail.client.client_group_number); rel.client_group_number=0; rel.profit_center_code=0;})" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Users -->
    <div v-if="tab==='users'" class="card mt-12">
      <div class="card-header">
        <h3 class="card-title">Benutzer</h3>
        <Button label="Neu" @click="showNewUser=true" />
      </div>
      <table class="table">
        <thead><tr><th>Benutzername</th><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Status</th><th class="text-right">Aktionen</th></tr></thead>
        <tbody>
          <tr v-for="u in usersSorted" :key="u.id">
            <td>{{ u.username }}</td>
            <td>{{ u.first_name }} {{ u.last_name }}</td>
            <td>{{ u.email }}</td>
            <td>{{ u.role || u.role_id }}</td>
            <td><span class="badge" :class="u.disabled ? 'blocked':'ok'">{{ u.disabled ? 'Gesperrt' : (u.online ? 'Online' : 'Offline') }}</span></td>
            <td class="text-right">
              <Button label="Sitzung beenden" @click="adm.kickUser(u.id)" />
              <Button :label="u.disabled ? 'Freigeben':'Sperren'" severity="warning" @click="adm.updateUser(u.id, { disabled: !u.disabled })" />
              <Button label="Löschen" severity="danger" @click="adm.deleteUser(u.id)" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Profit Centers -->
    <div v-if="tab==='profit'" class="card mt-12">
      <div class="card-header"><h3 class="card-title">Profit Center</h3></div>
      <table class="table">
        <thead><tr><th>Code</th><th>Name</th></tr></thead>
        <tbody>
          <tr v-for="p in adm.profitCenters" :key="p.profit_center_code">
            <td>{{ p.profit_center_code }}</td>
            <td>{{ p.profit_center_name }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Budget -->
    <div v-if="tab==='budget'" class="row mt-12">
      <div class="card span-12 xl-span-8">
        <div class="card-header">
          <h3 class="card-title">Budgetfälle {{ fy }}</h3>
          <div class="flex items-center gap-12">
            <InputText type="number" v-model.number="fy" style="width:140px" />
            <Button label="Neu laden" @click="adm.loadBudgetCases(fy)" />
          </div>
        </div>
        <table class="table">
          <thead><tr><th>CPC</th><th>Best Case</th><th>Worst Case</th></tr></thead>
          <tbody>
            <tr v-for="b in adm.budgetCases" :key="b.id || (b.client_profit_center_id+'-'+b.fiscal_year)">
              <td>{{ b.client_profit_center_id }}</td>
              <td><InputText type="number" :value="b.best_case" @change="adm.upsertBudgetCase({ client_profit_center_id: b.client_profit_center_id, fiscal_year: fy, best_case: +$event.target.value, worst_case: b.worst_case || 0 })" /></td>
              <td><InputText type="number" :value="b.worst_case" @change="adm.upsertBudgetCase({ client_profit_center_id: b.client_profit_center_id, fiscal_year: fy, best_case: b.best_case || 0, worst_case: +$event.target.value })" /></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card span-12 xl-span-4">
        <h3 class="card-title m-0">Nächstes Geschäftsjahr</h3>
        <p class="text-muted">FY: {{ nextFy }}</p>
        <Button label="Budget-Struktur erstellen" severity="success" @click="adm.createNextYearBudgets(nextFy)" />
        <hr class="div" />
        <Button label="Best/Worst Übersicht (Konsole)" @click="adm.clientsBestWorst(fy).then(r=>console.log('clients best/worst', r))" />
      </div>
    </div>

    <!-- Logs -->
    <div v-if="tab==='logs'" class="card mt-12">
      <div class="card-header">
        <h3 class="card-title">Protokoll</h3>
        <Button label="Testeintrag" @click="adm.addLog({ level:'INFO', message:'Test', context:{ by:'admin-ui' } })" />
      </div>
      <table class="table">
        <thead><tr><th>ID</th><th>Level</th><th>Nachricht</th><th>Datum</th></tr></thead>
        <tbody>
          <tr v-for="e in adm.logs" :key="e.id">
            <td>{{ e.id }}</td>
            <td>{{ e.event }}</td>
            <td>{{ e.description }}</td>
            <td>{{ new Date(e.created_at).toLocaleString('de-DE') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Tools -->
    <div v-if="tab==='tools'" class="card mt-12">
      <h3 class="card-title m-0">Tools</h3>
      <div class="flex gap-12 mt-12">
        <label style="min-width:240px">
          <span class="text-muted" style="display:block;margin-bottom:6px">Startdatum</span>
          <Calendar v-model="salesFrom" dateFormat="yy-mm-dd" showIcon :touchUI="true" />
        </label>
        <Button label="Verkäufe neu aufbauen" :disabled="!salesFrom" @click="adm.rebuildSales(new Date(salesFrom).toISOString().slice(0,10))" />
      </div>
    </div>

    <!-- Dialog: Neuer Kunde -->
    <Dialog v-model:visible="showNewClient" modal :draggable="false">
      <template #header><h3 class="card-title m-0">Neuer Kunde</h3></template>
      <div class="row">
        <label class="span-12 md-span-6"><span class="text-muted">Code</span><InputText type="number" v-model.number="formClient.client_group_number" /></label>
        <label class="span-12 md-span-6"><span class="text-muted">Name</span><InputText v-model="formClient.client_name" /></label>
        <label class="span-12 md-span-6"><span class="text-muted">Klassifikation-ID</span><InputText type="number" v-model.number="formClient.classification_id" /></label>
      </div>
      <template #footer>
        <Button label="Abbrechen" link @click="showNewClient=false" />
        <Button label="Erstellen" severity="success" @click="adm.createClient(formClient).then(()=>{ showNewClient=false; formClient={ client_group_number:0, client_name:'', classification_id:1 } })" />
      </template>
    </Dialog>

    <!-- Dialog: Neuer Benutzer -->
    <Dialog v-model:visible="showNewUser" modal :draggable="false">
      <template #header><h3 class="card-title m-0">Neuer Benutzer</h3></template>
      <div class="row">
        <label class="span-12 md-span-6"><span class="text-muted">Vorname</span><InputText v-model="formUser.first_name" /></label>
        <label class="span-12 md-span-6"><span class="text-muted">Nachname</span><InputText v-model="formUser.last_name" /></label>
        <label class="span-12 md-span-6"><span class="text-muted">Benutzername</span><InputText v-model="formUser.username" /></label>
        <label class="span-12 md-span-6"><span class="text-muted">E-Mail</span><InputText v-model="formUser.email" /></label>
        <label class="span-12 md-span-6"><span class="text-muted">Passwort</span><InputText type="password" v-model="formUser.password" /></label>
        <label class="span-12 md-span-6"><span class="text-muted">Rollen-ID</span><InputText type="number" v-model.number="formUser.role_id" /></label>
        <label class="span-12 md-span-6 flex items-center gap-12"><span class="text-muted">Gesperrt</span><input type="checkbox" v-model="formUser.disabled" /></label>
      </div>
      <template #footer>
        <Button label="Abbrechen" link @click="showNewUser=false" />
        <Button label="Erstellen" severity="success" @click="adm.createUser(formUser).then(()=>{ showNewUser=false; formUser={ first_name:'',last_name:'',username:'',email:'',password:'',role_id:1,disabled:false } })" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.admin {
	display: grid;
	gap: 16px;
}
.row {
	display: flex;
	gap: 8px;
	align-items: center;
	flex-wrap: wrap;
}
.row.space {
	justify-content: space-between;
}
.card {
	background: #111827;
	border: 1px solid #1f2937;
	border-radius: 12px;
	padding: 12px;
}
.tabs {
	display: flex;
	gap: 6px;
}
.tab {
	padding: 8px 10px;
	border-radius: 8px;
	border: 1px solid #1f2937;
	background: #1f2937;
	color: #e5e7eb;
}
.tab.active {
	background: #253044;
}
.table {
	width: 100%;
	border-collapse: collapse;
}
.table th,
.table td {
	border-bottom: 1px solid #1f2937;
	padding: 8px;
	text-align: left;
}
.btn {
	padding: 8px 10px;
	border-radius: 8px;
	border: 1px solid #1f2937;
	background: #1f2937;
	color: #e5e7eb;
	cursor: pointer;
}
.btn:hover {
	background: #253044;
}
.btn.primary {
	background: #0b5;
	border-color: #0a4;
	color: #041;
}
.btn.warn {
	background: #3a2;
	border-color: #2a1;
	color: #041;
}
.btn.danger {
	background: #7a1e1e;
	border-color: #8b1d1d;
	color: #fee2e2;
}
.btn.ghost {
	background: transparent;
}
.input {
	width: 100%;
	padding: 8px;
	border-radius: 8px;
	border: 1px solid #1f2937;
	background: #0b1020;
	color: #e5e7eb;
}
.switch {
	display: inline-flex;
	gap: 6px;
	align-items: center;
}
.badge {
	padding: 2px 8px;
	border-radius: 999px;
	font-size: 12px;
	border: 1px solid #1f2937;
}
.badge.ok {
	background: #0f2a1b;
	color: #a7f3d0;
	border-color: #064e3b;
}
.badge.blocked {
	background: #3b0f10;
	color: #fca5a5;
	border-color: #7f1d1d;
}
.grid {
	display: grid;
	gap: 12px;
}
.grid.two {
	grid-template-columns: 1fr 1fr;
}
.modal {
	position: fixed;
	inset: 0;
	background: rgba(0, 0, 0, 0.6);
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px;
}
.modal .box {
	width: 100%;
	max-width: 640px;
}
.actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 12px;
}
.inner {
	background: #0f172a;
}
.lbl {
	display: block;
	font-size: 12px;
	color: #9ca3af;
}
</style>
