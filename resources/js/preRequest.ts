/**
 * Pre-request script runner.
 *
 * Executes a user-supplied JavaScript snippet before a request is sent.
 * The script receives a `pm` object (Postman-compatible subset) that can:
 *   - Read/write the request URL, method, headers, and body
 *   - Read/write environment variables
 *
 * The script runs synchronously in a new Function scope with no access to
 * the global window/document — only the `pm` object is in scope.
 */

export interface PreRequestContext {
    method:  string
    url:     string
    headers: Record<string, string>
    body:    string
    envVars: Record<string, string>
}

export interface PreRequestResult {
    method:  string
    url:     string
    headers: Record<string, string>
    body:    string
    envVars: Record<string, string>
    error?:  string
}

export function runPreRequestScript(script: string, ctx: PreRequestContext): PreRequestResult {
    if (!script.trim()) {
        return { ...ctx, headers: { ...ctx.headers }, envVars: { ...ctx.envVars } }
    }

    // Mutable copies — the script mutates these via pm.*
    const reqState = {
        method:  ctx.method,
        url:     ctx.url,
        headers: { ...ctx.headers },
        body:    ctx.body,
    }
    const envState: Record<string, string> = { ...ctx.envVars }

    const pm = {
        request: reqState,
        environment: {
            get: (key: string): string => envState[key] ?? '',
            set: (key: string, value: string): void => { envState[key] = String(value) },
        },
    }

    try {
        // eslint-disable-next-line no-new-func
        const fn = new Function('pm', script)
        fn(pm)
    } catch (e) {
        const msg = e instanceof SyntaxError
            ? `Script error: ${e.message}`
            : (e instanceof Error ? e.message : String(e))
        return { ...ctx, headers: { ...ctx.headers }, envVars: { ...ctx.envVars }, error: msg }
    }

    return {
        method:  reqState.method,
        url:     reqState.url,
        headers: reqState.headers,
        body:    reqState.body,
        envVars: envState,
    }
}
