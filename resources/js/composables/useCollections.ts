import { ref } from 'vue'
import { useApi } from './useApi'
import type { Collection, SavedRequest } from '../types'

interface CollectionsResponse {
    data: Collection[]
    meta: { count: number; limit: number; limit_reached: boolean }
}

export function useCollections() {
    const collections = ref<Collection[]>([])
    const loading     = ref(false)
    const limitReached = ref(false)

    async function fetchCollections(): Promise<void> {
        loading.value = true

        try {
            const api       = useApi()
            const res       = await api.get<CollectionsResponse>('/collections')
            collections.value  = res.data
            limitReached.value = res.meta.limit_reached
        } finally {
            loading.value = false
        }
    }

    async function createCollection(name: string, description?: string): Promise<Collection> {
        const api        = useApi()
        const collection = await api.post<Collection>('/collections', { name, description })
        await fetchCollections()
        return collection
    }

    async function updateCollection(id: string, name: string, description?: string): Promise<Collection> {
        const api        = useApi()
        const collection = await api.put<Collection>(`/collections/${id}`, { name, description })
        await fetchCollections()
        return collection
    }

    async function deleteCollection(id: string): Promise<void> {
        const api = useApi()
        await api.del(`/collections/${id}`)
        collections.value = collections.value.filter((c) => c.id !== id)
    }

    async function saveRequest(
        collectionId: string,
        payload: { name: string; data: object },
    ): Promise<SavedRequest> {
        const api   = useApi()
        const saved = await api.post<SavedRequest>(`/collections/${collectionId}/requests`, payload)
        await fetchCollections()
        return saved
    }

    async function deleteRequest(requestId: string): Promise<void> {
        const api = useApi()
        await api.del(`/requests/${requestId}`)
        await fetchCollections()
    }

    return {
        collections,
        loading,
        limitReached,
        fetchCollections,
        createCollection,
        updateCollection,
        deleteCollection,
        saveRequest,
        deleteRequest,
    }
}
