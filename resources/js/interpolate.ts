/**
 * Replace {{variable}} placeholders in a string with values from the given map.
 * Unresolved variables are left as-is (e.g. {{MISSING}} stays {{MISSING}}).
 */
export function interpolate(text: string, vars: Record<string, string>): string {
    return text.replace(/\{\{(\w+)\}\}/g, (_, key: string) => vars[key] ?? `{{${key}}}`)
}
