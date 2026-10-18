<?php

declare(strict_types=1);

namespace Wtroiano\MigrationPlan\Exceptions;

use RuntimeException;

final class InvalidMigrationPlan extends RuntimeException
{
    /** @param array<string, string> $details */
    public function __construct(string $message, private readonly array $details = [])
    {
        parent::__construct($message);
    }

    /** @return array<string, string> */
    public function context(): array
    {
        return $this->details;
    }
}
