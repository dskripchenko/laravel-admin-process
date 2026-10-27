<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Fixtures;

use Dskripchenko\ProcessEngine\Batch\ItemSource;

/** Keys 1…n of the context. */
final class Numbers implements ItemSource
{
    /** @return list<int> */
    public function keys(array $context, ?string $after, int $limit): array
    {
        $from = $after === null ? 1 : (int) $after + 1;

        return $from > (int) $context['n'] ? [] : range($from, min((int) $context['n'], $from + $limit - 1));
    }
}
