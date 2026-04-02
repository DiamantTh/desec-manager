<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Session\SessionContext;
use App\Service\Translator;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Session\SessionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * SessionContextMiddleware — verbindet mezzio/mezzio-session mit der App.
 *
 * Läuft NACH Mezzio\Session\SessionMiddleware (welche die Session startet
 * und als Request-Attribut hinterlegt).
 *
 * Aufgaben:
 *   1. SessionContext mit der laufenden SessionInterface initialisieren
 *   2. Locale aus POST (_locale) oder Session ermitteln und in Session schreiben
 *   3. Accept-Language-Header als Fallback auswerten
 *   4. Laminas Translator mit der ermittelten Locale konfigurieren
 */
class SessionContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionContext $sessionContext,
        private readonly \Laminas\I18n\Translator\Translator $translator,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 1. SessionInterface aus Request-Attribut holen (von mezzio-session gesetzt)
        $session = $request->getAttribute(SessionInterface::class);
        if ($session instanceof SessionInterface) {
            $this->sessionContext->initialize($session);
        }

        // 2. Locale-Wechsel via POST (_locale=de_DE)
        // Locale setzen und sofort per GET-Redirect auf dieselbe URL weiterleiten,
        // damit der nachgelagerte Handler kein "unbekanntes" POST sieht.
        $body = $request->getParsedBody();
        if (is_array($body)
            && isset($body['_locale'])
            && isset(Translator::SUPPORTED_LOCALES[$body['_locale']])
        ) {
            $this->sessionContext->setLocale((string) $body['_locale']);

            // Locale in Translator setzen, bevor wir weiterleiten
            $locale = $this->resolveLocale($request);
            $this->translator->setLocale($locale);
            Translator::setLaminasTranslator($this->translator);

            // POST-Redirect-GET: verhindert, dass Handler ein _locale-POST verarbeitet
            return new RedirectResponse((string) $request->getUri(), 303);
        }

        // 3. Locale ermitteln (Session → Accept-Language → Fallback en_US)
        $locale = $this->resolveLocale($request);

        // 4. Translator konfigurieren
        $this->translator->setLocale($locale);
        Translator::setLaminasTranslator($this->translator);

        return $handler->handle($request);
    }

    private function resolveLocale(ServerRequestInterface $request): string
    {
        // Session-Locale hat Priorität
        if ($this->sessionContext->has('locale')) {
            $stored = $this->sessionContext->getLocale();
            if (isset(Translator::SUPPORTED_LOCALES[$stored])) {
                return $stored;
            }
        }

        // Accept-Language-Header auswerten
        $header = $request->getHeaderLine('Accept-Language');
        if ($header !== '') {
            foreach (explode(',', $header) as $part) {
                $lang = trim(explode(';', trim($part))[0]);
                $lang = str_replace('-', '_', $lang);
                foreach (array_keys(Translator::SUPPORTED_LOCALES) as $locale) {
                    if (str_starts_with(strtolower($locale), strtolower($lang))) {
                        return $locale;
                    }
                }
            }
        }

        return 'en_US';
    }
}
