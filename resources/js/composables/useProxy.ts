import { ref } from 'vue'
import { useApi } from './useApi'
import type { ProxyResponse } from '../types'

interface ProxyError {
    error: string
    type: string
}

interface ProxySendParams {
    method: string
    url: string
    headers?: Record<string, string>
    body?: string
}

function isProxyError(result: unknown): result is ProxyError {
    return typeof result === 'object' && result !== null && 'error' in result
}

export function useProxy() {
    const response = ref<ProxyResponse | null>(null)
    const sending  = ref(false)
    const error    = ref<string | null>(null)

    async function send(params: ProxySendParams): Promise<void> {
        sending.value  = true
        error.value    = null
        response.value = null

        try {
            const api    = useApi()
            const result = await api.post<ProxyResponse | ProxyError>('/proxy', params)

            if (isProxyError(result)) {
                error.value = result.error
            } else {
                response.value = result
            }
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Request failed'
        } finally {
            sending.value = false
        }
    }

    return { response, sending, error, send }
}
