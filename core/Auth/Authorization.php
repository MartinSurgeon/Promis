<?php

declare(strict_types=1);

namespace Promis\Core\Auth;

use Promis\Core\Exception\AuthorizationException;

/**
 * Server-Side Authorization Guard.
 * Enforces atomic permission checks and entity-scoped RBAC boundaries with a strict Deny-by-Default rule.
 */
final class Authorization
{
    /**
     * Determine whether the active authenticated user has a specific permission.
     * Denies by default if unauthenticated or if permission is absent.
     */
    public static function allows(string $permission, ?int $entityId = null): bool
    {
        $user = AuthManager::user();
        if ($user === null) {
            return false; // Deny by default for unauthenticated requests
        }

        // 1. Check direct global permissions attached to session user
        $userPermissions = $user['permissions'] ?? [];
        $roles = $user['roles'] ?? [];
        $isAdmin = in_array('ADMIN', $roles, true) || in_array('SUPER_ADMIN', $roles, true);

        // 2. If target entity is specified, check entity-scoped permissions (or admin override)
        if ($entityId !== null) {
            if ($isAdmin && is_array($userPermissions) && in_array($permission, $userPermissions, true)) {
                return true;
            }

            if (isset($user['entity_permissions']) && is_array($user['entity_permissions'])) {
                if (isset($user['entity_permissions'][$entityId])) {
                    $scopedPerms = $user['entity_permissions'][$entityId];
                    return is_array($scopedPerms) && in_array($permission, $scopedPerms, true);
                }
                return false;
            }

            if (isset($user['entity_id']) && (int)$user['entity_id'] === $entityId) {
                return is_array($userPermissions) && in_array($permission, $userPermissions, true);
            }

            if (!isset($user['entity_permissions']) && !isset($user['entity_id'])) {
                return is_array($userPermissions) && in_array($permission, $userPermissions, true);
            }

            return false;
        }

        // 3. When no entity is specified, check direct global permissions
        if (is_array($userPermissions) && in_array($permission, $userPermissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Enforce a permission check; throws AuthorizationException on failure.
     */
    public static function authorize(string $permission, ?int $entityId = null): void
    {
        if (!self::allows($permission, $entityId)) {
            throw new AuthorizationException("Access denied. Required permission '{$permission}' is not granted for this scope.");
        }
    }
}
