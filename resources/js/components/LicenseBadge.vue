<script setup lang="ts">
defineProps<{ tier: string }>()

const labels: Record<string, string> = {
    free:   'Free',
    pro:    'Pro',
    team:   'Team',
    agency: 'Agency',
}

const colours: Record<string, string> = {
    free:   'bg-gray-700 text-gray-300',
    pro:    'bg-indigo-600 text-white',
    team:   'bg-violet-600 text-white',
    agency: 'bg-amber-600 text-white',
}
</script>

<template>
    <span
        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
        :class="colours[tier] ?? colours.free"
    >
        {{ labels[tier] ?? tier }}
    </span>
</template>
