# Framework-Übersicht – deSEC Manager

Dieses Dokument beschreibt die aktuell verwendeten Frameworks und Tools, zeigt
wie sie zusammenarbeiten, und listet mögliche Ergänzungen/Alternativen auf.

---

## 1. Aktuelle Architektur (Überblick)

```
Browser ──HTTP──▶ index.php (Front-Controller)
                     │
                     ├─▶ ThemeManager (CSS / JS / Templates)
                     │
                     └─▶ Router (query-param ?route=xxx)
                              │
                              ├─▶ AuthController
                              ├─▶ DashboardController
                              ├─▶ DomainController
                              ├─▶ RecordController
                              ├─▶ KeyController
                              ├─▶ ProfileController
                              └─▶ AdminController
                                       │
                                  AbstractPageController
                                       │
                                  BaseController
                              ┌────────┴───────────┐
                    RepositoryLayer             DeSECClient (Guzzle)
                     (Doctrine DBAL)                 │
                          │                    deSEC.io REST-API
                    DatabaseConnection
                    (MySQL / SQLite)
```

---

## 2. Aktuelle Komponenten

### Backend – PHP

| Paket | Version | Zweck |
|-------|---------|-------|
| **PHP** | ≥ 8.1 (akt. 8.5) | Laufzeit |
| **Doctrine DBAL** | 3.7 | Datenbankabstraktion (MySQL + SQLite) |
| **Guzzle HTTP** | 7.8 | HTTP-Client für deSEC REST-API |
| Custom MVC | – | Eigener Front-Controller + PSR-4-Autoloader |
| Web-Authn (lbialy/php-webauthn) | – | Passkey-/FIDO2-Support *(im Code vorhanden, Paket fehlt in composer.json)* |

### Frontend – CSS / JS

| Paket | Version | Einbindung | Zweck |
|-------|---------|------------|-------|
| **Bulma** | 1.0.2 | CDN | CSS-Framework (responsive, flexbox-basiert) |
| Vanilla JS | – | lokal | Interaktivität (Alpine/React optional) |
| Dark-Mode-Script | – | inline `<head>` | Verhindert FOUC beim Theme-Wechsel |

### Theme-System

| Datei | Beschreibung |
|-------|-------------|
| `src/Service/ThemeManager.php` | Lädt `theme.json`, löst CSS/JS/Templates auf |
| `themes/default/` | Hellblau + Grün, Light/Dark, Bulma CDN |
| `themes/bulma/` | Standard-Bulma ohne Anpassungen |
| `themes/svelte/` | Konzept-Dokumentation für SvelteKit SPA |

---

## 3. PHP-Frameworks – Alternativen & Erweiterungen

Das Projekt nutzt ein **eigenes Micro-MVC** (kein Symfony/Laravel). Der Code
kann schrittweise auf schwerere Frameworks migriert oder durch leichtgewichtige
Router/DI-Container ergänzt werden.

### 3.1 Ohne Migration (Ergänzungen für das bestehende Projekt)

| Paket | Composer-Paket | Was es bringt |
|-------|---------------|---------------|
| **PHP-DI** | `php-di/php-di` | Dependency-Injection-Container; Controller via Type-Hint injecten |
| **FastRoute** | `nikic/fast-route` | Schneller Regex-Router statt `?route=xxx`-Query-Params |
| **Twig** | `twig/twig` | Template-Engine mit Auto-Escaping, Vererbung, Sandbox |
| **Plates** | `league/plates` | Leichte PHP-native Template-Engine (kein eigener Syntax) |
| **Monolog** | `monolog/monolog` | PSR-3-konformes Logging (Datei, Syslog, Slack, …) |
| **PHPMailer / Symfony Mailer** | `phpmailer/phpmailer` | E-Mail-Versand (Passwort-Reset etc.) |
| **lbialy/php-webauthn** | – | WebAuthn/FIDO2 (ist im Code vorhanden, fehlt in composer.json) |

**Empfehlung für sofortige Ergänzung**: FastRoute + PHP-DI lösen die aktuelle
`?route=xxx`-Schachtellogik und machen Controller testbar.

### 3.2 Leichtgewichtige PHP-Frameworks (Teil-Migration)

| Framework | composer.json | Stärken | Aufwand |
|-----------|---------------|---------|---------|
| **Slim 4** | `slim/slim` | PSR-15 Middleware, PSR-7 Request/Response, minimale Lernkurve | ★★☆ |
| **Leaf PHP** | `leafs/leaf` | Klein (~1MB), Express.js-ähnlich, gut für REST-APIs | ★☆☆ |
| **Mezzio / Laminas** | `mezzio/mezzio` | PSR-15-Middleware-Pipeline, Interop, Enterprise-ready | ★★★ |
| **Flight** | `mikecao/flight` | Minimaler Micro-Framework, kein Composer nötig | ★☆☆ |

### 3.3 Vollständige Frameworks (Komplettmigration)

| Framework | Stärken | Aufwand |
|-----------|---------|---------|
| **Symfony 7** | Doctrine bereits vorhanden, CLI-Tooling, große Community | ★★★★ |
| **Laravel 11** | Eloquent ORM (DBAL-Migration nötig), Blade, artisan | ★★★★ |
| **CakePHP 5** | Convention-over-Configuration, vollständig | ★★★ |

---

## 4. Frontend-Frameworks

### 4.1 CSS-Frameworks

| Framework | CDN | Bulma-Kompatibilität | Anmerkung |
|-----------|-----|---------------------|-----------|
| **Bulma 1.0** *(aktuell)* | ✓ | – | CSS-Variablen, keine JS-Abhängigkeit |
| **Bootstrap 5** | ✓ | Parallel möglich | Größer, mehr Komponenten |
| **Tailwind CSS 3/4** | Nur CDN-Play | Klassen vs. Bulma-Klassen | Templates müssten umgeschrieben werden |
| **Pico CSS** | ✓ | – | Semantisches HTML, minimales CSS, kein Class-Cluttering |
| **Skeleton UI** | ✓ | – | Für SvelteKit, Svelte-Komponenten |
| **daisyUI** | ✓ | – | Tailwind-Komponenten-Plugin, ähnlich wie Bulma |

### 4.2 JavaScript-Frameworks (sprinkles / SPA)

| Ansatz | Paket | Integration | Migrationspfad |
|--------|-------|-------------|----------------|
| **Vanilla JS** *(aktuell)* | – | Direct DOM | Keine Migration nötig |
| **Alpine.js** | CDN: `alpinejs` | `x-data` Attribut direkt in HTML | Einfachste Erweiterung; kein Build-Step |
| **HTMX** | CDN: `htmx.org` | `hx-get` / `hx-post` in HTML | Server-Rendered, kein JS-Schreiben nötig |
| **Stimulus** | npm: `@hotwired/stimulus` | Controller-Klassen für DOM-Behaviour | Kleinster Build-Schritt |
| **Vue 3** | CDN oder npm | Komponenten-SPA oder島 (Islands) | Mittlerer Build-Schritt |
| **SvelteKit** | npm: `@sveltejs/kit` | Vollständige SPA ↔ PHP JSON-API | Größter Aufwand; siehe `themes/svelte/README.md` |
| **React / Next.js** | npm: `next` | Vollständige SPA | Größter Aufwand, SSR möglich |

**Empfehlung**: Alpine.js oder HTMX sind die sinnvollsten nächsten Schritte –
sie funktionieren ohne Build-Pipeline direkt mit den bestehenden Bulma-Templates.

---

## 5. Zusammenspiel der Komponenten

```
HTTP-Request
    │
    ▼
index.php ──▶ ThemeManager ──▶ theme.json
    │              └──▶ CDN-Links (Bulma, etc.)
    │              └──▶ lokale CSS/JS-Dateien
    │
    ▼
Router (route-Whitelist)
    │
    ▼
Controller (extends AbstractPageController)
    │              ┌──────────────────┐
    ├──▶ Repository │ Doctrine DBAL    │
    │              │ MySQL / SQLite   │
    │              └──────────────────┘
    │              ┌──────────────────┐
    └──▶ DeSECClient│ Guzzle HTTP      │
                   │ deSEC.io REST-API│
                   └──────────────────┘
    │
    ▼
Template (PHP / Twig-fähig via ThemeManager)
    │
    ▼
HTML-Response mit Theme-CSS/JS
```

### Datenfluss: DNS-Record anlegen

```
Browser POST /index.php?route=records
    │
    ▼
RecordController::create()
    │── DomainRepository::findByUser()    (DBAL → DB)
    │── Input-Validierung (DomainValidator)
    └── DeSECProxyService::createRRSet()
             └── DeSECClient::post('/domains/{name}/rrsets/')
                      └── Guzzle → https://desec.io/api/v1/…
```

---

## 6. Empfohlene Upgrade-Pfade

### Kurzfristig (kein Breaking Change)

1. **FastRoute** einbinden → saubere URL-Pfade (`/dashboard`, `/domains`, etc.)
2. **PHP-DI** einbinden → Controller über DI-Container instanziieren
3. **Alpine.js** (CDN) → reaktive UI ohne Build-Step (Formular-Validierung, Modals)
4. **lbialy/php-webauthn** in `composer.json` eintragen (Code bereits vorhanden)

### Mittelfristig

5. **Twig** einbinden → Template-Vererbung, automatisches HTML-Escaping
6. **Slim 4** als Router-Schicht einziehen (bestehende Controller als Handler nutzbar)
7. **Monolog** für strukturiertes Logging

### Langfristig

8. Vollständige SvelteKit-SPA mit PHP als JSON-API-Backend  
   → Siehe `themes/svelte/README.md` für Architektur-Details
9. Symfony-Migration (Doctrine DBAL bereits vorhanden → Symfony DBAL-Bridge ≈ 0 Effort)

---

## 7. composer.json – bekannte Lücken

| Problem | Lösung |
|---------|--------|
| `lbialy/php-webauthn` fehlt, Code existiert | `composer require lbialy/php-webauthn` |
| `phpstan/phpstan` nur als Dev-Dep | ✓ korrekt |
| Kein PSR-7 / PSR-15 | Bei Slim-Migration: `composer require slim/psr7 slim/slim` |
| PHP-Versionsanforderung `>=8.1` | Passt, PHP 8.5 lokal verfügbar |
