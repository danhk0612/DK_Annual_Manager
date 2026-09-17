<?php

declare(strict_types=1);

namespace DKAnnual\Repository;

use PDO;

abstract class AbstractRepository
{
    public function __construct(protected readonly PDO $pdo)
    {
    }
}
