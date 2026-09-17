<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

interface UserResponsibilityRepositoryInterface
{
    /**
     * @return string[]
     */
    public function getActiveResponsibilityCodes(int $userId, ?int $planningEntityId = null): array;

    public function hasResponsibility(int $userId, string $responsibilityCode, ?int $planningEntityId = null): bool;

    /**
     * @param string[] $responsibilityCodes
     */
    public function syncUserResponsibilities(
        int $userId,
        int $planningEntityId,
        array $responsibilityCodes,
        int $actorUserId
    ): void;

    /**
     * @return array<int, array<string>> Keyed by user_id
     */
    public function getGroupedResponsibilitiesByUserIds(array $userIds): array;
}
