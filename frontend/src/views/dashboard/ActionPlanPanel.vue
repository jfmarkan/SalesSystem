<template>
    <div class="deviations-wrapper">
        <Toast />

        <!-- Title / Tabs -->
        <div class="title-glass">
            <div class="title-bar">
                <h2 class="m-0">Aktionspläne</h2>
                <div class="right">
                    <div class="tabs">
                        <button
                            class="tab"
                            :class="{ active: tab === 'open' }"
                            @click="tab = 'open'"
                        >
                            Offen <span class="badge">{{ openPlans.length }}</span>
                        </button>
                        <button
                            class="tab"
                            :class="{ active: tab === 'completed' }"
                            @click="tab = 'completed'"
                        >
                            Erledigt <span class="badge">{{ completedPlans.length }}</span>
                        </button>
                        <button
                            class="tab"
                            :class="{ active: tab === 'cancelled' }"
                            @click="tab = 'cancelled'"
                        >
                            Abgebrochen <span class="badge">{{ cancelledPlans.length }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- List -->
        <div class="list-wrap">
            <div v-if="loading" class="local-loader">
                <div class="dots">
                    <span class="dot g"></span><span class="dot r"></span
                    ><span class="dot b"></span>
                </div>
                <div class="caption">Wird geladen…</div>
            </div>

            <template v-else>
                <template v-if="tab === 'open'">
                    <template v-if="openPlans.length">
                        <ActionPlanItem
                            v-for="p in openPlans"
                            :key="p.id"
                            :plan="p"
                            :readonly="false"
                            @item-updated="onItemUpdated"
                        />
                    </template>
                    <div v-else class="empty">Keine offenen Pläne.</div>
                </template>

                <template v-else-if="tab === 'completed'">
                    <template v-if="completedPlans.length">
                        <ActionPlanItem
                            v-for="p in completedPlans"
                            :key="'c-' + p.id"
                            :plan="p"
                            :readonly="true"
                        />
                    </template>
                    <div v-else class="empty">Keine erledigten Pläne.</div>
                </template>

                <template v-else>
                    <template v-if="cancelledPlans.length">
                        <ActionPlanItem
                            v-for="p in cancelledPlans"
                            :key="'x-' + p.id"
                            :plan="p"
                            :readonly="true"
                        />
                    </template>
                    <div v-else class="empty">Keine abgebrochenen Pläne.</div>
                </template>
            </template>
        </div>
    </div>
</template>

<script setup>
// Code in English; UI German.
import { ref, computed, onMounted } from 'vue'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import api from '@/plugins/axios'
import { ensureCsrf } from '@/plugins/csrf'
import ActionPlanItem from '@/components/elements/ActionPlanItem.vue'

const toast = useToast()
const loading = ref(false)
const tab = ref('open')
const actionPlans = ref([])

function normalizeItem(i) {
    return {
        id: i.id,
        action_plan_id: i.action_plan_id,
        title: i.title || '',
        description: i.description || '',
        due_date: i.due_date || null, // 'YYYY-MM-DD'
        status: i.status || 'in_progress',
        created_at: i.created_at || null,
        updated_at: i.updated_at || null,
    }
}

function inferPlanStatus(items) {
    if (!items.length) return 'in_progress'
    const allCompleted = items.every((i) => i.status === 'completed')
    const allCancelled = items.every((i) => i.status === 'cancelled')
    if (allCompleted) return 'completed'
    if (allCancelled) return 'cancelled'
    return 'in_progress'
}

function normalizePlan(p) {
    const items = Array.isArray(p.action_items) ? p.action_items.map(normalizeItem) : []
    return {
        id: p.id,
        deviation_id: p.deviation_id,
        user_id: p.user_id,
        objective: p.objective || '',
        created_at: p.created_at || null,
        updated_at: p.updated_at || null,
        action_items: items,
        status: p.status || inferPlanStatus(items), // backend may not send status
    }
}

const openPlans = computed(() => actionPlans.value.filter((p) => p.status === 'in_progress'))
const completedPlans = computed(() => actionPlans.value.filter((p) => p.status === 'completed'))
const cancelledPlans = computed(() => actionPlans.value.filter((p) => p.status === 'cancelled'))

async function loadPlans() {
    loading.value = true
    try {
        await ensureCsrf()
        const { data } = await api.get('/api/action-plans/my-plans')
        actionPlans.value = Array.isArray(data) ? data.map(normalizePlan) : []
    } catch (e) {
        actionPlans.value = []
        toast.add({
            severity: 'error',
            summary: 'Fehler',
            detail: 'Aktionspläne konnten nicht geladen werden',
            life: 2500,
        })
    } finally {
        loading.value = false
    }
}

// Update the local plan + recompute status (no full reload -> avoids UI lock)
function onItemUpdated(updated) {
    const planIdx = actionPlans.value.findIndex((p) => p.id === updated.action_plan_id)
    if (planIdx < 0) return
    const itemIdx = actionPlans.value[planIdx].action_items.findIndex((i) => i.id === updated.id)
    if (itemIdx >= 0) {
        actionPlans.value[planIdx].action_items[itemIdx] = normalizeItem(updated)
    } else {
        actionPlans.value[planIdx].action_items.unshift(normalizeItem(updated))
    }
    actionPlans.value[planIdx].status = inferPlanStatus(actionPlans.value[planIdx].action_items)
}

onMounted(loadPlans)
</script>
