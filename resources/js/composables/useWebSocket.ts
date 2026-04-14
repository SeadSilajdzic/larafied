import { ref, onUnmounted } from 'vue'
import { useApi } from './useApi'

export interface WsConfig {
    enabled:  boolean
    driver:   string
    host?:    string
    port?:    number
    scheme?:  string
    app_key?: string
    path?:    string
    cluster?: string
}

export interface WsEvent {
    id:        number
    ts:        number        // Date.now()
    channel:   string
    event:     string
    data:      unknown
    direction: 'in' | 'out' | 'system'
}

type ConnState = 'disconnected' | 'connecting' | 'connected' | 'error'

let eventCounter = 0

export function useWebSocket() {
    const api          = useApi()
    const wsConfig     = ref<WsConfig | null>(null)
    const connState    = ref<ConnState>('disconnected')
    const connError    = ref<string | null>(null)
    const socketId     = ref<string | null>(null)
    const eventLog     = ref<WsEvent[]>([])
    const subscribed   = ref<Set<string>>(new Set())

    let ws: WebSocket | null = null

    async function loadConfig(): Promise<void> {
        try {
            wsConfig.value = await api.get<WsConfig>('ws/config')
        } catch {
            wsConfig.value = { enabled: false, driver: 'unknown' }
        }
    }

    function buildWsUrl(cfg: WsConfig): string {
        const scheme = cfg.scheme === 'https' ? 'wss' : 'ws'
        const port   = cfg.port ?? (scheme === 'wss' ? 443 : 80)
        const key    = cfg.app_key ?? ''
        const path   = (cfg.path ?? '/app').replace(/\/$/, '')
        return `${scheme}://${cfg.host}:${port}${path}/${key}?protocol=7&client=larafied&version=1.0.0`
    }

    function pushEvent(channel: string, event: string, data: unknown, dir: WsEvent['direction'] = 'in'): void {
        eventLog.value.push({
            id:        ++eventCounter,
            ts:        Date.now(),
            channel,
            event,
            data,
            direction: dir,
        })
        // Keep at most 200 events in memory
        if (eventLog.value.length > 200) {
            eventLog.value.splice(0, eventLog.value.length - 200)
        }
    }

    function connect(): void {
        if (!wsConfig.value?.enabled) return
        if (ws) disconnect()

        connState.value = 'connecting'
        connError.value = null

        try {
            ws = new WebSocket(buildWsUrl(wsConfig.value))
        } catch (e) {
            connState.value = 'error'
            connError.value = e instanceof Error ? e.message : 'Invalid URL'
            return
        }

        ws.onopen = () => {
            // Pusher protocol: server sends connection_established on open
        }

        ws.onmessage = (e) => {
            let msg: { event: string; data?: unknown; channel?: string }
            try {
                msg = JSON.parse(e.data as string)
            } catch {
                return
            }

            const channel = msg.channel ?? ''
            const event   = msg.event ?? ''

            // Parse inner data JSON (Pusher wraps it as a string)
            let data = msg.data
            if (typeof data === 'string') {
                try { data = JSON.parse(data) } catch { /* keep as string */ }
            }

            if (event === 'pusher:connection_established') {
                connState.value = 'connected'
                socketId.value  = (data as { socket_id?: string })?.socket_id ?? null
                pushEvent('', event, data, 'system')
                // Re-subscribe to channels that were active before reconnect
                for (const ch of subscribed.value) {
                    sendSubscribe(ch)
                }
                return
            }

            if (event === 'pusher:error') {
                connState.value = 'error'
                connError.value = (data as { message?: string })?.message ?? 'WS error'
                pushEvent('', event, data, 'system')
                return
            }

            pushEvent(channel, event, data, 'in')
        }

        ws.onerror = () => {
            connState.value = 'error'
            connError.value = 'WebSocket connection error.'
        }

        ws.onclose = (e) => {
            if (connState.value !== 'error') {
                connState.value = 'disconnected'
            }
            if (!e.wasClean) {
                connError.value = connError.value ?? `Closed (code ${e.code})`
            }
            socketId.value = null
            ws = null
        }
    }

    function disconnect(): void {
        ws?.close()
        ws             = null
        connState.value = 'disconnected'
        connError.value = null
        socketId.value  = null
    }

    function sendSubscribe(channel: string): void {
        if (!ws || ws.readyState !== WebSocket.OPEN) return
        ws.send(JSON.stringify({
            event: 'pusher:subscribe',
            data:  { channel },
        }))
        subscribed.value.add(channel)
        subscribed.value = new Set(subscribed.value)
        pushEvent(channel, 'pusher:subscribe', { channel }, 'out')
    }

    function sendUnsubscribe(channel: string): void {
        if (!ws || ws.readyState !== WebSocket.OPEN) return
        ws.send(JSON.stringify({
            event: 'pusher:unsubscribe',
            data:  { channel },
        }))
        subscribed.value.delete(channel)
        subscribed.value = new Set(subscribed.value)
        pushEvent(channel, 'pusher:unsubscribe', { channel }, 'out')
    }

    function clearLog(): void {
        eventLog.value = []
    }

    onUnmounted(disconnect)

    return {
        wsConfig,
        connState,
        connError,
        socketId,
        eventLog,
        subscribed,
        loadConfig,
        connect,
        disconnect,
        sendSubscribe,
        sendUnsubscribe,
        clearLog,
    }
}
