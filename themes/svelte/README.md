# SvelteKit Theme — deSEC Manager

## Konzept

Dieses Theme ersetzt die PHP-Template-Schicht vollständig durch eine **SvelteKit-SPA**.
Das PHP-Backend wird dabei als **JSON-REST-API** verwendet.

```
┌─────────────────────────────────────────┐
│             Browser                     │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │  SvelteKit SPA (dieses Theme)    │  │
│  │  Port 5173 (dev) / dist/ (prod)  │  │
│  └─────────────────┬─────────────────┘  │
│                    │ fetch() JSON-API    │
└────────────────────┼────────────────────┘
                     │
         ┌───────────▼────────────┐
         │  PHP-Backend (API)      │
         │  index.php?route=api/*  │
         │  deSEC Manager src/     │
         └────────────────────────┘
```

## Voraussetzungen

- Node.js >= 18
- npm oder pnpm
- PHP-Backend mit aktiviertem API-Modus (siehe unten)

## Schnellstart

```bash
# 1. In das Theme-Verzeichnis wechseln
cd themes/svelte/app

# 2. Abhängigkeiten installieren
npm install

# 3. Entwicklungsserver starten
npm run dev
# → http://localhost:5173

# 4. Produktions-Build
npm run build
# → Ausgabe in themes/svelte/app/build/
```

## PHP-Backend für API-Modus aktivieren

In `config/config.php` folgendes ergänzen:

```php
'api' => [
    'enabled' => true,
    'cors_origins' => ['http://localhost:5173'],
],
```

## App-Grundstruktur

```
themes/svelte/app/
  src/
    lib/
      api.ts          ← API-Client (fetch Wrapper)
      stores.ts       ← Svelte Stores (user, domains …)
    routes/
      +layout.svelte  ← Haupt-Layout (Navbar, Footer)
      +page.svelte    ← Dashboard
      login/
        +page.svelte
      domains/
        +page.svelte
      records/[domain]/
        +page.svelte
      keys/
        +page.svelte
      profile/
        +page.svelte
      admin/
        +page.svelte
    app.css           ← Globale Styles (importiert Theme)
  package.json
  svelte.config.js
  vite.config.ts
```

## CSS-Framework im SvelteKit-Theme

Das SvelteKit-Theme ist unabhängig von Bulma. Empfohlene Optionen:

| Option         | Beschreibung                              | Größe   |
|----------------|-------------------------------------------|---------|
| **Skeleton UI** | Tailwind-basiert, Svelte-native           | ~80 KB  |
| **daisyUI**    | Tailwind-Components, Dark-Mode nativ      | ~30 KB  |
| **Shoelace**   | Web Components, framework-unabhängig      | ~75 KB  |
| **Pico CSS**   | Semantisches HTML, kein JS benötigt       | ~10 KB  |
| Eigene CSS    | Voll kontrollierbar, unser Design-Token-System | variabel |

## Vorgesehene Features

- [x] Dokumentation (diese Datei)
- [ ] SvelteKit-App-Scaffold erstellen
- [ ] API-Route-Erweiterungen im PHP-Backend
- [ ] Authentication-Flow (JWT oder Session-Cookie)
- [ ] Dark-Mode mit daisyUI-Themes
- [ ] PWA-Manifest

## Integration in das Theme-System

Das SvelteKit-Theme wird **nicht** über ThemeManager geladen (da kein PHP-Template-Rendering).
Stattdessen wird die SvelteKit-App eigenständig deployed und der PHP-Backend-Router
liefert ausschließlich JSON-Antworten wenn `Accept: application/json` gesetzt ist.

Der PHP-Router erkennt API-Anfragen über:
- Query-Parameter `?api=1`
- `Accept: application/json` Header
- Route-Präfix `?route=api/...`
