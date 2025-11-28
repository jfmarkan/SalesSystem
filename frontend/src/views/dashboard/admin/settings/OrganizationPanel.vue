<template>
  <div class="org-designer">
    <Toast />

    <div class="toolbar">
      <button class="btn primary" @click="openCreateRoot">
        Neue Stammgesellschaft
      </button>
    </div>

    <OrganizationChart
      v-if="rootNode"
      v-model:selectionKeys="selection"
      :value="rootNode"
      collapsible
      selectionMode="multiple"
    >
      <!-- STRUCTURAL NODES (company / subsidiary / team / root) -->
      <template #default="slotProps">
        <div
          class="node-base unit-node"
          :class="'kind-' + (slotProps.node.data?.kind || 'root')"
        >
          <!-- Team node: big team name, small manager name, manager avatar -->
          <template v-if="getKind(slotProps.node) === 'team'">
            <div class="team-manager">
              <!-- Manager avatar (photo or initials) -->
              <Avatar
                v-if="slotProps.node.data?.manager?.image"
                :image="slotProps.node.data.manager.image"
                shape="circle"
                size="large"
              />
              <Avatar
                v-else
                :label="getInitials(slotProps.node.data?.manager?.name || slotProps.node.data?.name)"
                shape="circle"
                size="large"
              />

              <div class="person-text">
                <!-- Team name (highlighted) -->
                <div class="node-title">
                  {{ slotProps.node.data?.name || slotProps.node.label || 'Team' }}
                </div>
                <!-- Manager name (fine subtitle) -->
                <div class="node-subtitle">
                  {{ slotProps.node.data?.manager?.name || '' }}
                </div>
              </div>
            </div>
          </template>

          <!-- Company / Subsidiary / Root -->
          <template v-else>
            <div class="node-text">
              <div class="node-title">
                {{ slotProps.node.label || slotProps.node.data?.name }}
              </div>
              <div class="node-subtitle">
                {{ slotProps.node.data?.title }}
              </div>
            </div>
          </template>

          <!-- Actions (edit, add, delete) -->
          <div class="node-actions">
            <button
              v-if="isRealUnit(slotProps.node)"
              class="icon-button"
              @click.stop="openEditNode(slotProps.node)"
              title="Bearbeiten"
            >
              <i class="pi pi-pencil"></i>
            </button>

            <button
              v-if="canShowPlus(slotProps.node)"
              class="icon-button"
              @click.stop="handlePlus(slotProps.node)"
              :title="plusTitle(slotProps.node)"
            >
              <i class="pi pi-plus"></i>
            </button>

            <button
              v-if="canShowDelete(slotProps.node)"
              class="icon-button danger"
              :disabled="isDeleteDisabled(slotProps.node)"
              :class="{ disabled: isDeleteDisabled(slotProps.node) }"
              @click.stop="openDeleteNode(slotProps.node)"
              title="Löschen"
            >
              <i class="pi pi-trash"></i>
            </button>
          </div>
        </div>
      </template>

      <!-- MEMBERS NODE: AvatarGroup with sellers -->
      <template #members="slotProps">
        <div class="node-base members-node">
          <div class="node-text">
            <div class="node-title">
              Verkäufer
            </div>
            <div class="node-subtitle">
              {{ slotProps.node.data?.count || 0 }} Mitglieder
            </div>
          </div>

          <!-- AvatarGroup for sellers -->
          <AvatarGroup class="members-avatargroup">
            <template
              v-for="member in visibleMembers(slotProps.node)"
              :key="member.id"
            >
              <Avatar
                v-if="member.image"
                :image="member.image"
                shape="circle"
              />
              <Avatar
                v-else
                :label="getInitials(member.name)"
                shape="circle"
              />
            </template>

            <!-- Extra counter -->
            <Avatar
              v-if="extraCount(slotProps.node) > 0"
              :label="'+' + extraCount(slotProps.node)"
              shape="circle"
            />
          </AvatarGroup>

          <div class="node-actions">
            <button
              class="icon-button"
              @click.stop="openMembersDialog(slotProps.node)"
              title="Team-Mitglieder bearbeiten"
            >
              <i class="pi pi-users"></i>
            </button>
          </div>
        </div>
      </template>
    </OrganizationChart>

    <!-- CREATE dialog (company / subsidiary / team) -->
    <Dialog
      v-model:visible="showCreateDialog"
      :modal="true"
      :closable="true"
      :dismissable-mask="true"
      :header="createDialogTitle"
      :style="{ width: '420px' }"
    >
      <form @submit.prevent="submitCreate">
        <div class="field">
          <label>Du erstellst:</label>
          <div class="pill">
            {{ createLabel }}
          </div>
        </div>

        <div
          v-if="['company','subsidiary','team'].includes(createContext.mode)"
          class="field"
        >
          <label for="create-name">Name</label>
          <input
            id="create-name"
            v-model="createForm.name"
            type="text"
            class="text-input"
            required
          />
        </div>

        <div v-if="createContext.mode === 'team'" class="field">
          <label for="manager-id">Manager</label>
          <select
            id="manager-id"
            v-model="createForm.managerUserId"
            class="text-input"
            required
          >
            <option value="" disabled>Bitte Manager auswählen</option>
            <option
              v-for="user in adminUsers"
              :key="user.id"
              :value="user.id"
            >
              {{ user.name }} ({{ user.email }})
            </option>
          </select>
        </div>

        <div class="dialog-actions">
          <button type="button" class="btn secondary" @click="showCreateDialog = false">
            Abbrechen
          </button>
          <button type="submit" class="btn primary">
            Erstellen
          </button>
        </div>
      </form>
    </Dialog>

    <!-- EDIT dialog -->
    <Dialog
      v-model:visible="showEditDialog"
      :modal="true"
      :closable="true"
      :dismissable-mask="true"
      header="Element bearbeiten"
      :style="{ width: '420px' }"
    >
      <form @submit.prevent="submitEdit">
        <div class="field">
          <label>Typ</label>
          <div class="pill">
            {{ editTypeLabel }}
          </div>
        </div>

        <div class="field">
          <label for="edit-name">Name</label>
          <input
            id="edit-name"
            v-model="editForm.name"
            type="text"
            class="text-input"
            required
          />
        </div>

        <div class="dialog-actions">
          <button type="button" class="btn secondary" @click="showEditDialog = false">
            Abbrechen
          </button>
          <button type="submit" class="btn primary">
            Änderungen speichern
          </button>
        </div>
      </form>
    </Dialog>

    <!-- DELETE dialog -->
    <Dialog
      v-model:visible="showDeleteDialog"
      :modal="true"
      :closable="false"
      header="Löschen bestätigen"
      :style="{ width: '380px' }"
    >
      <div v-if="deleteContext.node" class="delete-dialog-content">
        <p>
          Möchten Sie
          <strong>{{ deleteContext.label }}</strong>
          wirklich löschen?
        </p>
        <p class="hint">
          Diese Aktion kann nicht rückgängig gemacht werden.
        </p>
      </div>

      <div class="dialog-actions">
        <button type="button" class="btn secondary" @click="showDeleteDialog = false">
          Abbrechen
        </button>
        <button type="button" class="btn danger" @click="performDelete">
          Löschen
        </button>
      </div>
    </Dialog>

    <!-- MEMBERS PICKLIST dialog -->
    <Dialog
      v-model:visible="showMembersDialog"
      :modal="true"
      :closable="true"
      :dismissable-mask="true"
      header="Team-Mitglieder bearbeiten"
      :style="{ width: '700px' }"
    >
      <div v-if="membersContext.teamId" class="members-dialog-body">
        <PickList
          v-model="picklistValue"
          dataKey="id"
          :metaKeySelection="false"
          :showSourceControls="false"
          :showTargetControls="false"
        >
          <template #sourceheader>Verfügbare Benutzer</template>
          <template #targetheader>Team-Mitglieder</template>

          <template #item="slotProps">
            <div class="user-row">
              <Avatar
                v-if="slotProps.item.image"
                :image="slotProps.item.image"
                shape="circle"
                size="normal"
              />
              <Avatar
                v-else
                :label="getInitials(slotProps.item.name)"
                shape="circle"
                size="normal"
              />
              <div class="user-text">
                <div class="user-name">{{ slotProps.item.name }}</div>
                <div class="user-email">{{ slotProps.item.email }}</div>
              </div>
            </div>
          </template>
        </PickList>

        <div class="dialog-actions">
          <button type="button" class="btn secondary" @click="showMembersDialog = false">
            Abbrechen
          </button>
          <button type="button" class="btn primary" @click="saveMembers">
            Speichern
          </button>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import PickList from 'primevue/picklist';
import AvatarGroup from 'primevue/avatargroup';
import Avatar from 'primevue/avatar';
import api from '@/plugins/axios';
import { ensureCsrf } from '@/plugins/csrf';

const toast = useToast();

const rootNode = ref(null);
const selection = ref({});

// managers (manager role / role_id=3) for new teams
const adminUsers = ref([]);
// all users (for PickList)
const allUsers = ref([]);

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const showDeleteDialog = ref(false);
const showMembersDialog = ref(false);

const createContext = ref({
  mode: null,          // 'company' | 'subsidiary' | 'team'
  parentCompanyId: null,
});

const createForm = ref({
  name: '',
  managerUserId: '',
});

const editContext = ref({
  node: null,
});

const editForm = ref({
  name: '',
});

const deleteContext = ref({
  node: null,
  label: '',
});

// members dialog context
const membersContext = ref({
  teamId: null,
});

// PickList model: [source, target]
const picklistValue = ref([[], []]);

/* ---------- API helpers ---------- */

const secureGet = async (url, config = {}) => {
  await ensureCsrf();
  return api.get(url, config);
};
const securePost = async (url, data = {}, config = {}) => {
  await ensureCsrf();
  return api.post(url, data, config);
};
const securePut = async (url, data = {}, config = {}) => {
  await ensureCsrf();
  return api.put(url, data, config);
};
const secureDelete = async (url, config = {}) => {
  await ensureCsrf();
  return api.delete(url, config);
};

/* ---------- helpers ---------- */

const getInitials = (name) => {
  if (!name || typeof name !== 'string') return '?';
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

const getKind = (node) => node?.data?.kind || null;
const isVirtualRoot = (node) => !node?.data?.entity && node?.type === 'root';

const isRealUnit = (node) => {
  if (!node) return false;
  if (isVirtualRoot(node)) return false;
  const kind = getKind(node);
  return ['company', 'subsidiary', 'team'].includes(kind);
};

const canShowPlus = (node) => {
  if (!node) return false;
  if (isVirtualRoot(node)) return false;
  const kind = getKind(node);
  return ['company', 'subsidiary', 'team'].includes(kind);
};

const plusTitle = (node) => {
  const kind = getKind(node);
  if (kind === 'company') return 'Tochtergesellschaft hinzufügen';
  if (kind === 'subsidiary') return 'Team hinzufügen';
  if (kind === 'team') return 'Team-Mitglieder bearbeiten';
  return 'Element hinzufügen';
};

const canShowDelete = (node) => {
  if (!node) return false;
  if (isVirtualRoot(node)) return false;
  const d = node.data || {};
  return d.canDelete === true;
};

const isDeleteDisabled = (node) => {
  const d = node?.data || {};
  return d.canDelete === false;
};

const getNodeLabel = (node) =>
  node?.label || node?.data?.name || 'Ohne Name';

// get members array from a "members" node
const getMembersFromMembersNode = (membersNode) => {
  return membersNode?.data?.members || [];
};

// get members array from a team node (look at its children)
const getMembersFromTeamNode = (teamNode) => {
  const children = teamNode.children || [];
  const membersNode = children.find((c) => c.type === 'members');
  return getMembersFromMembersNode(membersNode);
};

const visibleMembers = (membersNode) => {
  const all = getMembersFromMembersNode(membersNode);
  return all.slice(0, 4);
};

const extraCount = (membersNode) => {
  const all = getMembersFromMembersNode(membersNode);
  if (all.length <= 4) return 0;
  return all.length - 4;
};

/* ---------- labels ---------- */

const createLabel = computed(() => {
  const m = createContext.value.mode;
  if (m === 'company') return 'Stammgesellschaft';
  if (m === 'subsidiary') return 'Tochtergesellschaft';
  if (m === 'team') return 'Team';
  return 'Element';
});

const createDialogTitle = computed(() => {
  const m = createContext.value.mode;
  if (m === 'company') return 'Stammgesellschaft erstellen';
  if (m === 'subsidiary') return 'Tochtergesellschaft erstellen';
  if (m === 'team') return 'Team erstellen';
  return 'Element erstellen';
});

const editTypeLabel = computed(() => {
  const node = editContext.value.node;
  if (!node) return '';
  const kind = getKind(node);
  if (kind === 'company') return 'Stammgesellschaft';
  if (kind === 'subsidiary') return 'Tochtergesellschaft';
  if (kind === 'team') return 'Team';
  return 'Element';
});

/* ---------- load data ---------- */

const fetchOrgChart = async () => {
  try {
    await ensureCsrf();
    const { data } = await api.get('/api/settings/org-chart/tree');
    rootNode.value = data;
  } catch (e) {
    console.error('Error loading org chart', e);
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: 'Die Organisationsstruktur konnte nicht geladen werden.',
      life: 4000,
    });
  }
};

const fetchAdminUsers = async () => {
  if (adminUsers.value.length > 0) return;
  try {
    const { data } = await secureGet('/api/settings/org-chart/admin-users');
    adminUsers.value = data;
  } catch (e) {
    console.error('Error loading admin users', e);
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: 'Manager-Liste konnte nicht geladen werden.',
      life: 4000,
    });
  }
};

const fetchAllUsers = async () => {
  if (allUsers.value.length > 0) return;
  try {
    const { data } = await secureGet('/api/settings/org-chart/users');
    allUsers.value = data;
  } catch (e) {
    console.error('Error loading users', e);
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: 'Benutzerliste konnte nicht geladen werden.',
      life: 4000,
    });
  }
};

/* ---------- actions ---------- */

const openCreateRoot = () => {
  createContext.value = {
    mode: 'company',
    parentCompanyId: null,
  };
  createForm.value = {
    name: '',
    managerUserId: '',
  };
  showCreateDialog.value = true;
};

const handlePlus = async (node) => {
  const kind = getKind(node);

  if (kind === 'company') {
    // add subsidiary
    createContext.value = {
      mode: 'subsidiary',
      parentCompanyId: node.data.id,
    };
    createForm.value = {
      name: '',
      managerUserId: '',
    };
    showCreateDialog.value = true;
    return;
  }

  if (kind === 'subsidiary') {
    // add team
    await fetchAdminUsers();
    createContext.value = {
      mode: 'team',
      parentCompanyId: node.data.id,
    };
    createForm.value = {
      name: '',
      managerUserId: '',
    };
    showCreateDialog.value = true;
    return;
  }

  if (kind === 'team') {
    // edit members via PickList
    openMembersDialogFromTeam(node);
  }
};

const openEditNode = (node) => {
  if (!node?.data?.id) return;
  editContext.value.node = node;
  editForm.value.name = getNodeLabel(node);
  showEditDialog.value = true;
};

const openDeleteNode = (node) => {
  deleteContext.value.node = node;
  deleteContext.value.label = getNodeLabel(node);
  showDeleteDialog.value = true;
};

/* ---------- members dialog ---------- */

const openMembersDialog = async (membersNode) => {
  const teamId = membersNode?.data?.team_id;
  if (!teamId) return;

  await fetchAllUsers();

  const currentMembers = getMembersFromMembersNode(membersNode);
  const currentIds = new Set(currentMembers.map((m) => m.id));

  const source = allUsers.value.filter((u) => !currentIds.has(u.id));
  const target = currentMembers.slice();

  picklistValue.value = [source, target];
  membersContext.value = { teamId };
  showMembersDialog.value = true;
};

const openMembersDialogFromTeam = async (teamNode) => {
  const teamId = teamNode?.data?.id;
  if (!teamId) return;

  await fetchAllUsers();

  const currentMembers = getMembersFromTeamNode(teamNode);
  const currentIds = new Set(currentMembers.map((m) => m.id));

  const source = allUsers.value.filter((u) => !currentIds.has(u.id));
  const target = currentMembers.slice();

  picklistValue.value = [source, target];
  membersContext.value = { teamId };
  showMembersDialog.value = true;
};

const saveMembers = async () => {
  try {
    const teamId = membersContext.value.teamId;
    if (!teamId) return;

    const [, target] = picklistValue.value;

    const payload = {
      team_id: teamId,
      members: target.map((u) => ({
        user_id: u.id,
        role: 'SALES_REP',
      })),
    };

    await securePost('/api/settings/org-chart/team-members/sync', payload);

    showMembersDialog.value = false;

    toast.add({
      severity: 'success',
      summary: 'Gespeichert',
      detail: 'Die Team-Mitglieder wurden aktualisiert.',
      life: 3000,
    });

    await fetchOrgChart();
  } catch (e) {
    console.error('Error saving team members', e);
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: e.response?.data?.message || 'Die Team-Mitglieder konnten nicht gespeichert werden.',
      life: 4000,
    });
  }
};

/* ---------- submit create ---------- */

const submitCreate = async () => {
  const ctx = createContext.value;
  const mode = ctx.mode;

  try {
    if (mode === 'company' || mode === 'subsidiary') {
      const name = createForm.value.name.trim();
      if (!name) return;

      const payload = {
        name,
        parent_company_id: mode === 'subsidiary' ? ctx.parentCompanyId : null,
      };

      await securePost('/api/settings/org-chart/companies', payload);
    } else if (mode === 'team') {
      const name = createForm.value.name.trim();
      const managerId = createForm.value.managerUserId;
      if (!name || !ctx.parentCompanyId || !managerId) return;

      const payload = {
        name,
        company_id: ctx.parentCompanyId,
        manager_user_id: managerId,
      };

      await securePost('/api/settings/org-chart/teams', payload);
    }

    showCreateDialog.value = false;
    toast.add({
      severity: 'success',
      summary: 'Erfolgreich',
      detail: 'Das Element wurde erfolgreich erstellt.',
      life: 3000,
    });
    await fetchOrgChart();
  } catch (e) {
    console.error('Error creating element', e);
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: e.response?.data?.message || e.message || 'Das Element konnte nicht erstellt werden.',
      life: 4000,
    });
  }
};

/* ---------- submit edit ---------- */

const submitEdit = async () => {
  const node = editContext.value.node;
  if (!node?.data?.id) return;

  const name = editForm.value.name.trim();
  if (!name) return;

  const kind = getKind(node);
  const id = node.data.id;

  try {
    if (kind === 'company' || kind === 'subsidiary') {
      await securePut(`/api/settings/org-chart/companies/${id}`, { name });
    } else if (kind === 'team') {
      await securePut(`/api/settings/org-chart/teams/${id}`, { name });
    }

    showEditDialog.value = false;
    toast.add({
      severity: 'success',
      summary: 'Gespeichert',
      detail: 'Die Änderungen wurden gespeichert.',
      life: 3000,
    });
    await fetchOrgChart();
  } catch (e) {
    console.error('Error updating element', e);
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: e.response?.data?.message || 'Die Änderungen konnten nicht gespeichert werden.',
      life: 4000,
    });
  }
};

/* ---------- delete structural node ---------- */

const performDelete = async () => {
  const node = deleteContext.value.node;
  if (!node) return;

  try {
    const kind = getKind(node);
    const id = node.data?.id;

    if (kind === 'company' || kind === 'subsidiary') {
      await secureDelete(`/api/settings/org-chart/companies/${id}`);
    } else if (kind === 'team') {
      await secureDelete(`/api/settings/org-chart/teams/${id}`);
    }

    showDeleteDialog.value = false;
    toast.add({
      severity: 'success',
      summary: 'Gelöscht',
      detail: 'Das Element wurde gelöscht.',
      life: 3000,
    });
    await fetchOrgChart();
  } catch (e) {
    console.error('Error deleting element', e);
    showDeleteDialog.value = false;
    toast.add({
      severity: 'error',
      summary: 'Fehler',
      detail: e.response?.data?.message || 'Das Element konnte nicht gelöscht werden.',
      life: 4000,
    });
  }
};

onMounted(fetchOrgChart);
</script>

<style scoped>
.org-designer {
  padding: 1.25rem;
  min-height: 80vh;
  box-sizing: border-box;
}

.toolbar {
  display: flex;
  justify-content: flex-end;
  margin-bottom: 1rem;
}

/* Clean chart look */
:deep(.p-organizationchart) {
  background: transparent;
  border: none;
  box-shadow: none;
}
:deep(.p-organizationchart-node-content) {
  background: transparent;
  border: none;
  box-shadow: none;
  padding: 0;
}

/* Darker connector lines */
:deep(.p-organizationchart-line-down),
:deep(.p-organizationchart-line-left),
:deep(.p-organizationchart-line-top),
:deep(.p-organizationchart-line-right),
:deep(.p-organizationchart-line-root),
:deep(.p-organizationchart-node-connector) {
  border-color: #4b5563 !important;
}

/* Base node */
.node-base {
  min-width: 180px;
  max-width: 260px;
  padding: 0.3rem 0.4rem;
  box-sizing: border-box;
  background: transparent;
  border: none;
  display: flex;
  flex-direction: column;
}

/* Text */
.node-text {
  display: flex;
  flex-direction: column;
}
.node-title {
  font-size: 0.95rem;
  font-weight: 600;
  color: #111827;
}
.node-subtitle {
  font-size: 0.8rem;
  color: #6b7280;
  margin: 0.25rem 0 0.15rem;
}

/* Team manager block */
.team-manager {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 0.35rem;
}

.person-text {
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* Members node */
.members-node {
  align-items: flex-start;
}
.members-avatargroup {
  margin-top: 0.3rem;
}

/* Structural accents */
.unit-node.kind-company .node-title {
  color: #4A657C;
}
.unit-node.kind-subsidiary .node-title {
  color: #557761;
}
.unit-node.kind-team .node-title {
  color: #7D733F;
}

/* Actions */
.node-actions {
  display: flex;
  gap: 0.25rem;
  align-items: center;
  justify-content: flex-end;
  margin-top: 0.25rem;
}
.icon-button {
  border: none;
  background: transparent;
  color: #4b5563;
  width: 22px;
  height: 22px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: background 0.15s ease, transform 0.1s ease, opacity 0.15s ease;
}
.icon-button i {
  font-size: 0.8rem;
}
.icon-button:hover {
  background: rgba(0, 0, 0, 0.04);
  transform: translateY(-1px);
}
.icon-button.danger {
  color: #b91c1c;
}
.icon-button.disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

/* Buttons & dialogs */
.btn {
  border-radius: 4px;
  border: none;
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
  cursor: pointer;
}
.btn.primary {
  background: #4A657C;
  color: #fff;
}
.btn.primary:hover {
  background: #59768E;
}
.btn.secondary {
  background: #e5e7eb;
  color: #111827;
}
.btn.secondary:hover {
  background: #d1d5db;
}
.btn.danger {
  background: #dc2626;
  color: #fff;
}
.btn.danger:hover {
  background: #b91c1c;
}

.field {
  margin-bottom: 0.9rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.text-input {
  border-radius: 4px;
  border: 1px solid #d1d5db;
  padding: 0.4rem 0.5rem;
  font-size: 0.9rem;
  outline: none;
}
.text-input:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.25);
}
.pill {
  display: inline-flex;
  align-items: center;
  padding: 0.2rem 0.6rem;
  border-radius: 999px;
  background: #e5e7eb;
  font-size: 0.8rem;
}
.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
}
.delete-dialog-content .hint {
  font-size: 0.8rem;
  color: #6b7280;
  margin-top: 0.25rem;
}

/* PickList items */
.members-dialog-body {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
.user-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.user-text {
  display: flex;
  flex-direction: column;
}
.user-name {
  font-size: 0.85rem;
  color: #111827;
}
.user-email {
  font-size: 0.75rem;
  color: #6b7280;
}
</style>
