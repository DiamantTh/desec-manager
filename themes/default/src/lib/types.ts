// Typen entsprechend deSEC-API-Rückgaben

export interface RRset {
    subname: string
    type:    string
    ttl:     number
    records: string[]
    touched?: string
}

export interface RecordsProps {
    domain:   string
    apiKeyId: number
}
