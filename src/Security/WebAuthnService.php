<?php

declare(strict_types=1);

namespace App\Security;

use Cose\Algorithm\Manager as AlgorithmManager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\EdDSA\Ed25519;
use Cose\Algorithm\Signature\RSA\RS256;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;
use App\Entity\WebAuthnCredential;
use App\Session\SessionContext;

/**
 * WebAuthnService — production-ready FIDO2/WebAuthn implementation via webauthn-lib v5.
 *
 * Registration (2 steps):
 *   1. generateRegistrationOptions()  → sends PublicKeyCredentialCreationOptions JSON to the browser
 *   2. verifyRegistration()           → validates the authenticator response, returns WebAuthnCredential
 *
 * Authentication (2 steps):
 *   1. generateAuthenticationOptions() → schickt PublicKeyCredentialRequestOptions JSON an Browser
 *   2. verifyAuthentication()          → validiert die Authenticator-Antwort, liefert aktualisierten Source
 *
 * Die Challenge wird zwischen Schritt 1 und 2 in $_SESSION['webauthn_options'] als JSON gespeichert.
 *
 * Sicherheitsparameter (aus security.toml):
 *   - userVerification = "required"   → PIN oder Biometrie zwingend
 *   - residentKey      = "required"   → Passkey/Discoverable Credential
 *   - attestation      = "direct"     → AAGUID-Identifikation
 *   - algorithms       = [-8, -7, -257] → Ed25519, ES256, RS256
 */
class WebAuthnService
{
    private readonly string $rpName;
    private readonly string $rpId;
    private readonly string $origin;
    private readonly string $userVerification;
    private readonly string $residentKey;
    private readonly string $attestation;
    private readonly int $challengeBytes;
    private readonly int $timeoutMs;
    /** @var list<int> */
    private readonly array $algorithms;

    /**
     * @param array<string, mixed> $config  Full TOML configuration array (keys 'app' and 'security')
     */
    public function __construct(array $config, private readonly SessionContext $sessionContext)
    {
        $appCfg    = $config['app']                   ?? [];
        $fidoCfg   = $config['security']['fido2']     ?? [];

        $domain = (string)($appCfg['domain'] ?? '');
        if ($domain === '') {
            throw new \RuntimeException(
                'app.domain muss in config/config.toml konfiguriert sein (wird für WebAuthn RP-ID benötigt).'
            );
        }

        $this->rpName          = (string)($appCfg['name']             ?? 'DeSEC Manager');
        $this->rpId            = $domain;
        $this->origin          = 'https://' . $domain;
        $this->userVerification = (string)($fidoCfg['user_verification']   ?? 'required');
        $this->residentKey     = ($fidoCfg['require_resident_key'] ?? true) ? 'required' : 'preferred';
        $this->attestation     = (string)($fidoCfg['attestation']          ?? 'direct');
        $this->challengeBytes  = max(16, (int)($fidoCfg['challenge_bytes'] ?? 32));
        $this->timeoutMs       = (int)($fidoCfg['timeout_ms']              ?? 60000);

        /** @var list<int> $algs */
        $algs = $fidoCfg['algorithms'] ?? [-8, -7, -257];
        /** @var list<int> $algList */
        $algList = array_map('intval', (array)$algs);
        $this->algorithms = $algList;
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Step 1 of registration: generate options for the browser.
     *
     * The returned array can be sent directly to the browser via json_encode().
     * Die kompletten Optionen werden in $_SESSION['webauthn_options'] gespeichert.
     *
     * @param list<WebAuthnCredential> $excludeCredentials  Bereits registrierte Keys dieses Nutzers
     * @return array<string, mixed>
     */
    public function generateRegistrationOptions(
        string $username,
        string $userHandle,
        array $excludeCredentials = []
    ): array {
        $challenge = random_bytes($this->challengeBytes);

        $rp   = PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId);
        $user = PublicKeyCredentialUserEntity::create($username, $userHandle, $username);

        $params = array_map(
            fn(int $alg): PublicKeyCredentialParameters => PublicKeyCredentialParameters::createPk($alg),
            $this->algorithms
        );

        $exclude = array_map(
            fn(WebAuthnCredential $c): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Base64UrlSafe::decode($c->getCredentialId()),
                $c->getTransports()
            ),
            $excludeCredentials
        );

        $criteria = AuthenticatorSelectionCriteria::create(
            null,
            $this->userVerification,
            $this->residentKey
        );

        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rp,
            user: $user,
            challenge: $challenge,
            pubKeyCredParams: $params,
            authenticatorSelection: $criteria,
            attestation: $this->attestation,
            excludeCredentials: $exclude,
            timeout: max(1, $this->timeoutMs),
        );

        $serializer = $this->buildSerializer();
        $optionsJson = $serializer->serialize($options, 'json');

        $this->storeInSession('webauthn_options', $optionsJson);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($optionsJson, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }

    /**
     * Schritt 2 der Registrierung: Authenticator-Antwort verifizieren.
     *
     * @param string $credentialName  User-assigned name for this key
     * @param string $browserJson     Die rohe JSON-Antwort vom Browser (navigator.credentials.create())
     * @return WebAuthnCredential     Fully populated entity — must still be persisted
     * @throws \RuntimeException      If session data is missing or attestation is invalid
     */
    public function verifyRegistration(string $credentialName, string $browserJson): WebAuthnCredential
    {
        $optionsJson = $this->loadFromSession('webauthn_options');

        $serializer = $this->buildSerializer();

        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = $serializer->deserialize($browserJson, PublicKeyCredential::class, 'json');

        /** @var PublicKeyCredentialCreationOptions $options */
        $options = $serializer->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class, 'json');

        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Ungültige Authenticator-Antwort: kein Attestation-Response.');
        }

        $stepManager = $this->buildCeremonyStepManagerFactory()->creationCeremony();
        $validator   = AuthenticatorAttestationResponseValidator::create($stepManager);

        $source = $validator->check($response, $options, $this->rpId);

        $cred = new WebAuthnCredential($credentialName, 0);
        $cred->setCredentialId(Base64UrlSafe::encodeUnpadded($source->publicKeyCredentialId));
        $cred->setPublicKeyCbor($source->credentialPublicKey);
        $cred->setSignCount($source->counter);
        $cred->setAttestationType($source->attestationType);
        $aaguidStr = $source->aaguid->toRfc4122();
        $cred->setAaguid($aaguidStr === '00000000-0000-0000-0000-000000000000' ? null : $aaguidStr);
        /** @var list<string> $transports */
        $transports = array_values($source->transports);
        $cred->setTransports($transports);
        $cred->setUvInitialized($source->uvInitialized ?? false);
        $cred->setBackupEligible($source->backupEligible ?? false);
        $cred->setBackupState($source->backupStatus ?? false);

        $this->clearFromSession('webauthn_options');

        return $cred;
    }

    /**
     * Step 1 of authentication: generate options for the browser.
     *
     * @param list<WebAuthnCredential> $storedCredentials  Alle aktiven Keys des Nutzers
     * @return array<string, mixed>
     */
    public function generateAuthenticationOptions(array $storedCredentials = []): array
    {
        $challenge = random_bytes($this->challengeBytes);

        $allowCredentials = array_map(
            fn(WebAuthnCredential $c): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                Base64UrlSafe::decode($c->getCredentialId()),
                $c->getTransports()
            ),
            $storedCredentials
        );

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId,
            allowCredentials: $allowCredentials,
            userVerification: $this->userVerification,
            timeout: max(1, $this->timeoutMs),
        );

        $serializer  = $this->buildSerializer();
        $optionsJson = $serializer->serialize($options, 'json');

        $this->storeInSession('webauthn_options', $optionsJson);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($optionsJson, true, 512, JSON_THROW_ON_ERROR);
        return $decoded;
    }

    /**
     * Schritt 2 der Authentifizierung: Authenticator-Antwort verifizieren.
     *
     * The returned source contains the updated signature counter and backup status
     * und muss vom Aufrufer in der Datenbank persistiert werden (sign_count, backup_state).
     *
     * @param string           $browserJson      Rohe JSON-Antwort vom Browser (navigator.credentials.get())
     * @param WebAuthnCredential $storedCredential Das passende gespeicherte Credential aus der DB
     * @param string           $userHandle       Binary user handle from the database
     * @return PublicKeyCredentialSource          Aktualisierter Credential-Source (sign_count, backup_state aktuell)
     * @throws \RuntimeException                  Bei fehlender Session, falschem Credential-Typ oder Validierungsfehler
     */
    public function verifyAuthentication(
        string $browserJson,
        WebAuthnCredential $storedCredential,
        string $userHandle
    ): PublicKeyCredentialSource {
        $optionsJson = $this->loadFromSession('webauthn_options');

        $serializer = $this->buildSerializer();

        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = $serializer->deserialize($browserJson, PublicKeyCredential::class, 'json');

        /** @var PublicKeyCredentialRequestOptions $options */
        $options = $serializer->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');

        $response = $publicKeyCredential->response;
        if (!$response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Ungültige Authenticator-Antwort: kein Assertion-Response.');
        }

        $source = $this->buildSourceFromCredential($storedCredential, $userHandle);

        $stepManager = $this->buildCeremonyStepManagerFactory()->requestCeremony();
        $validator   = AuthenticatorAssertionResponseValidator::create($stepManager);

        $updatedSource = $validator->check($source, $response, $options, $this->rpId, $userHandle);

        $this->clearFromSession('webauthn_options');

        return $updatedSource;
    }

    // =========================================================================
    // Interne Hilfsmethoden
    // =========================================================================

    /**
     * Baut die CeremonyStepManagerFactory mit den konfigurierten Algorithmen und Origins.
     */
    private function buildCeremonyStepManagerFactory(): CeremonyStepManagerFactory
    {
        $algManager = AlgorithmManager::create();
        foreach ($this->algorithms as $alg) {
            match ($alg) {
                -8   => $algManager->add(Ed25519::create()),
                -7   => $algManager->add(ES256::create()),
                -257 => $algManager->add(RS256::create()),
                default => null,
            };
        }

        $factory = new CeremonyStepManagerFactory();
        $factory->setAlgorithmManager($algManager);
        $factory->setAllowedOrigins([$this->origin]);

        return $factory;
    }

    /**
     * Builds the Symfony serializer for WebAuthn objects via WebauthnSerializerFactory.
     */
    private function buildSerializer(): \Symfony\Component\Serializer\SerializerInterface
    {
        $algManager = AlgorithmManager::create()->add(
            Ed25519::create(),
            ES256::create(),
            RS256::create()
        );

        $attestationManager = AttestationStatementSupportManager::create([
            NoneAttestationStatementSupport::create(),
            PackedAttestationStatementSupport::create($algManager),
        ]);

        return (new WebauthnSerializerFactory($attestationManager))->create();
    }

    /**
     * Rekonstruiert einen PublicKeyCredentialSource aus der gespeicherten WebAuthnCredential-Entity.
     * Required for assertion validation.
     */
    private function buildSourceFromCredential(
        WebAuthnCredential $credential,
        string $userHandle
    ): PublicKeyCredentialSource {
        $aaguid = $credential->getAaguid() !== null
            ? Uuid::fromString($credential->getAaguid())
            : Uuid::fromString('00000000-0000-0000-0000-000000000000');

        return PublicKeyCredentialSource::create(
            publicKeyCredentialId: Base64UrlSafe::decode($credential->getCredentialId()),
            type:                  PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports:            $credential->getTransports(),
            attestationType:       $credential->getAttestationType(),
            trustPath:             EmptyTrustPath::create(),
            aaguid:                $aaguid,
            credentialPublicKey:   $credential->getPublicKeyCbor(),
            userHandle:            $userHandle,
            counter:               $credential->getSignCount(),
            backupEligible:        $credential->isBackupEligible(),
            backupStatus:          $credential->isBackupState(),
            uvInitialized:         $credential->isUvInitialized(),
        );
    }

    // =========================================================================
    // Interne Session-Hilfsmethoden (via SessionContext)
    // =========================================================================

    private function storeInSession(string $key, string $value): void
    {
        $this->sessionContext->set($key, $value);
    }

    private function loadFromSession(string $key): string
    {
        $value = $this->sessionContext->get($key);
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(
                "Fehlende WebAuthn-Session-Daten ('{$key}'). " .
                'Die Session könnte abgelaufen oder manipuliert worden sein.'
            );
        }
        return $value;
    }

    private function clearFromSession(string $key): void
    {
        $this->sessionContext->unset($key);
    }
}
