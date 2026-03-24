import { ref } from 'vue'
import { useApi } from './useApi'
import type { RouteGroup } from '../types'

export function useRoutes() {
    const groups  = ref<RouteGroup[]>([])
    const loading = ref(false)
    const error   = ref<string | null>(null)

    async function fetchRoutes(): Promise<void> {
        loading.value = true
        error.value   = null

        try {
            const api     = useApi()
            groups.value  = await api.get<RouteGroup[]>('/routes')
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load routes'
        } finally {
            loading.value = false
        }
    }

    return { groups, loading, error, fetchRoutes }
}
