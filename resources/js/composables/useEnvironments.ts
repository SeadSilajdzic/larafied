import { ref, computed } from 'vue'
import { useApi } from './useApi'
import type { Environment, EnvVariable } from '../types'

export function useEnvironments() {
    const environments = ref<Environment[]>([])
    const loading      = ref(false)
    const locked       = ref(false)

    const active = computed(() =>
        environments.value.find(e => e.is_active) ?? null,
    )

    const api = useApi()

    async function fetchEnvironments(): Promise<void> {
        loading.value = true
        locked.value  = false

        try {
            const data = await api.get<Environment[] | { upgrade: boolean }>('/environments')

            if (!Array.isArray(data) && 'upgrade' in data) {
                locked.value = true
                return
            }

            environments.value = data as Environment[]
        } catch {
            // fail silently
        } finally {
            loading.value = false
        }
    }

    async function createEnvironment(name: string, variables: EnvVariable[] = []): Promise<Environment | null> {
        try {
            const env = await api.post<Environment>('/environments', { name, variables })
            environments.value.push(env)
            return env
        } catch {
            return null
        }
    }

    async function updateEnvironment(id: string, name: string, variables: EnvVariable[]): Promise<void> {
        try {
            const env = await api.put<Environment>(`/environments/${id}`, { name, variables })
            const idx = environments.value.findIndex(e => e.id === id)
            if (idx !== -1) environments.value[idx] = env
        } catch {
            // fail silently
        }
    }

    async function deleteEnvironment(id: string): Promise<void> {
        try {
            await api.del(`/environments/${id}`)
            environments.value = environments.value.filter(e => e.id !== id)
        } catch {
            // fail silently
        }
    }

    async function activateEnvironment(id: string): Promise<void> {
        try {
            await api.post(`/environments/${id}/activate`)
            environments.value = environments.value.map(e => ({
                ...e,
                is_active: e.id === id,
            }))
        } catch {
            // fail silently
        }
    }

    return {
        environments,
        active,
        loading,
        locked,
        fetchEnvironments,
        createEnvironment,
        updateEnvironment,
        deleteEnvironment,
        activateEnvironment,
    }
}
