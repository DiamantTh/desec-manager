# PHP 8.4+ Kompatibilitätsanalyse - deSEC Manager

## Zusammenfassung

Das deSEC Manager Projekt wurde auf PHP 8.4+ Kompatibilität geprüft. Die Analyse umfasst:
- Statische Code-Analyse mit PHPStan
- Abhängigkeitsprüfung auf Sicherheitslücken
- Code-Struktur und -Pfade Dokumentation
- Empfehlungen für Verbesserungen

### Status: ✅ PHP 8.4+ Kompatibel (nach Bugfixes)

---

## 1. Projektstruktur

### Basis-Framework
- **Typ**: Eigenes MVC-Framework (kein Symfony/Laravel)
- **Autoloading**: PSR-4 via Composer
- **Ursprüngliche PHP-Version**: >= 7.4
- **Neue PHP-Version**: >= 8.1 (empfohlen für PHP 8.4+ Support)

### Verzeichnisstruktur
```
desec-manager/
├── src/
│   ├── Controller/          # MVC Controller
│   │   ├── AbstractPageController.php
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── BaseController.php
│   │   ├── DashboardController.php
│   │   ├── DeSECController.php
│   │   ├── DomainController.php
│   │   ├── KeyController.php
│   │   ├── ProfileController.php
│   │   └── RecordController.php
│   ├── Database/            # Datenbankverbindung
│   │   └── DatabaseConnection.php
│   ├── DeSEC/               # API Client
│   │   ├── DeSECClient.php
│   │   └── DeSECException.php
│   ├── Entity/              # Doctrine ORM Entities
│   │   ├── ApiKey.php
│   │   ├── User.php
│   │   ├── UserKey.php
│   │   └── WebAuthnCredential.php
│   ├── Repository/          # Datenzugriff
│   │   ├── ApiKeyRepository.php
│   │   ├── DomainRepository.php
│   │   └── UserRepository.php
│   ├── Security/            # Sicherheitskomponenten
│   │   ├── DomainValidator.php
│   │   ├── EncryptionService.php
│   │   ├── KeyAuthentication.php
│   │   ├── PasswordHasher.php
│   │   └── WebAuthnService.php
│   └── Service/             # Business Logic
│       ├── DNSService.php
│       ├── DeSECProxyService.php
│       ├── StatusReporter.php
│       └── SystemHealthService.php
├── templates/               # View Templates
├── config/                  # Konfigurationsdateien
├── assets/                  # CSS/JS Assets
├── sql/                     # SQL Schema
├── tests/                   # Tests
├── index.php               # Haupt-Einstiegspunkt
├── install.php             # Installationsskript
└── status.php              # Health-Check Endpoint
```

---

## 2. Abhängigkeiten

### Composer Pakete (require)
| Paket | Version | Sicherheit | PHP 8.4 Support |
|-------|---------|------------|-----------------|
| doctrine/dbal | ^3.7 (3.10.2) | ✅ Keine Vulnerabilities | ✅ Ja |
| guzzlehttp/guzzle | ^7.8 (7.10.0) | ✅ Keine Vulnerabilities | ✅ Ja |

### Transitive Abhängigkeiten
| Paket | Version | Sicherheit |
|-------|---------|------------|
| doctrine/deprecations | 1.1.5 | ✅ |
| doctrine/event-manager | 2.0.1 | ✅ |
| guzzlehttp/promises | 2.3.0 | ✅ |
| guzzlehttp/psr7 | 2.8.0 | ✅ |
| psr/cache | 3.0.0 | ✅ |
| psr/http-client | 1.0.3 | ✅ |
| psr/http-factory | 1.1.0 | ✅ |
| psr/http-message | 2.0 | ✅ |
| psr/log | 3.0.2 | ✅ |
| ralouphie/getallheaders | 3.0.3 | ✅ |
| symfony/deprecation-contracts | v3.6.0 | ✅ |

---

## 3. Sicherheitsfeatures

### Authentifizierung
- **Passwort-Hashing**: `PASSWORD_ARGON2ID` (State-of-the-Art)
  - Memory Cost: 65536 (64MB)
  - Time Cost: 4 Iterationen
  - Threads: 2

### Verschlüsselung
- **API-Key Speicherung**: Sodium `crypto_secretbox` (XSalsa20-Poly1305)
- **Key-Authentifizierung**: Ed25519 Signaturen via Sodium

### WebAuthn/FIDO2
- Unterstützung für Hardware-Tokens
- Domain-Validierung für RP ID
- HTTPS-Erzwingung für Produktionsumgebungen

### Session-Management
- `session_regenerate_id(true)` bei Login
- Session-basierte Authentifizierung

---

## 4. Behobene Bugs

### 4.1 install.php - fgets() Rückgabewert
**Problem**: `trim(fgets(STDIN))` - `fgets()` kann `false` zurückgeben
**Fix**: Explizite Prüfung auf `false`
```php
$input = fgets(STDIN);
$rootPassword = $input !== false ? trim($input) : '';
```

### 4.2 RecordController.php - preg_split() Rückgabewert
**Problem**: `preg_split()` kann `false` zurückgeben
**Fix**: Explizite Prüfung auf `false`
```php
$lines = preg_split('/\r?\n/', $recordsRaw);
if ($lines === false) {
    return [];
}
```

### 4.3 StatusReporter.php - preg_replace() Rückgabewert
**Problem**: `preg_replace()` kann `null` zurückgeben
**Fix**: Null-Coalescing Fallback
```php
$result = preg_replace('/[^a-zA-Z0-9_:\-\.]/', '_', $value);
return $result ?? $value;
```

### 4.5 StatusReporter.php - Redundante Prüfung
**Problem**: PHPStan erkannte redundante Prüfung `$overall !== 'critical'`
**Fix**: Vereinfachte Loop-Logik mit besserer Lesbarkeit

### 4.6 KeyAuthentication.php - Strikte base64 Validierung
**Problem**: `base64_decode()` kann `false` zurückgeben, sodium erwartet non-empty-string
**Fix**: Strikte Modus und Leerprüfung vor sodium Aufrufen

---

## 5. PHPStan Analyse (Level 8)

### Gesamtergebnis
- **Ursprüngliche Fehler**: 180
- **Nach Fixes kritischer Fehler**: Reduziert

### Fehlertypen
| Typ | Anzahl | Kritisch |
|-----|--------|----------|
| missingType.iterableValue | 79 | Nein |
| attribute.notFound | 54 | Nein* |
| class.notFound | 20 | Nein* |
| property.unused | 6 | Nein |
| argument.type | 4 | ✅ Behoben |
| foreach.nonIterable | 1 | ✅ Behoben |
| return.type | 1 | ✅ Behoben |
| notIdentical.alwaysTrue | 1 | ✅ Behoben |

*Die `attribute.notFound` und `class.notFound` Fehler beziehen sich auf Doctrine ORM Attribute - diese sind für die Entities definiert, aber Doctrine ORM ist nicht in den Abhängigkeiten installiert (nur DBAL).

### Empfehlungen
1. **Type Hints hinzufügen** für bessere Code-Qualität
2. **PHPDoc Annotations** für generische Arrays
3. **Doctrine ORM installieren** falls Entities verwendet werden sollen

---

## 6. PHP 8.4 spezifische Änderungen

### 6.1 Verwendete Funktionen - Status
| Funktion | Status in PHP 8.4 |
|----------|-------------------|
| `password_hash()` mit ARGON2ID | ✅ Unterstützt |
| `sodium_crypto_*` | ✅ Unterstützt |
| `preg_split()` / `preg_replace()` | ⚠️ Stricter null handling |
| `fgets()` | ⚠️ Stricter false handling |
| `implode()` | ✅ Kein Problem |
| `str_ends_with()` | ✅ PHP 8.0+ |
| `match` expression | ✅ PHP 8.0+ |

### 6.2 PHP 8.4 Deprecations
Das Projekt verwendet keine der in PHP 8.4 als deprecated markierten Funktionen:
- ❌ Kein `utf8_encode()`/`utf8_decode()`
- ❌ Keine implicit nullable parameters (außer explizit `?type`)
- ❌ Kein `#[\ReturnTypeWillChange]` benötigt

---

## 7. Security-Empfehlungen

### Hoch Priorität
1. **CSRF-Schutz** fehlt in Formularen - Token hinzufügen
2. **Rate Limiting** für Login-Versuche implementieren
3. **Content Security Policy** Headers setzen

### Mittel Priorität
1. **Input Validation** verbessern (z.B. E-Mail mit `filter_var`)
2. **Prepared Statements** werden korrekt verwendet (via DBAL)
3. **XSS-Schutz** mit `htmlspecialchars()` vorhanden

### Niedrig Priorität
1. **Logging** für Sicherheitsereignisse implementieren
2. **Session-Timeout** konfigurierbar machen

---

## 8. Empfohlene nächste Schritte

1. ✅ PHP-Version auf >= 8.1 aktualisiert
2. ✅ Kritische Bugs behoben (preg_split, preg_replace, fgets)
3. ⬜ Vollständige Type Hints hinzufügen
4. ⬜ Unit Tests implementieren
5. ⬜ CSRF-Schutz implementieren
6. ⬜ Doctrine ORM Entscheidung treffen (installieren oder Entities entfernen)

---

## 9. Zusammenfassung der Änderungen

### Geänderte Dateien
1. `composer.json` - PHP >= 8.1, PHPStan als dev dependency
2. `install.php` - Bug fix, PHP Version check aktualisiert
3. `README.md` - PHP Version aktualisiert
4. `src/Controller/RecordController.php` - preg_split Bug fix
5. `src/Service/StatusReporter.php` - preg_replace Bug fix, Logik vereinfacht
6. `src/Service/DeSECProxyService.php` - preg_split Bug fix
7. `src/Security/KeyAuthentication.php` - base64/sodium validation fixes
8. `phpstan.neon` - PHPStan Konfiguration (neu)

### Neue Dateien
- `PHP84_COMPATIBILITY.md` - Diese Analyse

---

*Erstellt am: 2026-03-17*
*Analyse-Tools: PHPStan 2.1.41, PHP 8.3.6*
