<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Database\Models;

use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Cline\Tracer\Enums\RevisionAction;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Override;

use function array_key_exists;
use function array_keys;
use function array_unique;

/**
 * Eloquent model representing a historical revision of a traceable model.
 *
 * Stores snapshots of model state at specific points in time, enabling
 * full audit trails and the ability to revert to previous states.
 *
 * @property RevisionAction            $action         The action that created this revision
 * @property null|Model                $causer         The user/entity that caused this revision
 * @property null|int|string           $causer_id      Polymorphic ID of the user/entity that made the change
 * @property null|string               $causer_type    Polymorphic type of the user/entity that made the change
 * @property Carbon                    $created_at     When the revision was created
 * @property string                    $diff_strategy  Identifier of the diff strategy used
 * @property mixed                     $id             Primary key (auto-increment, UUID, or ULID)
 * @property null|array<string, mixed> $metadata       Additional context about the revision
 * @property array<string, mixed>      $new_values     New attribute values after the change
 * @property array<string, mixed>      $old_values     Previous attribute values before the change
 * @property null|Model                $traceable      The model this revision belongs to
 * @property int|string                $traceable_id   Polymorphic ID of the tracked model
 * @property string                    $traceable_type Polymorphic type of the tracked model
 * @property int                       $version        Sequential version number for this model
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Revision extends Model
{
    use HasFactory;
    use HasVariablePrimaryKey;

    public const null UPDATED_AT = null;

    /** @var array<int, string> */
    protected $fillable = [
        'traceable_type',
        'traceable_id',
        'version',
        'action',
        'old_values',
        'new_values',
        'diff_strategy',
        'causer_type',
        'causer_id',
        'metadata',
    ];

    /**
     * Get the table name from configuration.
     *
     * Reads the configured table name to support custom naming conventions
     * across different Laravel applications. Defaults to 'revisions' if not
     * explicitly configured.
     */
    #[Override()]
    public function getTable(): string
    {
        /** @var string */
        return Config::get('tracer.table_names.revisions', 'revisions');
    }

    /**
     * Get the model this revision belongs to.
     *
     * Returns the original model instance that was modified to create this
     * revision. The relationship is polymorphic, supporting any model type
     * that implements the Traceable interface.
     *
     * @return MorphTo<Model, $this>
     */
    public function traceable(): MorphTo
    {
        return $this->morphTo('traceable');
    }

    /**
     * Get the user/entity that caused this revision.
     *
     * Returns the model instance representing who made the change, typically
     * a User model but supporting any authenticated entity. The relationship
     * is polymorphic and nullable for system-generated changes.
     *
     * @return MorphTo<Model, $this>
     */
    public function causer(): MorphTo
    {
        return $this->morphTo('causer');
    }

    /**
     * Get a specific old value from before the change.
     *
     * Retrieves an attribute's previous value from the revision snapshot.
     * Returns the provided default if the attribute was not tracked in this
     * revision, which can occur when the attribute didn't exist or wasn't modified.
     *
     * @param string $key     The attribute name to retrieve
     * @param mixed  $default Fallback value if attribute not found
     */
    public function getOldValue(string $key, mixed $default = null): mixed
    {
        return $this->old_values[$key] ?? $default;
    }

    /**
     * Get a specific new value from after the change.
     *
     * Retrieves an attribute's updated value from the revision snapshot.
     * Returns the provided default if the attribute was not tracked in this
     * revision, which can occur when the attribute didn't exist or wasn't modified.
     *
     * @param string $key     The attribute name to retrieve
     * @param mixed  $default Fallback value if attribute not found
     */
    public function getNewValue(string $key, mixed $default = null): mixed
    {
        return $this->new_values[$key] ?? $default;
    }

    /**
     * Get the changed attribute keys.
     *
     * Returns a deduplicated array of all attribute names that appear in either
     * old_values or new_values, representing every field tracked in this revision.
     * Useful for iterating over changes without checking both arrays separately.
     *
     * @return array<string>
     */
    public function getChangedKeys(): array
    {
        return array_unique([
            ...array_keys($this->old_values),
            ...array_keys($this->new_values),
        ]);
    }

    /**
     * Check if a specific attribute was changed in this revision.
     *
     * Returns true if the attribute appears in either old_values or new_values,
     * indicating it was tracked as part of this change. This includes attributes
     * added, modified, or removed.
     *
     * @param string $key The attribute name to check
     */
    public function hasChangedAttribute(string $key): bool
    {
        return array_key_exists($key, $this->old_values) || array_key_exists($key, $this->new_values);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[Override()]
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'action' => RevisionAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
        ];
    }
}
