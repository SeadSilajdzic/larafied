import { ref } from 'vue'
import { useApi } from './useApi'
import type { SqlTable } from '../types'

export function useSqlTables() {
    const tables  = ref<SqlTable[]>([])
    const loading = ref(false)
    const error   = ref<string | null>(null)

    const api = useApi()

    async function fetchTables(connection?: string): Promise<void> {
        loading.value = true
        error.value   = null

        try {
            const params = connection ? `?connection=${encodeURIComponent(connection)}` : ''
            const data   = await api.get<{ tables: SqlTable[] } | { error: string }>(`/sql/tables${params}`)

            if ('error' in data) {
                error.value = data.error
            } else {
                tables.value = data.tables
            }
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Failed to load tables.'
        } finally {
            loading.value = false
        }
    }

    return { tables, loading, error, fetchTables }
}
