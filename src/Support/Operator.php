<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Support;

use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;
use Dskripchenko\ProcessEngine\Actor;
use Dskripchenko\ProcessEngine\Engine;
use Dskripchenko\ProcessEngine\Store\InboxRecord;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Runs an operator action on behalf of the signed-in admin.
 *
 * The engine queues a manual operation as an event of the instance; the
 * runner applies it in order with the other events. With a synchronous
 * queue it has already run when the call returns, so the outcome is
 * reported at once: a refusal (the effect is not failed, the state does not
 * exist) becomes the panel's error. With a real queue the action is
 * reported as accepted.
 */
final class Operator
{
    public static function actor(): Actor
    {
        // The panel signs admins in with a guard of its own, not the
        // application's default one.
        try {
            $user = Auth::guard(Panels::currentGuard())->user();
        } catch (\Throwable) {
            $user = null;
        }
        if (! $user instanceof Authenticatable) {
            return Actor::system('admin');
        }
        $display = data_get($user, 'name') ?? data_get($user, 'email');

        return Actor::operator((string) $user->getAuthIdentifier(), is_string($display) ? $display : null);
    }

    /**
     * @param  string  $operation  the manual operation (ManualOperations::OPERATIONS)
     * @param  callable(Engine, Actor, string): mixed  $call
     */
    public static function run(string $instanceId, string $operation, mixed $reason, callable $call): string
    {
        $reason = trim(is_string($reason) ? $reason : '');
        if ($reason === '') {
            throw new ActionFailedException(__('Укажите причину: она попадёт в журнал'));
        }
        $engine = app(Engine::class);
        try {
            $call($engine, self::actor(), $reason);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw new ActionFailedException($e->getMessage(), previous: $e);
        }

        $entry = self::lastManual($engine, $instanceId, 'manual.'.$operation);
        if ($entry?->status === InboxRecord::FAILED) {
            throw new ActionFailedException(__('Операция отклонена: :reason', ['reason' => (string) $entry->reason]));
        }

        return $entry === null || $entry->status === InboxRecord::PENDING
            ? __('Операция принята и будет выполнена')
            : __('Операция выполнена');
    }

    private static function lastManual(Engine $engine, string $instanceId, string $name): ?InboxRecord
    {
        $found = null;
        foreach ($engine->find($instanceId)?->inbox() ?? [] as $entry) {
            if ($entry->name === $name && ($found === null || (int) $entry->id > (int) $found->id)) {
                $found = $entry;
            }
        }

        return $found;
    }
}
