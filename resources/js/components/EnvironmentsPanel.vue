<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useEnvironments } from '../composables/useEnvironments'
import type { EnvVariable } from '../types'

const emit = defineEmits<{ upgrade: [] }>()

const {
    environments, active, loading, locked,
    fetchEnvironments, createEnvironment, updateEnvironment, deleteEnvironment, activateEnvironment,
} = useEnvironments()

// ─── Create ───────────────────────────────────────────────────────────────────
const creating   = ref(false)
const newEnvName = ref('')

async function submitCreate(): Promise<void> {
    if (!newEnvName.value.trim()) return
    await createEnvironment(newEnvName.value.trim())
    newEnvName.value = ''
    creating.value   = false
}

// ─── Expand / Edit ────────────────────────────────────────────────────────────
const expandedEnvId = ref<string | null>(null)
const editName      = ref('')
const editVars      = ref<EnvVariable[]>([])

function toggleExpand(env: { id: string; name: string; variables: EnvVariable[] }): void {
    if (expandedEnvId.value === env.id) {
        expandedEnvId.value = null
        return
    }
    expandedEnvId.value = env.id
    editName.value      = env.name
    editVars.value      = env.variables.map(v => ({ ...v }))
    if (editVars.value.length === 0) {
        editVars.value.push({ key: '', value: '', secret: false })
    }
}

function cancelEdit(): void {
    expandedEnvId.value = null
}

function addVar(): void {
    editVars.value.push({ key: '', value: '', secret: false })
}

function removeVar(i: number): void {
    editVars.value.splice(i, 1)
}

async function saveEdit(): Promise<void> {
    if (!expandedEnvId.value) return
    const vars = editVars.value.filter(v => v.key.trim())
    await updateEnvironment(expandedEnvId.value, editName.value.trim(), vars)
    expandedEnvId.value = null
}

async function handleDelete(id: string, e: MouseEvent): Promise<void> {
    e.stopPropagation()
    if (expandedEnvId.value === id) expandedEnvId.value = null
    await deleteEnvironment(id)
}

onMounted(() => fetchEnvironments())
</script>

<template>
    <div class="flex flex-col h-full overflow-hidden">

        <!-- Locked -->
        <div v-if="locked" class="flex flex-col items-center justify-center flex-1 gap-3 px-4">
            <svg class="w-6 h-6 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
            </svg>
            <p class="text-xs text-gray-500 text-center">Environments require a Pro license.</p>
            <button
                class="px-3 py-1.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white rounded transition-colors"
                @click="emit('upgrade')"
            >Upgrade to Pro</button>
        </div>

        <!-- Loading -->
        <div v-else-if="loading" class="flex flex-1 items-center justify-center text-gray-600 text-xs">
            <span class="animate-pulse">Loading…</span>
        </div>

        <template v-else>
            <!-- Header -->
            <div class="flex items-center justify-between px-3 py-2 border-b border-gray-800 shrink-0">
                <span class="text-xs text-gray-500">
                    {{ environments.length }} environment{{ environments.length !== 1 ? 's' : '' }}
                    <span v-if="active" class="text-indigo-400 ml-1">· {{ active.name }} active</span>
                </span>
                <button
                    class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors"
                    @click="creating = true; newEnvName = ''"
                >+ New</button>
            </div>

            <!-- Create form -->
            <div v-if="creating" class="px-3 py-2 border-b border-gray-800 shrink-0">
                <input
                    v-model="newEnvName"
                    type="text"
                    placeholder="Environment name"
                    class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs font-mono text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 mb-1.5"
                    autofocus
                    @keydown.enter="submitCreate"
                    @keydown.esc="creating = false"
                />
                <div class="flex gap-2">
                    <button class="text-xs px-2 py-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded transition-colors" @click="submitCreate">Save</button>
                    <button class="text-xs px-2 py-1 text-gray-500 hover:text-gray-300 transition-colors" @click="creating = false">Cancel</button>
                </div>
            </div>

            <!-- Empty -->
            <div v-if="!environments.length && !creating" class="flex flex-1 items-center justify-center text-gray-700 text-xs px-4 text-center">
                No environments yet.<br>Create one to use variables.
            </div>

            <!-- Env list -->
            <div class="flex-1 overflow-y-auto">
                <div
                    v-for="env in environments"
                    :key="env.id"
                    class="border-b border-gray-800/50"
                    :class="env.is_active ? 'border-l-2 border-indigo-500' : 'border-l-2 border-transparent'"
                >
                    <!-- Env row -->
                    <div
                        class="flex items-center gap-2 px-2.5 py-2.5 hover:bg-gray-800/30 transition-colors cursor-pointer group"
                        @click="toggleExpand(env)"
                    >
                        <!-- Active dot -->
                        <button
                            class="w-2.5 h-2.5 rounded-full border shrink-0 transition-all"
                            :class="env.is_active
                                ? 'bg-indigo-500 border-indigo-400'
                                : 'bg-transparent border-gray-600 hover:border-indigo-400'"
                            :title="env.is_active ? 'Active environment' : 'Click to activate'"
                            @click.stop="activateEnvironment(env.id)"
                        />

                        <!-- Name -->
                        <span
                            class="flex-1 text-xs font-medium truncate"
                            :class="env.is_active ? 'text-white' : 'text-gray-300'"
                        >{{ env.name }}</span>

                        <!-- Var count badge -->
                        <span
                            v-if="env.variables.length"
                            class="text-xs text-gray-600 bg-gray-800/60 rounded px-1.5 py-0.5 shrink-0 tabular-nums"
                        >{{ env.variables.length }}</span>

                        <!-- Delete (hover) -->
                        <button
                            class="p-0.5 text-gray-700 hover:text-red-400 transition-colors shrink-0 opacity-0 group-hover:opacity-100"
                            title="Delete environment"
                            @click="handleDelete(env.id, $event)"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>

                        <!-- Chevron -->
                        <svg
                            class="w-3 h-3 text-gray-600 transition-transform shrink-0"
                            :class="expandedEnvId === env.id ? '' : '-rotate-90'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    <!-- Variable table -->
                    <div v-if="expandedEnvId === env.id" class="px-3 pb-3 bg-gray-900/30">
                        <!-- Name edit -->
                        <input
                            v-model="editName"
                            type="text"
                            class="w-full bg-gray-900 border border-gray-700 rounded px-2 py-1.5 text-xs font-medium text-gray-200 placeholder-gray-600 focus:outline-none focus:border-indigo-500 mt-2 mb-2"
                            @keydown.esc="cancelEdit"
                        />

                        <!-- Column headers -->
                        <div class="grid gap-1 mb-1 px-0.5" style="grid-template-columns: 1fr 1fr 24px 24px">
                            <span class="text-xs text-gray-600 uppercase tracking-wide">Key</span>
                            <span class="text-xs text-gray-600 uppercase tracking-wide">Value</span>
                            <span/>
                            <span/>
                        </div>

                        <!-- Variable rows -->
                        <div
                            v-for="(v, i) in editVars"
                            :key="i"
                            class="grid gap-1 mb-1"
                            style="grid-template-columns: 1fr 1fr 24px 24px"
                        >
                            <input
                                v-model="v.key"
                                type="text"
                                placeholder="KEY"
                                class="bg-gray-900 border border-gray-700/60 rounded px-2 py-1 text-xs font-mono text-indigo-300 placeholder-gray-700 focus:outline-none focus:border-indigo-500 min-w-0"
                            />
                            <input
                                v-model="v.value"
                                :type="v.secret ? 'password' : 'text'"
                                placeholder="value"
                                class="bg-gray-900 border border-gray-700/60 rounded px-2 py-1 text-xs font-mono text-gray-200 placeholder-gray-700 focus:outline-none focus:border-indigo-500 min-w-0"
                            />
                            <button
                                class="flex items-center justify-center rounded transition-colors"
                                :class="v.secret ? 'text-amber-400' : 'text-gray-600 hover:text-gray-400'"
                                :title="v.secret ? 'Mark as plain text' : 'Mark as secret'"
                                @click="v.secret = !v.secret"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <template v-if="!v.secret">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </template>
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                            <button
                                class="flex items-center justify-center text-gray-700 hover:text-red-400 transition-colors text-sm leading-none"
                                @click="removeVar(i)"
                            >×</button>
                        </div>

                        <button
                            class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors mt-1 mb-3"
                            @click="addVar"
                        >+ Add variable</button>

                        <div class="flex gap-2 pt-2 border-t border-gray-800">
                            <button
                                class="text-xs px-2.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded transition-colors"
                                @click="saveEdit"
                            >Save</button>
                            <button
                                class="text-xs px-2.5 py-1.5 text-gray-500 hover:text-gray-300 transition-colors"
                                @click="cancelEdit"
                            >Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
