<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Fixtures;

use Dskripchenko\ProcessEngine\Attributes\Idempotent;
use Dskripchenko\ProcessEngine\Batch\BatchHandler;
use Dskripchenko\ProcessEngine\Batch\BatchResult;
use Dskripchenko\ProcessEngine\Batch\ItemOutcome;
use Dskripchenko\ProcessEngine\Effect\EffectNotExecuted;

/** Fails key 2 always; holds the chunk with key 9 while `$hold`. */
#[Idempotent]
final class Lookup implements BatchHandler
{
    public static bool $hold = false;

    /** @param list<string> $keys */
    public function handle(array $keys): BatchResult
    {
        if (self::$hold && in_array('9', $keys, true)) {
            throw new EffectNotExecuted(new \RuntimeException('held'));
        }
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $key === '2' ? ItemOutcome::failed('timeout') : ItemOutcome::done();
        }

        return BatchResult::of($out);
    }
}
