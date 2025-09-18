<template>
    <div class="wrap">
        <h2>Extra Quotas · User #{{ effectiveUserId ?? '—' }}</h2>

        <div class="bar">
            <label>ID Usuario</label>
            <input type="number" v-model.number="localUserId" min="1" placeholder="userId" />
            <label>Fiscal Year</label>
            <input type="number" v-model.number="fy" min="2000" />
            <label>PC Code</label>
            <input type="text" v-model="pc" placeholder="(opcional)" />
            <button @click="fetchRows" :disabled="!effectiveUserId || loading">Cargar</button>
            <span class="spacer"></span>
            <button class="primary" @click="saveAll" :disabled="dirtyCount === 0 || loading">
                Guardar ({{ dirtyCount }})
            </button>
        </div>

        <div v-if="error" class="err">{{ error }}</div>

        <div class="table-wrap" :class="{ loading: loading }">
            <table class="tbl">
                <thead>
                    <tr>
                        <th style="width: 120px">PC Code</th>
                        <th>Profit Center</th>
                        <th style="width: 90px">FY</th>
                        <th style="width: 220px">Volumen</th>
                        <th style="width: 90px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.id">
                        <td>{{ r.profit_center_code }}</td>
                        <td>{{ r.profit_center_name }}</td>
                        <td class="tc">{{ r.fiscal_year }}</td>
                        <td>
                            <div class="vol">
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    v-model.number="r.volume"
                                    @input="markDirty(r)"
                                />
                                <small
                                    v-if="
                                        original[r.id] !== undefined && original[r.id] !== r.volume
                                    "
                                    class="old"
                                >
                                    old: {{ original[r.id] }}
                                </small>
                            </div>
                        </td>
                        <td class="tc">
                            <button
                                class="ghost"
                                :disabled="!isDirty(r.id) || loading"
                                @click="saveOne(r)"
                            >
                                💾
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="5" class="empty">Sin cuotas para este filtro.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="loading" class="overlay">
                <div class="spinner"></div>
                <div>Cargando…</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/plugins/axios'

const route = useRoute()

// fuente de verdad del userId: ruta o input local
const routeUserId = computed(() => {
    const p = route.params.userId || route.query.user_id
    return p ? Number(p) : null
})
const localUserId = ref(routeUserId.value || null)
const effectiveUserId = computed(() => routeUserId.value || localUserId.value || null)

const fy = ref(Number(route.query.fiscal_year || route.query.fy) || new Date().getFullYear())
const pc = ref('')

const rows = ref([])
const original = reactive({}) // id -> volumen original
const dirty = reactive(new Map()) // id -> { id, volume }
const loading = ref(false)
const error = ref('')

const dirtyCount = computed(() => dirty.size)
const isDirty = (id) => dirty.has(id)

function markDirty(row) {
    if (original[row.id] === undefined) original[row.id] = row.volume
    dirty.set(row.id, { id: row.id, volume: Number(row.volume) || 0 })
}

// 🚀 Trae TODOS los PCs (tenga o no cuota) para el user+FY
async function fetchRows() {
    error.value = ''
    if (!effectiveUserId.value) {
        error.value = 'Falta userId'
        return
    }
    loading.value = true
    try {
        const { data } = await api.get(`api/extra-quota/user/${effectiveUserId.value}/all`, {
            params: { fiscal_year: fy.value },
        })
        // reset snapshots
        for (const k in original) delete original[k]
        dirty.clear()

        rows.value = (Array.isArray(data) ? data : []).map((r) => {
            const row = {
                ...r,
                id: r.id ?? null,
                volume: Number(r.volume) || 0,
                _key: r.id ?? null ?? `pc:${r.profit_center_code}`,
            }
            original[row._key] = row.volume
            return row
        })
    } catch (e) {
        error.value = e?.response?.data?.message || e?.message || 'Error al cargar'
    } finally {
        loading.value = false
    }
}

// 💾 Guarda UNA fila (patch si existe, post si no)
async function saveOne(row) {
    loading.value = true
    error.value = ''
    try {
        const oldKey = row._key || (row.id ?? `pc:${row.profit_center_code}`)
        const vol = Number(row.volume) || 0

        if (row.id) {
            await api.patch(`api/extra-quota/${row.id}`, { volume: vol })
            original[oldKey] = vol
            dirty.delete(oldKey)
        } else {
            const { data } = await api.post('api/extra-quota/assign', {
                user_id: effectiveUserId.value,
                fiscal_year: fy.value,
                profit_center_code: row.profit_center_code,
                volume: vol,
            })
            // actualizar id y key
            row.id = data.id
            const newKey = row.id
            delete original[oldKey]
            row._key = newKey
            original[newKey] = vol
            dirty.delete(oldKey)
            dirty.delete(newKey)
        }
    } catch (e) {
        error.value = e?.response?.data?.message || e?.message || 'No se pudo guardar'
    } finally {
        loading.value = false
    }
}

// 💾 Guarda TODAS las filas modificadas (patch + upsert)
async function saveAll() {
    loading.value = true
    error.value = ''
    try {
        // detectar cambios por snapshot (independiente del Map dirty)
        const changed = rows.value.filter((r) => {
            const key = r._key || (r.id ?? `pc:${r.profit_center_code}`)
            return original[key] !== (Number(r.volume) || 0)
        })

        for (const r of changed) {
            const oldKey = r._key || (r.id ?? `pc:${r.profit_center_code}`)
            const vol = Number(r.volume) || 0

            if (r.id) {
                await api.patch(`api/extra-quota/${r.id}`, { volume: vol })
                original[oldKey] = vol
                dirty.delete(oldKey)
            } else {
                const { data } = await api.post('api/extra-quota/assign', {
                    user_id: effectiveUserId.value,
                    fiscal_year: fy.value,
                    profit_center_code: r.profit_center_code,
                    volume: vol,
                })
                r.id = data.id
                const newKey = r.id
                delete original[oldKey]
                r._key = newKey
                original[newKey] = vol
                dirty.delete(oldKey)
                dirty.delete(newKey)
            }
        }
    } catch (e) {
        error.value = e?.response?.data?.message || e?.message || 'Error guardando cambios'
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    if (effectiveUserId.value) fetchRows()
})
watch(
    () => route.fullPath,
    () => {
        // si cambió el :userId por navegación, refrescamos
        if (routeUserId.value) {
            localUserId.value = routeUserId.value
            fetchRows()
        }
    },
)
</script>

<style scoped>
.wrap {
    padding: 16px;
    display: grid;
    gap: 12px;
}
.bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.bar label {
    font-size: 0.85rem;
    color: #475569;
}
.bar input {
    padding: 6px 8px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    min-width: 120px;
}
.bar button {
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid #94a3b8;
    background: #f8fafc;
    cursor: pointer;
}
.bar button.primary {
    background: #111827;
    color: #fff;
    border-color: #111827;
}
.bar button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.spacer {
    flex: 1;
}

.table-wrap {
    position: relative;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}
.tbl {
    width: 100%;
    border-collapse: collapse;
    background: rgba(255, 255, 255, 0.92);
}
th,
td {
    padding: 10px;
    border-bottom: 1px solid #e2e8f0;
}
th {
    text-align: left;
    background: #f8fafc;
    font-weight: 700;
    font-size: 0.9rem;
    color: #334155;
}
.tc {
    text-align: center;
}
.vol {
    display: flex;
    align-items: center;
    gap: 8px;
}
.vol input {
    width: 140px;
    padding: 6px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}
.old {
    color: #64748b;
    font-size: 0.8rem;
}
.empty {
    text-align: center;
    color: #64748b;
    padding: 16px 0;
}

.err {
    color: #b91c1c;
    background: #fee2e2;
    border: 1px solid #fecaca;
    padding: 8px 10px;
    border-radius: 8px;
}

.overlay {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    gap: 8px;
    background: rgba(0, 0, 0, 0.08);
}
.spinner {
    width: 22px;
    height: 22px;
    border: 3px solid #e2e8f0;
    border-top-color: #111827;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>
