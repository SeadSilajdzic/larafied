import { ref } from 'vue'
import { useApi } from './useApi'

export interface HistoryEntry {
    id:          string
    method:      string
    url:         string
    headers:     Record<string, string>
    body:        string | null
    status:      number | null
    duration_ms: number | null
    created_at:  number
}

export function useHistory() {
    const entries = ref<HistoryEntry[]>([])
    const loading = ref(false)
    const locked  = ref(false)

    const api = useApi()

    async function fetchHistory(): Promise<void> {
        loading.value = true
        locked.value  = false

        try {
            const data = await api.get<{ data: HistoryEntry[] } | { upgrade: boolean }>('/history')

            if ('upgrade' in data) {
                locked.value = true
                return
            }

            entries.value = data.data
        } catch {
            // fail silently
        } finally {
            loading.value = false
        }
    }

    async function clearHistory(): Promise<void> {
        try {
            await api.del('/history')
            entries.value = []
        } catch {
            // fail silently
        }
    }

    function prepend(entry: HistoryEntry): void {
        entries.value = [entry, ...entries.value].slice(0, 50)
    }

    return { entries, loading, locked, fetchHistory, clearHistory, prepend }
}
