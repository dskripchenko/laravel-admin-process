<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Support;

use Dskripchenko\ProcessEngine\Definition\Definition;
use Dskripchenko\ProcessEngine\Engine;

/**
 * The states an instance row stands in, for the list: the active leaf
 * states, or the final state of a finished instance. The definitions are
 * read once per request.
 */
final class StateNames
{
    /** @var array<string, Definition>|null */
    private ?array $definitions = null;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function of(string $definitionId, array $snapshot): string
    {
        if (isset($snapshot['final']) && is_string($snapshot['final'])) {
            return $snapshot['final'];
        }
        $active = array_values(array_filter((array) ($snapshot['active'] ?? []), 'is_string'));
        $definition = $this->definition($definitionId);
        if ($definition !== null) {
            $active = array_values(array_filter($active, fn (string $id) => $definition->has($id) && $definition->node($id)->isAtomic()));
        }

        return implode(', ', $active);
    }

    private function definition(string $id): ?Definition
    {
        if ($this->definitions === null) {
            $this->definitions = [];
            foreach (app(Engine::class)->definitions() as $record) {
                $this->definitions[$record->id] = new Definition($record->schema);
            }
        }

        return $this->definitions[$id] ?? null;
    }
}
