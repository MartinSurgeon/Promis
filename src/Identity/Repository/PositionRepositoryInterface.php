<?php

declare(strict_types=1);

namespace Promis\Src\Identity\Repository;

use Promis\Src\Identity\Domain\Model\StaffPosition;

interface PositionRepositoryInterface
{
    /**
     * @return StaffPosition[]
     */
    public function findAllActive(): array;

    public function findById(int $id): ?StaffPosition;

    public function findByCode(string $code): ?StaffPosition;
}
