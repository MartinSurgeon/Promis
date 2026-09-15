<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

/**
 * Interface for querying planning entities and system roles for IAM views.
 */
interface PlanningEntityRepositoryInterface
{
    public function findAllActive(): array;

    public function findById(int $id): ?array;

    public function findAllActiveRoles(): array;

    public function findRoleById(int $id): ?array;
}
