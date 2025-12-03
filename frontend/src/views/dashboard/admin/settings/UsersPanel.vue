<template>
	<Toast />
	<div class="users-page">
		<div class="grid2-10">
			<!-- 2: Listen / Filter -->
			<aside class="pane left">
				<div class="pane-head">
					<div class="search">
						<IconField>
							<InputIcon class="pi pi-search" />
							<InputText v-model="q" placeholder="Suche" />
						</IconField>
					</div>
					<Button icon="pi pi-plus" class="btn-new" @click="dlgCreate = true" />
				</div>

				<!-- Liste nimmt die volle Höhe ein -->
				<div class="list-wrap">
					<Listbox
						v-model="selectedId"
						:options="usersFiltered"
						optionLabel="__label"
						optionValue="id"
						class="w-full h-full"
						:listStyle="{ height: '100%' }"
					>
						<template #option="{ option }">
							<div class="lb-row">
								<Tag
									class="state-dot"
									:icon="option.disabled ? 'pi pi-times' : 'pi pi-check'"
									:severity="option.disabled ? 'danger' : 'success'"
								/>
								<span class="lb-name">{{ option.__label }}</span>
							</div>
						</template>
					</Listbox>
				</div>
			</aside>

			<!-- 10: Workbench -->
			<section class="right">
				<template v-if="selected">
					<!-- Titelkarte -->
					<Card class="title-card">
						<template #content>
							<div class="title-head">
								<div class="th-left">
									<Avatar
										:image="selected.avatar_url || selected.photo_url || null"
										:label="avatarInitials(selected)"
										shape="circle"
										size="large"
										class="avt"
									/>
									<div class="id-block">
										<div class="name-row">
											<span class="name">{{ fullName(selected) || selected.email }}</span>
											<Tag
												class="state-dot name-dot"
												:icon="selected.disabled ? 'pi pi-times' : 'pi pi-check'"
												:severity="selected.disabled ? 'danger' : 'success'"
											/>
										</div>

										<div class="meta-chips">
											<Tag
												v-for="(c, ci) in (selected.companies || [])"
												:key="'c' + ci"
												class="tag-slim tag-company"
												:value="c.name || c"
											/>
											<Tag
												v-for="tid in selected.teamIds || []"
												:key="'t' + tid"
												class="tag-slim tag-team"
												:value="teamName(tid)"
											/>
											<Tag
												v-if="roleDisplay(selected)"
												class="tag-slim tag-role"
												:value="roleDisplay(selected)"
											/>
										</div>

										<div class="email">{{ selected.email }}</div>
									</div>
								</div>

								<div class="th-right">
									<SpeedDial
										:model="[
											{
												label: 'Aktivieren',
												icon: 'pi pi-check',
												class: 'p-button-rounded p-button-success',
												command: () => toggleBlock(selected, false),
											},
											{
												label: 'Sperren',
												icon: 'pi pi-times',
												class: 'p-button-rounded p-button-danger',
												command: () => toggleBlock(selected, true),
											},
										]"
										direction="left"
										type="linear"
										:showIcon="selected?.disabled ? 'pi pi-times' : 'pi pi-check'"
										hideIcon="pi pi-times"
										buttonClass="p-button-rounded p-button-sm p-button-secondary"
										appendTo="body"
										v-tooltip.bottom="'Sperren/Aktivieren'"
									/>
									<Button
										icon="pi pi-sliders-h"
										rounded
										text
										size="small"
										@click="openRoleDlg"
										v-tooltip.bottom="'Rolle'"
									/>
									<Button
										icon="pi pi-sitemap"
										rounded
										text
										size="small"
										@click="openTeamsDlg"
										v-tooltip.bottom="'Teams'"
									/>
									<Button
										icon="pi pi-sync"
										rounded
										text
										size="small"
										@click="openTransferPanel"
										v-tooltip.bottom="'Kundenübertragungen'"
									/>
									<Button
										icon="pi pi-percentage"
										rounded
										text
										size="small"
										@click="openExtraPanel"
										v-tooltip.bottom="'Extra-Quoten'"
									/>
								</div>
							</div>
						</template>
					</Card>

					<!-- ================= TRANSFER PANEL ================= -->
					<Card v-if="selected && ws === 'transfer'" class="content-card">
						<template #title>Kunden übertragen</template>

						<template #content>
							<div class="transfer-toolbar">
								<div class="tb-left">
									<IconField class="tb-search">
										<InputIcon class="pi pi-search" />
										<InputText v-model="transfer.search" placeholder="Suche" />
									</IconField>

									<div class="tb-mode">
										<label class="tb-label">Modus</label>
										<SelectButton
											v-model="transfer.mode"
											:options="modeOptions"
											optionLabel="label"
											optionValue="value"
										/>
									</div>

									<div class="tb-target" v-if="transfer.mode !== 'perRow'">
										<label class="tb-label" for="target">Zielbenutzer</label>
										<Select
											id="target"
											v-model="transfer.globalTarget"
											:options="targetsOpts"
											optionLabel="name"
											optionValue="id"
											placeholder="Auswählen"
											class="w-12rem"
										/>
									</div>
								</div>

								<div class="tb-right">
									<Button label="Abbrechen" text severity="secondary" @click="ws = 'overview'" />
									<Button
										label="Bestätigen"
										icon="pi pi-check"
										:disabled="!canConfirmTransfer"
										@click.prevent.stop="confirmTransfer"
									/>
								</div>
							</div>

							<div class="transfer-hint">
								<Message v-if="transfer.mode === 'all'" severity="info" :closable="false">
									Alle Kunden (alle Profitcenter) werden vollständig übertragen.
								</Message>
								<Message v-else-if="transfer.mode === 'pick'" severity="info" :closable="false">
									Nur ausgewählte Kunden werden übertragen.
								</Message>
								<Message v-else severity="info" :closable="false">
									Pro Zeile Kunden einem Zielbenutzer zuordnen.
								</Message>
							</div>

							<DataTable
								:value="filteredClients"
								dataKey="clientGroupNumber"
								v-model:selection="transfer.selection"
								:selectionMode="transfer.mode === 'pick' ? 'checkbox' : null"
								:loading="transfer.loading"
								:rows="10"
								paginator
								:rowHover="true"
								responsiveLayout="scroll"
								:emptyMessage="'Keine Kunden'"
							>
								<Column
									v-if="transfer.mode === 'pick'"
									selectionMode="multiple"
									headerStyle="width:3rem"
								/>

								<Column header="Klassifikation" style="width: 120px">
									<template #body="{ data }">
										<span v-if="data.classLetter" class="class-chip">
											{{ data.classLetter }}
										</span>
										<span v-else>-</span>
									</template>
								</Column>

								<Column
									field="clientGroupNumber"
									header="Kundenartikelklassifikation"
									style="width: 220px"
								/>

								<Column field="clientName" header="Kundenname" />

								<Column header="Profitcenter" style="width: 260px">
									<template #body="{ data }">
										<span v-if="Array.isArray(data.profitCenters) && data.profitCenters.length">
											{{ data.profitCenters.join(', ') }}
										</span>
										<span v-else>-</span>
									</template>
								</Column>

								<Column
									v-if="transfer.mode === 'perRow'"
									header="Zielbenutzer"
									style="width: 260px"
								>
									<template #body="{ data }">
										<Select
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
						</template>
					</Card>

					<!-- ============= EXTRA QUOTAS PANEL ============= -->
					<Card v-if="selected && ws === 'extra'" class="content-card">
						<template #title>
							<div class="xq-title-bar">
								<span>Extra-Quoten</span>
								<div class="xq-title-actions">
									<Button
										icon="pi pi-chevron-left"
										text
										rounded
										size="small"
										@click="xqPrevYear"
										v-tooltip.bottom="'Vorheriges Geschäftsjahr'"
									/>
									<span class="xq-fy-label">{{ xqFyLabel }}</span>
									<Button
										icon="pi pi-chevron-right"
										text
										rounded
										size="small"
										@click="xqNextYear"
										v-tooltip.bottom="'Nächstes Geschäftsjahr'"
									/>
									<Button
										:label="xqSaveAllLabel"
										icon="pi pi-save"
										size="small"
										class="xq-save-all-btn"
										:disabled="xqDirtyCount === 0 || xq.loading"
										@click="xqSaveAll"
									/>
								</div>
							</div>
						</template>

						<template #content>
							<div class="xq-wrap">
								<div class="xq-bar">
									<span class="xq-spacer"></span>
								</div>

								<div v-if="xq.error" class="xq-err">{{ xq.error }}</div>

								<div class="xq-table-wrap" :class="{ loading: xq.loading }">
									<table class="xq-tbl">
										<thead>
											<tr>
												<th>Profitcenter</th>
												<th class="tc" style="width:220px">Volumen</th>
												<th class="tc" style="width:80px"></th>
											</tr>
										</thead>
										<tbody>
											<tr v-for="r in xq.rows" :key="r._key">
												<td>{{ r.profit_center_name }}</td>
												<td class="tc">
													<div class="xq-vol">
														<input
															type="text"
															:value="formatThousandInput(r.volume)"
															@input="onVolumeInput($event, r)"
														/>
														<small
															v-if="xq.original[r._key] !== undefined && xq.original[r._key] !== r.volume"
															class="old"
														>
															alt: {{ formatThousand(xq.original[r._key]) }}
														</small>
													</div>
												</td>
												<td class="tc">
													<Button
														icon="pi pi-save"
														size="small"
														class="xq-save-btn"
														:class="{
															'xq-save-btn--active': xqIsDirty(r._key) && !xq.loading,
														}"
														:disabled="xq.loading || !xqIsDirty(r._key)"
														@click="xqSaveOne(r)"
													/>
												</td>
											</tr>
											<tr v-if="!xq.loading && xq.rows.length === 0">
												<td colspan="3" class="empty">Keine Quoten für diesen Benutzer.</td>
											</tr>
										</tbody>
									</table>

									<div v-if="xq.loading" class="xq-overlay">
										<i class="pi pi-spin pi-spinner" />
										<div>Lädt…</div>
									</div>
								</div>
							</div>
						</template>
					</Card>
				</template>

				<!-- ===================== DIALOG: BENUTZER ERSTELLEN ===================== -->
				<Dialog
					v-model:visible="dlgCreate"
					modal
					:draggable="false"
					:breakpoints="{ '960px': '70vw', '640px': '92vw' }"
					:style="{ width: '620px' }"
					header="Neuen Benutzer erstellen"
					appendTo="body"
				>
					<div class="grid form-grid dialog-body">
						<div class="col-12 md:col-6">
							<div class="form-field">
								<label for="fn" class="field-label">Vorname</label>
								<InputText id="fn" v-model.trim="newUser.first_name" class="w-full" />
							</div>
						</div>
						<div class="col-12 md:col-6">
							<div class="form-field">
								<label for="ln" class="field-label">Nachname</label>
								<InputText id="ln" v-model.trim="newUser.last_name" class="w-full" />
							</div>
						</div>
						<div class="col-12 md:col-6">
							<div class="form-field">
								<label for="un" class="field-label">Benutzername</label>
								<InputText id="un" v-model.trim="newUser.username" class="w-full" />
							</div>
						</div>
						<div class="col-12 md:col-6">
							<div class="form-field">
								<label for="em" class="field-label">E-Mail</label>
								<InputText id="em" v-model.trim="newUser.email" class="w-full" />
							</div>
						</div>
						<div class="col-12 md:col-6">
							<div class="form-field">
								<label for="pw" class="field-label">Passwort</label>
								<Password
									id="pw"
									v-model="newUser.password"
									toggleMask
									:feedback="false"
									class="w-full"
								/>
							</div>
						</div>

						<div class="col-12 md:col-6">
							<div class="row-inline">
								<div class="flex-1">
									<div class="form-field">
										<label for="role_id" class="field-label">Rolle</label>
										<Select
											id="role_id"
											v-model="newUser.role_id"
											:options="rolesOptions"
											optionLabel="label"
											optionValue="value"
											class="w-full"
											placeholder="Rolle auswählen"
										/>
									</div>
								</div>
								<Button
									icon="pi pi-plus"
									rounded
									text
									class="ml-2"
									v-tooltip.bottom="'Neue Rolle'"
									@click="dlgCreateRole = true"
								/>
							</div>
						</div>

						<div class="col-12" v-if="createError">
							<Message severity="error" :closable="false">{{ createError }}</Message>
						</div>
					</div>

					<template #footer>
						<div class="dialog-footer">
							<Button
								label="Abbrechen"
								severity="secondary"
								@click="onCancelCreate"
								:disabled="createLoading"
							/>
							<Button
								label="Erstellen"
								icon="pi pi-check"
								:loading="createLoading"
								:disabled="!isCreateValid || createLoading"
								@click="onCreateUser"
							/>
						</div>
					</template>
				</Dialog>

				<!-- DIALOG: Rolle ändern -->
				<Dialog
					v-model:visible="dlgRole"
					modal
					header="Rolle ändern"
					:style="{ width: '28rem' }"
					appendTo="body"
				>
					<div class="dialog-body">
						<div class="row-inline">
							<div class="flex-1">
								<div class="form-field">
									<label for="role" class="field-label">Rolle</label>
									<Select
										id="role"
										v-model="roleLocal"
										:options="rolesOptions"
										optionLabel="label"
										optionValue="value"
										class="w-full"
										placeholder="Rolle auswählen"
									/>
								</div>
							</div>
							<Button
								icon="pi pi-plus"
								rounded
								text
								class="ml-2"
								v-tooltip.bottom="'Neue Rolle'"
								@click="dlgCreateRole = true"
							/>
						</div>
					</div>
					<template #footer>
						<div class="dialog-footer">
							<Button label="Abbrechen" text severity="secondary" @click="dlgRole = false" />
							<Button
								label="Speichern"
								icon="pi pi-check"
								severity="warning"
								:disabled="!roleLocal"
								@click="confirmRole"
							/>
						</div>
					</template>
				</Dialog>

				<!-- DIALOG: Teams ändern -->
				<Dialog
					v-model:visible="dlgTeams"
					modal
					header="Teams ändern"
					:style="{ width: '32rem' }"
					appendTo="body"
				>
					<div class="dialog-body">
						<div class="row-inline mb-2">
							<div class="flex-1">
								<div class="form-field">
									<label for="teams" class="field-label">Teams</label>
									<MultiSelect
										id="teams"
										v-model="teamsLocal"
										:options="teams"
										optionLabel="name"
										optionValue="id"
										display="chip"
										class="w-full"
										placeholder="Teams auswählen"
									/>
								</div>
							</div>
							<Button
								icon="pi pi-plus"
								rounded
								text
								class="ml-2"
								v-tooltip.bottom="'Neues Team'"
								@click="dlgCreateTeam = true"
							/>
						</div>
						<Message severity="warn" :closable="false">
							Die gesamte Team-Zuordnung wird ersetzt.
						</Message>
					</div>
					<template #footer>
						<div class="dialog-footer">
							<Button label="Abbrechen" text severity="secondary" @click="dlgTeams = false" />
							<Button
								label="Speichern"
								icon="pi pi-check"
								:loading="savingTeams"
								@click="confirmTeams"
							/>
						</div>
					</template>
				</Dialog>

				<!-- DIALOG: Neue Rolle -->
				<Dialog
					v-model:visible="dlgCreateRole"
					modal
					header="Neue Rolle"
					:style="{ width: '24rem' }"
					appendTo="body"
				>
					<div class="dialog-body">
						<div class="form-field mb-3">
							<label for="rname" class="field-label">Rollenname</label>
							<InputText id="rname" v-model.trim="newRoleName" class="w-full" />
						</div>
						<Message v-if="createRoleError" severity="error" :closable="false">
							{{ createRoleError }}
						</Message>
					</div>
					<template #footer>
						<div class="dialog-footer">
							<Button label="Abbrechen" text severity="secondary" @click="closeCreateRole" />
							<Button
								label="Erstellen"
								icon="pi pi-check"
								:disabled="!newRoleName"
								@click="createRole"
							/>
						</div>
					</template>
				</Dialog>

				<!-- DIALOG: Neues Team -->
				<Dialog
					v-model:visible="dlgCreateTeam"
					modal
					header="Neues Team"
					:style="{ width: '24rem' }"
					appendTo="body"
				>
					<div class="dialog-body">
						<div class="form-field mb-3">
							<label for="tname" class="field-label">Teamname</label>
							<InputText id="tname" v-model.trim="newTeamName" class="w-full" />
						</div>
						<Message v-if="createTeamError" severity="error" :closable="false">
							{{ createTeamError }}
						</Message>
					</div>
					<template #footer>
						<div class="dialog-footer">
							<Button label="Abbrechen" text severity="secondary" @click="closeCreateTeam" />
							<Button
								label="Erstellen"
								icon="pi pi-check"
								:disabled="!newTeamName"
								@click="createTeam"
							/>
						</div>
					</template>
				</Dialog>
			</section>
		</div>
	</div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/plugins/axios'

import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Listbox from 'primevue/listbox'
import Tag from 'primevue/tag'
import SpeedDial from 'primevue/speeddial'
import Button from 'primevue/button'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import Card from 'primevue/card'
import Tooltip from 'primevue/tooltip'
import Dialog from 'primevue/dialog'
import Password from 'primevue/password'
import Message from 'primevue/message'
import Avatar from 'primevue/avatar'
import SelectButton from 'primevue/selectbutton'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

const vTooltip = Tooltip

const users = ref([])
const allUsers = ref([])
const rolesRaw = ref([])
const teams = ref([])

const q = ref('')
const selectedId = ref(null)
const ws = ref('overview')

const roleLocal = ref(null)
const teamsLocal = ref([])
const savingTeams = ref(false)

const dlgCreate = ref(false)
const createLoading = ref(false)
const createError = ref('')
const newUser = ref({
	first_name: '',
	last_name: '',
	username: '',
	email: '',
	password: '',
	role_id: null,
})

const dlgRole = ref(false)
const dlgTeams = ref(false)
const dlgCreateRole = ref(false)
const dlgCreateTeam = ref(false)
const newRoleName = ref('')
const newTeamName = ref('')
const createRoleError = ref('')
const createTeamError = ref('')

const transfer = ref({
	loading: false,
	mode: 'all',
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

onMounted(async () => {
	const [u, r, t, au] = await Promise.all([
		api.get('/api/settings/users'),
		api.get('/api/settings/roles'),
		api.get('/api/settings/teams'),
		api.get('/api/settings/users'),
	])

	const normalizeUser = (u) => {
		const name =
			u?.name?.trim?.() ||
			`${u?.first_name ?? ''} ${u?.last_name ?? ''}`.trim()

		let teamIds = []
		if (Array.isArray(u.teamIds)) {
			teamIds = [...u.teamIds]
		} else if (Array.isArray(u.teams)) {
			teamIds = u.teams
				.map((tt) => tt.id ?? tt.team_id)
				.filter(Boolean)
		} else if (Array.isArray(u.team_ids)) {
			teamIds = [...u.team_ids]
		}

		return {
			...u,
			name,
			teamIds,
		}
	}

	users.value = (u.data || []).map(normalizeUser)
	rolesRaw.value = Array.isArray(r.data) ? r.data : []
	teams.value = t.data || []
	allUsers.value = (au.data || []).map(normalizeUser)
})

const selected = computed(() =>
	users.value.find((x) => x.id === selectedId.value),
)

const usersFiltered = computed(() => {
	const s = q.value.trim().toLowerCase()
	const list = users.value.map((u) => ({
		...u,
		__label: fullName(u) || u.email || `User #${u.id}`,
	}))
	if (!s) return list
	return list.filter(
		(u) =>
			u.__label.toLowerCase().includes(s) ||
			String(u.email || '')
				.toLowerCase()
				.includes(s) ||
			(Array.isArray(u.roles) &&
				u.roles.some((r) =>
					String(r).toLowerCase().includes(s),
				)),
	)
})

const rolesOptions = computed(() =>
	(rolesRaw.value || []).map((r) => {
		if (typeof r === 'string') return { label: r, value: r }
		return { label: r.name ?? String(r.id), value: r.id ?? r.name }
	}),
)

const targetsOpts = computed(() =>
	(allUsers.value || [])
		.filter((x) => x.id !== selected.value?.id)
		.map((x) => ({
			id: x.id,
			name: fullName(x) || x.email || `User #${x.id}`,
		})),
)

const filteredClients = computed(() => {
	const s = (transfer.value.search || '').toLowerCase()
	return transfer.value.clients.filter((r) => {
		const groupStr = String(r.clientGroupNumber || '').toLowerCase()
		const nameStr = (r.clientName || '').toLowerCase()
		const classStr = (r.classLetter || '').toLowerCase()
		const pcsStr = Array.isArray(r.profitCenters)
			? r.profitCenters.join(',').toLowerCase()
			: ''
		return (
			groupStr.includes(s) ||
			nameStr.includes(s) ||
			classStr.includes(s) ||
			pcsStr.includes(s)
		)
	})
})

const canConfirmTransfer = computed(() => {
	if (!selected.value?.id) return false
	if (transfer.value.mode === 'all') return !!transfer.value.globalTarget
	if (transfer.value.mode === 'pick')
		return (
			!!transfer.value.globalTarget &&
			transfer.value.selection.length > 0
		)
	if (transfer.value.mode === 'perRow')
		return (
			filteredClients.value.length > 0 &&
			filteredClients.value.every(
				(r) => !!transfer.value.rowTargets[r.clientGroupNumber],
			)
		)
	return false
})

function fullName(u) {
	return (
		u?.name?.trim?.() ||
		`${u?.first_name ?? ''} ${u?.last_name ?? ''}`.trim()
	)
}
function avatarInitials(u) {
	const n = fullName(u) || u?.email || ''
	const parts = n.trim().split(/\s+/).slice(0, 2)
	return parts
		.map((p) => p[0]?.toUpperCase?.() || '')
		.join('')
}
function teamName(id) {
	return (
		teams.value.find((t) => t.id === id)?.name || `#${id}`
	)
}
function roleDisplay(u) {
	if (Array.isArray(u.roles) && u.roles[0]) return u.roles[0]
	const opt = rolesOptions.value.find((o) => o.value === u.role_id)
	return opt?.label || null
}

/** Formatear número con . como miles (alemán) para mostrar */
function formatThousand(value) {
	const n = Number(value)
	if (!Number.isFinite(n)) return ''
	return n.toLocaleString('de-DE', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 0,
	})
}

/** Formato usado en el input (texto) */
function formatThousandInput(value) {
	if (value === null || value === undefined || value === '') return ''
	const n = Number(value)
	if (!Number.isFinite(n)) return ''
	return n.toLocaleString('de-DE', {
		minimumFractionDigits: 0,
		maximumFractionDigits: 0,
	})
}

/** Manejar input de volumen: string -> número interno -> re-formatear */
function onVolumeInput(event, row) {
	const raw = event.target.value

	// Normalizar: quitar puntos de miles, cambiar coma por punto
	const normalized = raw.replace(/\./g, '').replace(',', '.')
	const n = Number(normalized)

	// Internamente siempre número
	row.volume = Number.isFinite(n) ? n : 0

	// Reescribir input con formato miles
	event.target.value = raw === '' ? '' : formatThousandInput(row.volume)

	// Marcar dirty
	xqMarkDirty(row)
}

watch(selected, (u) => {
	ws.value = 'overview'
	if (!u) return
	roleLocal.value =
		u.role_id || (Array.isArray(u.roles) ? null : null)
	teamsLocal.value = Array.isArray(u.teamIds)
		? [...u.teamIds]
		: []
})

function openRoleDlg() {
	if (selected.value) dlgRole.value = true
}
function openTeamsDlg() {
	if (selected.value) dlgTeams.value = true
}
function openTransferPanel() {
	if (!selected.value) return
	ws.value = 'transfer'
	loadClientsForUser()
}
function openExtraPanel() {
	if (!selected.value) return
	ws.value = 'extra'
}

const emailOk = (e) =>
	/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(e || '').trim())
const isCreateValid = computed(() => {
	const u = newUser.value
	return (
		u.first_name?.trim() &&
		u.last_name?.trim() &&
		u.username?.trim() &&
		emailOk(u.email) &&
		(u.password?.length ?? 0) >= 6 &&
		u.role_id
	)
})

function resetCreateForm() {
	newUser.value = {
		first_name: '',
		last_name: '',
		username: '',
		email: '',
		password: '',
		role_id: null,
	}
	createError.value = ''
}
function onCancelCreate() {
	dlgCreate.value = false
	createLoading.value = false
	createError.value = ''
}
async function onCreateUser() {
	if (!isCreateValid.value || createLoading.value) return
	createLoading.value = true
	createError.value = ''
	try {
		const roleVal = newUser.value.role_id
		const isId =
			typeof roleVal === 'number' ||
			/^[0-9]+$/.test(String(roleVal))

		const payload = {
			first_name: newUser.value.first_name.trim(),
			last_name: newUser.value.last_name.trim(),
			username: newUser.value.username.trim(),
			email: newUser.value.email.trim(),
			password: newUser.value.password,
			...(isId ? { role_id: Number(roleVal) } : {}),
		}
		const { data } = await api.post(
			'/api/settings/users',
			payload,
		)
		const created = data?.user || data

		if (!isId && created?.id && typeof roleVal === 'string') {
			await api.patch(
				`/api/settings/users/${created.id}/roles`,
				{ role: roleVal },
			)
			created.roles = [roleVal]
		}

		const merged = {
			id:
				created?.id ??
				(Math.max(
					0,
					...users.value.map((u) => u.id || 0),
				) +
					1),
			first_name:
				created?.first_name ?? newUser.value.first_name,
			last_name:
				created?.last_name ?? newUser.value.last_name,
			username:
				created?.username ?? newUser.value.username,
			email: created?.email ?? newUser.value.email,
			roles: created?.roles ?? (isId ? [] : [roleVal]),
			disabled: !!created?.disabled,
			role_id:
				created?.role_id ??
				(isId ? Number(roleVal) : null),
			teamIds: created?.teamIds || [],
			companies: created?.companies || [],
		}
		users.value = [merged, ...users.value]
		selectedId.value = merged.id
		dlgCreate.value = false
		resetCreateForm()
	} catch (e) {
		createError.value =
			e?.response?.data?.message ||
			e?.message ||
			'Fehler beim Erstellen'
	} finally {
		createLoading.value = false
	}
}

async function toggleBlock(u, disabled) {
	const { data } = await api.patch(
		`/api/settings/users/${u.id}/block`,
		{ disabled },
	)
	const i = users.value.findIndex((x) => x.id === u.id)
	if (i !== -1) users.value[i] = { ...users.value[i], ...data }
}

async function confirmRole() {
	if (!selected.value || !roleLocal.value) return
	const roleVal = roleLocal.value
	const body =
		typeof roleVal === 'number' ||
		/^[0-9]+$/.test(String(roleVal))
			? { role_id: Number(roleVal) }
			: { role: String(roleVal) }
	const { data } = await api.patch(
		`/api/settings/users/${selected.value.id}/roles`,
		body,
	)
	const i = users.value.findIndex(
		(x) => x.id === selected.value.id,
	)
	if (i !== -1) users.value[i] = { ...users.value[i], ...data }
	dlgRole.value = false
}

async function confirmTeams() {
	if (!selected.value) return
	try {
		savingTeams.value = true
		const { data } = await api.patch(
			`/api/settings/users/${selected.value.id}/teams`,
			{
				teamIds: teamsLocal.value,
			},
		)
		const i = users.value.findIndex(
			(x) => x.id === selected.value.id,
		)
		if (i !== -1)
			users.value[i] = {
				...users.value[i],
				teamIds: data?.teamIds || [...teamsLocal.value],
			}
		dlgTeams.value = false
	} finally {
		savingTeams.value = false
	}
}

function closeCreateRole() {
	dlgCreateRole.value = false
	newRoleName.value = ''
	createRoleError.value = ''
}
async function createRole() {
	if (!newRoleName.value) return
	try {
		const { data } = await api.post('/api/settings/roles', {
			name: newRoleName.value,
		})
		if (data?.id && data?.name) {
			rolesRaw.value = [...rolesRaw.value, data]
			roleLocal.value = data.id
			if (dlgCreate.value) newUser.value.role_id = data.id
		}
		closeCreateRole()
	} catch (e) {
		createRoleError.value =
			e?.response?.data?.message ||
			e?.message ||
			'Fehler beim Erstellen'
	}
}

function closeCreateTeam() {
	dlgCreateTeam.value = false
	newTeamName.value = ''
	createTeamError.value = ''
}
async function createTeam() {
	if (!newTeamName.value) return
	try {
		const { data } = await api.post('/api/settings/teams', {
			name: newTeamName.value,
		})
		if (data?.id && data?.name) {
			teams.value = [...teams.value, data]
			teamsLocal.value = [
				...new Set([...(teamsLocal.value || []), data.id]),
			]
		}
		closeCreateTeam()
	} catch (e) {
		createTeamError.value =
			e?.response?.data?.message ||
			e?.message ||
			'Fehler beim Erstellen'
	}
}

async function loadClientsForUser() {
	if (!selected.value) return
	transfer.value.loading = true
	try {
		const userId = selected.value.id

		const { data } = await api.get(
			'/api/settings/user-clients',
			{
				params: { userId },
			},
		)

		const raw = Array.isArray(data) ? data : []

		transfer.value.clients = raw.map((r) => {
			const cgn =
				r.clientGroupNumber ??
				r.client_group_number ??
				r.cgn ??
				null

			const name =
				r.clientName ??
				r.client_name ??
				r.name ??
				''

			const classId =
				r.classification_id ??
				r.classificationId ??
				r.classification ??
				null
			const classLetter =
				r.class_letter ??
				r.classLetter ??
				r.classification_letter ??
				(typeof classId === 'number'
					? {
							1: 'A',
							2: 'B',
							3: 'C',
							4: 'D',
							5: 'X',
							6: 'PA',
							7: 'PB',
					  }[classId] || null
					: null)

			const rawPcs =
				r.profitCenters ??
				r.profit_centers ??
				r.client_profit_centers ??
				r.pc_codes ??
				r.pcs ??
				[]

			let pcsNorm = []
			if (Array.isArray(rawPcs)) {
				pcsNorm = rawPcs
					.map((pc) => {
						if (pc == null) return null
						if (
							typeof pc === 'string' ||
							typeof pc === 'number'
						)
							return String(pc)
						return (
							pc.profit_center_code ||
							pc.pc_code ||
							pc.code ||
							pc.name ||
							pc.label ||
							null
						)
					})
					.filter(Boolean)
			}

			const cnt =
				r.count ??
				r.cpc_count ??
				(Array.isArray(pcsNorm)
					? pcsNorm.length
					: 0)

			return {
				...r,
				clientGroupNumber: cgn,
				clientName: name,
				classLetter,
				profitCenters: pcsNorm,
				count: cnt,
			}
		})

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
	const from = selected.value.id
	if (transfer.value.mode === 'perRow') {
		const byUser = new Map()
		filteredClients.value.forEach((row) => {
			const to =
				transfer.value.rowTargets[
					row.clientGroupNumber
				]
			if (!to) return
			if (!byUser.has(to)) byUser.set(to, [])
			byUser.get(to).push(row.clientGroupNumber)
		})
		for (const [toUserId, clientGroupNumbers] of byUser.entries()) {
			await api.post(
				`/api/settings/users/${from}/transfer`,
				{ toUserId, clientGroupNumbers },
			)
		}
	} else if (transfer.value.mode === 'pick') {
		await api.post(
			`/api/settings/users/${from}/transfer`,
			{
				toUserId: transfer.value.globalTarget,
				clientGroupNumbers: transfer.value.selection.map(
					(s) => s.clientGroupNumber,
				),
			},
		)
	} else {
		await api.post(
			`/api/settings/users/${from}/transfer`,
			{
				toUserId: transfer.value.globalTarget,
				clientGroupNumbers: transfer.value.clients.map(
					(s) => s.clientGroupNumber,
				),
			},
		)
	}
	ws.value = 'overview'
}

/* ===================== EXTRA-QUOTEN ===================== */
function fyOf(d = new Date()) {
	const y = d.getFullYear()
	const m = d.getMonth() + 1
	return m >= 4 ? y : y - 1
}
const nowConst = new Date()
const currentMonth = nowConst.getMonth() + 1
const currentFy = fyOf(nowConst)
const thisFy = currentFy

/* Max FY permitido:
 * - Oct (10) a Mar (3): currentFy + 1
 * - Abr (4) a Sep (9): currentFy
 */
const maxFyAllowed = computed(() => {
	if (currentMonth >= 10 || currentMonth <= 3) {
		return currentFy + 1
	}
	return currentFy
})

const eqMonths = [
	{ key: '04', label: 'Apr' },
	{ key: '05', label: 'Mai' },
	{ key: '06', label: 'Jun' },
	{ key: '07', label: 'Jul' },
	{ key: '08', label: 'Aug' },
	{ key: '09', label: 'Sep' },
	{ key: '10', label: 'Okt' },
	{ key: '11', label: 'Nov' },
	{ key: '12', label: 'Dez' },
	{ key: '01', label: 'Jan' },
	{ key: '02', label: 'Feb' },
	{ key: '03', label: 'Mär' },
]

const eq = ref({
	fy: thisFy,
	months: {
		'01': 0,
		'02': 0,
		'03': 0,
		'04': 0,
		'05': 0,
		'06': 0,
		'07': 0,
		'08': 0,
		'09': 0,
		'10': 0,
		'11': 0,
		'12': 0,
	},
	saving: false,
	loading: false,
})

async function eqLoad() {
	if (!selected.value) return
	eq.value.loading = true
	try {
		const { data } = await api.get(
			'/api/sales-force/extra-quotas',
			{
				params: {
					userId: selected.value.id,
					fy: eq.value.fy,
				},
			},
		)
		const months = data?.months || {}
		const next = { ...eq.value.months }
		for (const { key } of eqMonths) {
			const v =
				months[key] ??
				months[String(key).padStart(2, '0')] ??
				months[`${eq.value.fy}-${key}`] ??
				months[Number(key)] ??
				0
			next[key] = Number(v) || 0
		}
		eq.value.months = next
	} finally {
		eq.value.loading = false
	}
}

// === Extra Quota ===
const xq = ref({
	localUserId: null,
	fy: thisFy,
	pc: '',
	rows: [],
	original: {},
	_dirty: new Map(),
	loading: false,
	error: '',
})

xq.value.dirty = new Map()

const xqEffectiveUserId = computed(
	() => selected.value?.id || xq.value.localUserId || null,
)
const xqDirtyCount = computed(() => xq.value.dirty.size)
const xqIsDirty = (key) => xq.value.dirty.has(key)

/* Label WJ2025/26 */
const xqFyLabel = computed(() => {
	const fy = Number(xq.value.fy || currentFy)
	const nextShort = String((fy + 1) % 100).padStart(2, '0')
	return `WJ${fy}/${nextShort}`
})

/* Label Alle speichern (+ contador) */
const xqSaveAllLabel = computed(() => {
	const base = 'Alle speichern'
	const count = xqDirtyCount.value
	return count > 0 ? `${base} (${count})` : base
})

function xqMarkDirty(row) {
	const key = row._key
	const vol = Number(row.volume) || 0
	const original = xq.value.original[key]

	if (original === undefined) {
		xq.value.original[key] = vol
		xq.value.dirty.delete(key)
		return
	}

	if (vol === original) {
		xq.value.dirty.delete(key)
	} else {
		xq.value.dirty.set(key, {
			id: row.id ?? null,
			volume: vol,
		})
	}
}

async function xqFetchRows() {
	xq.value.error = ''
	if (!xqEffectiveUserId.value) {
		xq.value.error = 'Benutzer-ID fehlt'
		return
	}
	xq.value.loading = true
	try {
		const { data } = await api.get(
			`api/extra-quota/user/${xqEffectiveUserId.value}/all`,
			{
				params: {
					fiscal_year: xq.value.fy,
					pc: xq.value.pc || undefined,
				},
			},
		)
		xq.value.original = {}
		xq.value.dirty.clear()

		xq.value.rows = (Array.isArray(data) ? data : []).map(
			(r) => {
				const row = {
					...r,
					id: r.id ?? null,
					volume: Number(r.volume) || 0,
					_key:
						r.id ?? `pc:${r.profit_center_code}`,
				}
				xq.value.original[row._key] = row.volume
				return row
			},
		)
	} catch (e) {
		xq.value.error =
			e?.response?.data?.message ||
			e?.message ||
			'Fehler beim Laden'
	} finally {
		xq.value.loading = false
	}
}

async function xqSaveOne(row) {
	xq.value.loading = true
	xq.value.error = ''
	try {
		const oldKey = row._key
		const vol = Number(row.volume) || 0

		if (row.id) {
			await api.patch(`api/extra-quota/${row.id}`, {
				volume: vol,
			})
			xq.value.original[oldKey] = vol
			xq.value.dirty.delete(oldKey)
		} else {
			const { data } = await api.post(
				'api/extra-quota/assign',
				{
					user_id: xqEffectiveUserId.value,
					fiscal_year: xq.value.fy,
					profit_center_code: row.profit_center_code,
					volume: vol,
				},
			)
			row.id = data.id
			const newKey = row.id
			delete xq.value.original[oldKey]
			row._key = newKey
			xq.value.original[newKey] = vol
			xq.value.dirty.delete(oldKey)
			xq.value.dirty.delete(newKey)
		}
	} catch (e) {
		xq.value.error =
			e?.response?.data?.message ||
			e?.message ||
			'Speichern fehlgeschlagen'
	} finally {
		xq.value.loading = false
	}
}

async function xqSaveAll() {
	xq.value.loading = true
	xq.value.error = ''
	try {
		const changed = xq.value.rows.filter((r) => {
			const key = r._key
			return (
				xq.value.original[key] !==
				(Number(r.volume) || 0)
			)
		})

		for (const r of changed) {
			const oldKey = r._key
			const vol = Number(r.volume) || 0

			if (r.id) {
				await api.patch(`api/extra-quota/${r.id}`, {
					volume: vol,
				})
				xq.value.original[oldKey] = vol
				xq.value.dirty.delete(oldKey)
			} else {
				const { data } = await api.post(
					'api/extra-quota/assign',
					{
						user_id: xqEffectiveUserId.value,
						fiscal_year: xq.value.fy,
						profit_center_code:
							r.profit_center_code,
						volume: vol,
					},
				)
				r.id = data.id
				const newKey = r.id
				delete xq.value.original[oldKey]
				r._key = newKey
				xq.value.original[newKey] = vol
				xq.value.dirty.delete(oldKey)
				xq.value.dirty.delete(newKey)
			}
		}
	} catch (e) {
		xq.value.error =
			e?.response?.data?.message ||
			e?.message ||
			'Fehler beim Speichern der Änderungen'
	} finally {
		xq.value.loading = false
	}
}

/* Navegación de año fiscal con límites según mes actual */
function xqPrevYear() {
	const curFy = Number(xq.value.fy || currentFy)
	const newFy = curFy - 1
	xq.value.fy = newFy
	eq.value.fy = newFy
	xqFetchRows()
}

function xqNextYear() {
	const curFy = Number(xq.value.fy || currentFy)
	const candidate = curFy + 1
	if (candidate > maxFyAllowed.value) return
	xq.value.fy = candidate
	eq.value.fy = candidate
	xqFetchRows()
}

watch(
	[selected, () => ws.value],
	([u, tab]) => {
		if (u && tab === 'extra') {
			xq.value.localUserId = u.id
			xq.value.fy = thisFy
			eq.value.fy = thisFy
			xqFetchRows()
		}
	},
)

watch(
	[selected, () => eq.value.fy, () => ws.value],
	([u, tab]) => {
		if (u && tab === 'extra') eqLoad()
	},
)
</script>

<style scoped>
.users-page {
	height: 100%;
}

.grid2-10 {
	display: grid;
	grid-template-columns: 2fr 10fr;
	gap: 16px;
	height: 100%;
	min-height: 0;
}

/* Pane base */
.pane {
	background: var(--surface-card, #fff);
	border-radius: 10px;
	box-shadow: 0 1px 8px rgba(0, 0, 0, 0.06);
	padding: 10px;
	overflow: auto;
}

/* LEFT */
.pane.left {
	display: flex;
	flex-direction: column;
	gap: 8px;
	min-height: 0;
}

.pane.left .pane-head {
	display: flex;
	gap: 8px;
	align-items: center;
}

.pane.left .pane-head .search {
	flex: 1 1 auto;
	min-width: 0;
}

.btn-new {
	white-space: nowrap;
}

/* Liste full height */
.list-wrap {
	flex: 1 1 auto;
	min-height: 0;
	display: flex;
	height: 100%;
}

.list-wrap :deep(.p-listbox) {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
	min-height: 0;
	height: 100%;
}

.list-wrap :deep(.p-listbox-list-container) {
	flex: 1 1 auto;
	min-height: 0;
	height: 100%;
	max-height: none !important;
	overflow: auto;
}

.list-wrap :deep(.p-listbox-list) {
	min-height: 100%;
}

/* Item */
.lb-row {
	display: flex;
	align-items: center;
	gap: 10px;
}

.lb-name {
	font-weight: 600;
	color: #111827;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

/* Status */
.state-dot {
	width: 22px;
	height: 22px;
	padding: 0 !important;
	border-radius: 999px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
}

.state-dot .p-tag-icon {
	font-size: 0.9rem;
	line-height: 1;
}

.state-dot.p-tag {
	padding: 0;
	min-width: 22px;
	min-height: 22px;
}

.name-dot {
	margin-left: 8px;
}

/* RIGHT */
.right {
	display: flex;
	flex-direction: column;
	gap: 16px;
	height: 70%;
}

/* Titel */
.title-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
}

.th-left {
	display: flex;
	align-items: center;
	gap: 10px;
}

.avt {
	width: 56px;
	height: 56px;
	font-weight: 700;
}

.id-block {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.name-row {
	display: flex;
	align-items: center;
	gap: 6px;
}

.name {
	font-weight: 800;
	color: #111827;
	font-size: 0.98rem;
	line-height: 1.05;
}

.name-dot {
	width: 16px;
	height: 16px;
	min-width: 16px;
	min-height: 16px;
	padding: 0 !important;
}

.name-dot .p-tag-icon {
	font-size: 0.7rem;
}

/* Chips */
.meta-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin-top: 2px;
}

.tag-slim.p-tag {
	padding: 2px 8px;
	border-radius: 999px;
	font-size: 0.72rem;
	font-weight: 500;
	line-height: 1;
}

.tag-company {
	background: #e5e7eb;
	color: #111827;
	border: none;
}

.tag-team {
	background: #f3f4f6;
	color: #374151;
	border: none;
}

.tag-role {
	background: #dbeafe;
	color: #1d4ed8;
	border: none;
}

/* Mail */
.email {
	color: #6b7280;
	font-size: 0.85rem;
	font-weight: 300;
}

/* Aktionen */
.th-right {
	display: flex;
	align-items: center;
	gap: 6px;
}

.th-right :deep(.p-button.p-button-text) {
	padding: 4px;
}

.th-right :deep(.p-button.p-button-text .p-button-icon) {
	font-size: 1rem;
}

/* ===== Transfer toolbar ===== */
.transfer-toolbar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
	margin-bottom: 10px;
}

.transfer-toolbar .tb-left {
	display: flex;
	align-items: center;
	gap: 30px;
	flex-wrap: wrap;
}

.tb-search :deep(.p-inputtext) {
	width: 16rem;
}

.tb-mode,
.tb-target {
	display: flex;
	align-items: center;
	gap: 6px;
}

.tb-label {
	font-size: 0.8rem;
	color: #6b7280;
	white-space: nowrap;
}

.w-12rem {
	width: 12rem;
}

.transfer-hint {
	margin-bottom: 8px;
}

/* Klassifikation chip */
.class-chip {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 26px;
	height: 26px;
	border-radius: 999px;
	font-size: 0.75rem;
	font-weight: 700;
	color: #fff;
	background: #4b5563;
}

/* ===== Extra Quota ===== */
.xq-title-bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	font-size: 1rem;
	font-weight: 600;
	color: #1f2937;
}

.xq-title-actions {
	display: flex;
	align-items: center;
	gap: 8px;
}

.xq-fy-label {
	min-width: 6.5rem;
	text-align: center;
	font-weight: 700;
	color: #111827;
}

.xq-save-all-btn {
	margin-left: 4px;
}

.xq-wrap {
	display: grid;
	gap: 12px;
}

.xq-bar {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.xq-spacer {
	flex: 1;
}

.xq-err {
	color: #b91c1c;
	background: #fee2e2;
	border: 1px solid #fecaca;
	padding: 8px 10px;
	border-radius: 8px;
}

.xq-table-wrap {
	position: relative;
	border: 1px solid #e2e8f0;
	border-radius: 10px;
	overflow: hidden;
}
.xq-tbl {
	width: 100%;
	border-collapse: collapse;
	background: rgba(255, 255, 255, 0.92);
}
.xq-tbl th,
.xq-tbl td {
	padding: 10px;
	border-bottom: 1px solid #e2e8f0;
}
.xq-tbl th {
	text-align: center;
	background: #f8fafc;
	font-weight: 700;
	font-size: 0.9rem;
	color: #334155;
}
.xq-tbl th:first-child,
.xq-tbl td:first-child {
	text-align: left;
}
.tc {
	text-align: center;
}

/* Volumen input */
.xq-vol {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
}
.xq-vol input {
	width: 80px;
	padding: 4px 6px;
	border: 1px solid #cbd5e1;
	border-radius: 6px;
	font-size: 0.85rem;
}

/* old value */
.old {
	color: #64748b;
	font-size: 0.8rem;
}

.empty {
	text-align: center;
	color: #64748b;
	padding: 16px 0;
}

.xq-overlay {
	position: absolute;
	inset: 0;
	display: grid;
	place-items: center;
	gap: 8px;
	background: rgba(0, 0, 0, 0.08);
}

/* Save button por fila: base blanco, activo negro */
.xq-save-btn.p-button {
	background: #ffffff;
	border-color: #d1d5db;
	color: #111827;
	padding: 4px 6px;
}
.xq-save-btn.p-button .p-button-icon {
	color: #111827;
}
.xq-save-btn.p-button.xq-save-btn--active {
	background: #111827;
	border-color: #111827;
	color: #f9fafb;
}
.xq-save-btn.p-button.xq-save-btn--active .p-button-icon {
	color: #f9fafb;
}
.xq-save-btn.p-button.p-disabled {
	opacity: 0.6;
}

/* ===== Dialog Styling ===== */
:deep(.p-dialog) {
	border-radius: 12px;
	box-shadow: 0 20px 40px rgba(15, 23, 42, 0.25);
}

:deep(.p-dialog .p-dialog-header) {
	padding: 0.8rem 1rem;
	border-bottom: 1px solid #e5e7eb;
	background: #f9fafb;
}

:deep(.p-dialog .p-dialog-title) {
	font-weight: 700;
	font-size: 0.95rem;
	color: #111827;
}

.dialog-body {
	padding-top: 0.25rem;
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
}

/* Campos de formulario en diálogos */
.form-field {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
}
.field-label {
	font-size: 0.75rem;
	font-weight: 600;
	color: #4b5563;
}

.dialog-footer {
	display: flex;
	justify-content: flex-end;
	gap: 0.5rem;
	padding-top: 0.25rem;
}
</style>
