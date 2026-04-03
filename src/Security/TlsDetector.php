<?php

declare(strict_types=1);

namespace App\Security;

use Psr\Http\Message\ServerRequestInterface;

/**
 * TlsDetector — erkennt ob die aktuelle Verbindung über TLS/HTTPS läuft.
 *
 * Unterstützt:
 *   1. Direkte HTTPS-Verbindung (PSR-7 URI-Schema, PHP SAPI HTTPS-Variable)
 *   2. Reverse-Proxy-Betrieb (nur wenn trust_proxy = true konfiguriert ist)
 *      — X-Forwarded-Proto: https   (nginx, HAProxy, AWS ALB, Cloudflare)
 *      — X-Forwarded-SSL: on        (Apache mod_ssl hinter Proxy)
 *      — X-Forwarded-HTTPS: 1       (ältere Setups)
 *
 * SICHERHEITSHINWEIS: Proxy-Header können von Clients beliebig gesetzt werden.
 * trust_proxy NUR aktivieren wenn ein vertrauenswürdiger Reverse Proxy vorgelagert
 * ist und externe Clients keinen direkten Zugriff auf PHP haben.
 *
 * Ebenfalls: Ermittlung der tatsächlichen Client-IP hinter Proxy.
 */
final class TlsDetector
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * Gibt true zurück wenn die Verbindung über TLS/HTTPS läuft.
     * Berücksichtigt Proxy-Header nur wenn trust_proxy = true konfiguriert ist.
     */
    public function isSecure(ServerRequestInterface $request): bool
    {
        // 1. PSR-7 URI-Schema (z.B. gesetzt von Webserver/SAPI)
        if ($request->getUri()->getScheme() === 'https') {
            return true;
        }

        // 2. PHP SAPI-Variable (HTTPS = 'on' bei direktem HTTPS via mod_php/FPM)
        $serverParams = $request->getServerParams();
        $httpsVar     = strtolower((string)($serverParams['HTTPS'] ?? ''));
        if ($httpsVar === 'on' || $httpsVar === '1') {
            return true;
        }

        // 3. Proxy-Header — auswerten NUR wenn explizit vertraust
        if (!(bool)($this->config['security']['trust_proxy'] ?? false)) {
            return false;
        }

        // X-Forwarded-Proto (Standard-Header, unterstützt von nginx, HAProxy,
        // AWS ALB, Cloudflare, Traefik, Caddy etc.)
        $proto = strtolower($request->getHeaderLine('X-Forwarded-Proto'));
        if ($proto === 'https') {
            return true;
        }

        // X-Forwarded-SSL: on (Apache mod_proxy + mod_ssl)
        $fwdSsl = strtolower($request->getHeaderLine('X-Forwarded-SSL'));
        if ($fwdSsl === 'on') {
            return true;
        }

        // X-Forwarded-HTTPS: 1 (ältere Proxy-Setups)
        $fwdHttps = $request->getHeaderLine('X-Forwarded-HTTPS');
        if ($fwdHttps === '1' || strtolower($fwdHttps) === 'on') {
            return true;
        }

        return false;
    }

    /**
     * Gibt die tatsächliche Client-IP zurück.
     * Berücksichtigt X-Forwarded-For / X-Real-IP nur wenn trust_proxy = true.
     *
     * X-Forwarded-For hat das Format: "client, proxy1, proxy2"
     * → erste IP ist der eigentliche Client.
     */
    public function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();

        if ((bool)($this->config['security']['trust_proxy'] ?? false)) {
            // X-Forwarded-For (Standard, alle gängigen Proxies)
            $forwarded = $request->getHeaderLine('X-Forwarded-For');
            if ($forwarded !== '') {
                $parts = array_map('trim', explode(',', $forwarded));
                $ip    = $parts[0];
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }

            // X-Real-IP (nginx-spezifisch — setzt nur eine IP, direkt den Client)
            $realIp = $request->getHeaderLine('X-Real-IP');
            if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP) !== false) {
                return $realIp;
            }
        }

        return (string)($serverParams['REMOTE_ADDR'] ?? '');
    }
}
