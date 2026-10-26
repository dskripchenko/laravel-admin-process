<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read access to the engine's incidents table for the incident list.
 * Writes go through the engine only.
 *
 * @property string $id
 * @property string $instance_id
 * @property string $type
 * @property string $message
 * @property array<string, mixed> $details
 * @property string|null $effect_id
 * @property string $status
 */
final class ProcessIncident extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = ['*'];

    /** @var array<string, string> */
    protected $casts = [
        'details' => 'array',
        'resolved_by' => 'array',
    ];

    public function getConnectionName(): ?string
    {
        return config('process-engine.connection') ?? parent::getConnectionName();
    }

    public function getTable(): string
    {
        return config('process-engine.table_prefix', 'pe_').'incidents';
    }
}
