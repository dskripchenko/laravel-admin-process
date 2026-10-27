<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read access to the engine's definitions table for the list of processes.
 * Writes go through the engine only.
 *
 * @property int $id
 * @property string $name
 * @property int $version
 * @property array<string, mixed> $schema
 */
final class ProcessDefinition extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    /** @var list<string> the whole definition: too big for a list row */
    protected $hidden = ['schema'];

    /** @var array<string, string> */
    protected $casts = [
        'schema' => 'array',
    ];

    public function getConnectionName(): ?string
    {
        return config('process-engine.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return config('process-engine.table_prefix', 'pe_').'definitions';
    }
}
