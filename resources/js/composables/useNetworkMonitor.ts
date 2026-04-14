import { ref, onUnmounted } from 'vue'
import { useApi } from './useApi'

export interface NetworkEvent {
    id:          number
    method:      string
    path:        string
    query:       string | null
    status:      number | null
    duration_ms: number | null
    req_headers: Record<string, string>
    req_body:    string | null
    res_headers: Record<string, string>
    res_body:    string | null
    ip:          string | null
    created_at:  number
}

interface PollResponse {
    events: NetworkEvent[]
    cursor: number
    total:  number
}

const POLL_INTERVAL_MS = 600

export function useNetworkMonitor() {
    const api     = useApi()
    const events  = ref<NetworkEvent[]>([])
    const total   = ref(0)
    const enabled = ref(false)
    const active  = ref(false)

    let cursor  = 0
    let timerId: ReturnType<typeof setTimeout> | null = null

    async function checkConfig(): Promise<void> {
        try {
            const res = await api.get<{ enabled: boolean }>('network/config')
            enabled.value = res.enabled
        } catch {
            enabled.value = false
        }
    }

    async function poll(): Promise<void> {
        if (!active.value) return
        try {
            const res = await api.get<PollResponse>(`network/events?cursor=${cursor}`)
            if (res.events.length > 0) {
                events.value.push(...res.events)
                cursor = res.cursor
            }
            total.value = res.total
        } catch { /* silently ignore poll errors */ }

        timerId = setTimeout(poll, POLL_INTERVAL_MS)
    }

    function start(): void {
        if (active.value) return
        active.value = true
        cursor = 0
        poll()
    }

    function stop(): void {
        active.value = false
        if (timerId !== null) {
            clearTimeout(timerId)
            timerId = null
        }
    }

    async function clear(): Promise<void> {
        await api.del('network/events')
        events.value = []
        cursor = 0
        total.value  = 0
    }

    onUnmounted(stop)

    return { events, total, enabled, active, checkConfig, start, stop, clear }
}
