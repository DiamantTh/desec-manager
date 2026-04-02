<script lang="ts">
    import { fetchRRsets } from '../lib/api.js'
    import type { RRset } from '../lib/types.js'

    let { domain, apiKeyId }: { domain: string; apiKeyId: number } = $props()

    let rrsets  = $state<RRset[]>([])
    let loading = $state(true)
    let error   = $state<string | null>(null)

    $effect(() => {
        loading = true
        error   = null
        rrsets  = []

        fetchRRsets(domain, apiKeyId)
            .then(data => { rrsets = data })
            .catch(e  => { error  = e instanceof Error ? e.message : String(e) })
            .finally(() => { loading = false })
    })

    function labelFor(subname: string): string {
        return subname === '' ? '@' : subname
    }
</script>

<div class="records-app">

    {#if loading}
        <div class="has-text-centered py-5">
            <span class="icon is-large has-text-grey">
                <i class="fas fa-spinner fa-spin fa-2x"></i>
            </span>
            <p class="has-text-grey mt-2">{domain} — DNS-Einträge werden geladen …</p>
        </div>

    {:else if error}
        <div class="notification is-danger is-light">
            <strong>Fehler beim Laden der DNS-Einträge:</strong> {error}
        </div>

    {:else if rrsets.length === 0}
        <div class="notification is-info is-light">
            Keine DNS-Einträge für <strong>{domain}</strong> vorhanden.
        </div>

    {:else}
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Typ</th>
                        <th>TTL</th>
                        <th>Wert(e)</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {#each rrsets as rrset (rrset.subname + '/' + rrset.type)}
                        <tr>
                            <td><code>{labelFor(rrset.subname)}.{domain}</code></td>
                            <td><span class="tag is-info is-light">{rrset.type}</span></td>
                            <td>{rrset.ttl}</td>
                            <td>
                                {#each rrset.records as record}
                                    <div><code>{record}</code></div>
                                {/each}
                            </td>
                            <td>
                                <!-- TODO: Edit / Delete Actions -->
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    {/if}

</div>
