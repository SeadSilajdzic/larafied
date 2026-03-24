import { createApp } from 'vue'
import App from './App.vue'
import type { LarafiedConfig } from './types'
import './app.css'

const el = document.getElementById('app')

if (!el) {
    throw new Error('[Larafied] Mount element #app not found.')
}

const raw    = el.getAttribute('data-config') ?? '{}'
const config = JSON.parse(raw) as LarafiedConfig

createApp(App, { config }).mount(el)
