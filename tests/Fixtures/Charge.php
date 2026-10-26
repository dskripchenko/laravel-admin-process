<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Fixtures;

use Dskripchenko\ProcessEngine\Attributes\AtMostOnce;

/**
 * Charges once; a timeout leaves the outcome unknown (no reconcile), which
 * opens an incident for an operator.
 */
#[AtMostOnce]
final class Charge
{
    public static bool $timeout = false;

    public function __invoke(array $context): string
    {
        if (self::$timeout) {
            throw new \RuntimeException('Read timed out');
        }

        return 'ch_1';
    }
}
