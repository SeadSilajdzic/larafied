import type { Assertion, AssertionResult, ProxyResponse } from '../types'

export function evaluateAssertions(
    assertions: Assertion[],
    response: ProxyResponse,
): AssertionResult[] {
    return assertions.map(a => evaluate(a, response))
}

function evaluate(assertion: Assertion, response: ProxyResponse): AssertionResult {
    switch (assertion.type) {
        case 'status_equals': {
            const expected = parseInt(assertion.value, 10)
            const passed   = response.status === expected
            return {
                assertion,
                passed,
                message: `Expected status ${expected}, got ${response.status}`,
            }
        }

        case 'body_contains': {
            const passed = response.body.includes(assertion.value)
            return {
                assertion,
                passed,
                message: `Response body does not contain "${assertion.value}"`,
            }
        }

        case 'json_path_equals': {
            let json: unknown
            try {
                json = JSON.parse(response.body)
            } catch {
                return { assertion, passed: false, message: 'Response body is not valid JSON' }
            }
            const actual = resolveDotPath(json, assertion.key ?? '')
            const passed = String(actual) === assertion.value
            return {
                assertion,
                passed,
                message: `Expected ${assertion.key ?? '(root)'} = "${assertion.value}", got "${actual ?? 'undefined'}"`,
            }
        }

        case 'header_equals': {
            const targetKey = (assertion.key ?? '').toLowerCase()
            const headerKey = Object.keys(response.headers).find(
                k => k.toLowerCase() === targetKey,
            )
            const actual = headerKey !== undefined ? response.headers[headerKey] : undefined
            const passed = actual === assertion.value
            return {
                assertion,
                passed,
                message: `Expected header "${assertion.key}" = "${assertion.value}", got "${actual ?? 'not set'}"`,
            }
        }

        default:
            return { assertion, passed: false, message: 'Unknown assertion type' }
    }
}

function resolveDotPath(obj: unknown, path: string): unknown {
    if (!path) return obj
    return path.split('.').reduce((cur: unknown, key: string) => {
        if (cur === null || cur === undefined) return undefined
        return (cur as Record<string, unknown>)[key]
    }, obj)
}
