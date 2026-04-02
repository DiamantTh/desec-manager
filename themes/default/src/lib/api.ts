import type { RRset } from './types.js'

/**
 * Lädt alle RRsets für eine Domain vom PHP-Backend.
 * Endpoint: GET /api/domains/{domain}/records?key_id=…
 */
export async function fetchRRsets(domain: string, apiKeyId: number): Promise<RRset[]> {
    const params = new URLSearchParams({ key_id: String(apiKeyId) })
    const res = await fetch(`/api/domains/${encodeURIComponent(domain)}/records?${params}`, {
        headers: { Accept: 'application/json' },
    })

    if (!res.ok) {
        const body = await res.json().catch(() => ({}))
        throw new Error((body as { error?: string }).error ?? `HTTP ${res.status}`)
    }

    return res.json() as Promise<RRset[]>
}

/**
 * Lese Props die PHP als JSON in einem <script>-Tag einbettet.
 * <script id="<id>" type="application/json">{ … }</script>
 */
export function readPropsJson<T>(id: string, fallback: T): T {
    const el = document.getElementById(id)
    if (!el) return fallback
    try {
        return JSON.parse(el.textContent ?? '{}') as T
    } catch {
        return fallback
    }
}
