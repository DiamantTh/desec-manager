import { mount } from 'svelte'
import App from './App.svelte'
import { readPropsJson } from '../lib/api.js'
import type { RecordsProps } from '../lib/types.js'

const el = document.getElementById('svelte-records')

if (el) {
    const props = readPropsJson<RecordsProps>('svelte-records-props', {
        domain:   '',
        apiKeyId: 0,
    })

    mount(App, { target: el, props })
}
