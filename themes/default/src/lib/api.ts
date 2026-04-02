import type { RRset } from './types.js'

/**
 * Lädt alle RRsets für eine Domain vom PHP-Backend.
 * PHP-Endpoint: ?route=api/records&domain=…&key=…
 * (Endpoint wird separat implementiert.)
 */
export async function fetchRRsets(domain: string, apiKeyId: number): Promise<RRset[]> {
    const params = new URLSearchParams({
        route:  'api/records',
        domain,
        key:    String(apiKeyId),
    })

    const res = await fetch(`?${params}`, {
        headers: { Accept: 'application/json' },
    })

    if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${res.statusText}`)
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
