<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Health;

use DateTimeImmutable;
use Dskripchenko\LaravelAdminHealth\HealthCheck;
use Dskripchenko\LaravelAdminHealth\HealthResult;
use Dskripchenko\ProcessEngine\Engine;
use Dskripchenko\ProcessEngine\Runtime\Clock;
use Dskripchenko\ProcessEngine\Store\HealthCounters;
use Dskripchenko\ProcessEngine\Store\Store;
use Dskripchenko\ProcessEngine\Support\Duration;
use Throwable;

/**
 * A health check of the process engine for laravel-admin-health (ADR-017 of
 * process-engine). Its thresholds come from `admin-process.health.<key>`.
 */
abstract class ProcessCheck implements HealthCheck
{
    /**
     * @param  array<string, mixed>  $config  overrides of `admin-process.health.<key>`
     */
    public function __construct(public readonly array $config = []) {}

    /** The key of the check's settings under `admin-process.health`. */
    abstract protected function key(): string;

    abstract protected function check(): HealthResult;

    public function id(): string
    {
        return 'process.'.$this->key();
    }

    public function category(): string
    {
        return 'processes';
    }

    public function frequency(): string
    {
        return (string) $this->setting('frequency', '1m');
    }

    public function timeout(): int
    {
        return (int) $this->setting('timeout', 10);
    }

    public function run(): HealthResult
    {
        try {
            return $this->check();
        } catch (Throwable $e) {
            return HealthResult::failing('Не удалось проверить процессы: :message', [], ['message' => $e->getMessage()]);
        }
    }

    protected function setting(string $name, mixed $default): mixed
    {
        $settings = (array) config('admin-process.health.'.$this->key(), []);

        return $this->config[$name] ?? $settings[$name] ?? $default;
    }

    protected function engine(): Engine
    {
        return app(Engine::class);
    }

    /** The store's own counters, when it has them (process-engine 1.3+). */
    protected function counters(): ?HealthCounters
    {
        // A host may bind a store of its own: not every one counts.
        /** @var Store $store */
        $store = app(Store::class);

        return $store instanceof HealthCounters ? $store : null;
    }

    /** The moment `$age` ago: '15 minutes', 'PT1H'. */
    protected function ago(string $age): DateTimeImmutable
    {
        $now = app(Clock::class)->now();

        return $now->modify('-'.Duration::seconds($age).' seconds');
    }
}
