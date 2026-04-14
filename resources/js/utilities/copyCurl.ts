/**
 * Builds a curl command string from the given request parameters.
 * Each option is on its own line, joined with ` \` for readability.
 */
export function buildCurlCommand(
    method: string,
    url: string,
    headers: Record<string, string>,
    body?: string,
): string {
    const parts: string[] = [`curl -X ${method.toUpperCase()}`]

    for (const [key, value] of Object.entries(headers)) {
        parts.push(`  -H ${JSON.stringify(`${key}: ${value}`)}`)
    }

    if (body && body.trim()) {
        parts.push(`  -d ${JSON.stringify(body.trim())}`)
    }

    parts.push(`  ${JSON.stringify(url)}`)

    return parts.join(' \\\n')
}

export async function copyToClipboard(text: string): Promise<void> {
    if (navigator.clipboard) {
        await navigator.clipboard.writeText(text)
    } else {
        // Fallback for older browsers / insecure contexts
        const el = document.createElement('textarea')
        el.value = text
        el.style.position = 'fixed'
        el.style.opacity  = '0'
        document.body.appendChild(el)
        el.select()
        document.execCommand('copy')
        document.body.removeChild(el)
    }
}
