import { ref } from 'vue'
import { useApi } from './useApi'

interface LicenseInfo {
    tier: string
    features: string[]
    grace_warning: boolean
    validated_at: string | null
    grace_until: string | null
}

export function useLicense() {
    const tier         = ref<string>('free')
    const features     = ref<string[]>([])
    const graceWarning = ref<boolean>(false)
    const loading      = ref<boolean>(false)
    const error        = ref<string | null>(null)

    const api = useApi()

    async function fetchLicense(): Promise<void> {
        loading.value = true
        error.value   = null

        try {
            const data = await api.get<LicenseInfo>('/license')
            tier.value         = data.tier
            features.value     = data.features
            graceWarning.value = data.grace_warning
        } catch {
            // fail silently — defaults to free tier
        } finally {
            loading.value = false
        }
    }

    async function activate(key: string, domain?: string): Promise<void> {
        loading.value = true
        error.value   = null

        try {
            const data = await api.post<LicenseInfo & { reason?: string }>('/license/activate', {
                key,
                domain,
            })

            if ('reason' in data && data.reason) {
                error.value = data.reason
                return
            }

            tier.value     = data.tier
            features.value = data.features
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Activation failed.'
        } finally {
            loading.value = false
        }
    }

    return { tier, features, graceWarning, loading, error, fetchLicense, activate }
}
