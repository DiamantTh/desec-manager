<?php

declare(strict_types=1);

namespace App\Service;

use App\Session\SessionContext;
use Laminas\Permissions\Rbac\Rbac;
use Laminas\Permissions\Rbac\Role;

/**
 * AuthorizationService — RBAC-basierte Berechtigungsprüfung.
 *
 * Rollen-Hierarchie:
 *   admin  → erbt alle Berechtigungen von "user"
 *   user   → eingeschränkte Berechtigungen
 *
 * Berechtigungen (Strings nach dem Schema "{ressource}.{aktion}"):
 *
 *   user:
 *     domains.view, domains.manage
 *     records.view, records.manage
 *     keys.view, keys.manage
 *     profile.view, profile.manage
 *
 *   admin (zusätzlich):
 *     admin.view, admin.manage
 *     users.view, users.manage
 *
 * Verwendung im Handler:
 *   if (!$this->authz->can('admin.view')) { return 403; }
 *
 * Die Berechtigungsliste ist bewusst fest kodiert — für eine DNS-Verwaltungsapplikation
 * dieser Größe ist dynamisches RBAC per DB deutlich mehr Komplexität als Nutzen.
 * Erweiterbar durch Hinzufügen weiterer Berechtigungen in PERMISSIONS.
 */
final class AuthorizationService
{
    private readonly Rbac $rbac;

    /** @var array<string, string[]> Berechtigungen pro Rolle */
    private const PERMISSIONS = [
        'user' => [
            'domains.view',
            'domains.manage',
            'records.view',
            'records.manage',
            'keys.view',
            'keys.manage',
            'profile.view',
            'profile.manage',
        ],
        'admin' => [
            'admin.view',
            'admin.manage',
            'users.view',
            'users.manage',
        ],
    ];

    public function __construct(
        private readonly SessionContext $sessionContext,
    ) {
        $this->rbac = $this->buildRbac();
    }

    /**
     * Prüft, ob der aktuelle Nutzer die angegebene Berechtigung besitzt.
     */
    public function can(string $permission): bool
    {
        $role = $this->currentRole();
        return $this->rbac->isGranted($role, $permission);
    }

    /**
     * Gibt die aktuelle Rolle zurück ('admin' oder 'user').
     * Gibt '' zurück wenn nicht eingeloggt (alle Prüfungen schlagen fehl).
     */
    public function currentRole(): string
    {
        if (!(bool) $this->sessionContext->get('user_id', 0)) {
            return '';
        }
        return (bool) $this->sessionContext->get('is_admin', false) ? 'admin' : 'user';
    }

    /**
     * Gibt true zurück wenn der Nutzer eingeloggt und Admin ist.
     */
    public function isAdmin(): bool
    {
        return $this->currentRole() === 'admin';
    }

    /**
     * Gibt true zurück wenn der Nutzer eingeloggt ist (beliebige Rolle).
     */
    public function isAuthenticated(): bool
    {
        return $this->currentRole() !== '';
    }

    // -------------------------------------------------------------------------
    // Intern
    // -------------------------------------------------------------------------

    private function buildRbac(): Rbac
    {
        $rbac = new Rbac();
        $rbac->setCreateMissingRoles(true);

        // user-Rolle mit Basis-Berechtigungen
        $userRole = new Role('user');
        foreach (self::PERMISSIONS['user'] as $permission) {
            $userRole->addPermission($permission);
        }

        // admin-Rolle erbt user und erhält zusätzliche Berechtigungen
        $adminRole = new Role('admin');
        $adminRole->addChild($userRole);
        foreach (self::PERMISSIONS['admin'] as $permission) {
            $adminRole->addPermission($permission);
        }

        $rbac->addRole($userRole);
        $rbac->addRole($adminRole);

        return $rbac;
    }
}
