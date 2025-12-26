<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Database\Models;

use Cline\Tracer\Concerns\HasRevisions;
use Cline\Tracer\Contracts\Traceable;
use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Cline\Tracer\Enums\StagedChangeStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * Eloquent model representing a staged change awaiting approval.
 *
 * Stores proposed changes to a model that must go through an approval workflow
 * before being applied. Supports configurable diff and approval strategies.
 *
 * @property null|Carbon                           $applied_at        When the change was applied (if applied)
 * @property null|array<string, mixed>             $approval_metadata Strategy-specific approval tracking data
 * @property string                                $approval_strategy Identifier of the approval strategy used
 * @property Collection<int, StagedChangeApproval> $approvals         Individual approval/rejection records
 * @property null|Model                            $author            The user/entity that proposed this change
 * @property null|int|string                       $author_id         Polymorphic ID of the change author
 * @property null|string                           $author_type       Polymorphic type of the change author
 * @property Carbon                                $created_at        When the change was staged
 * @property string                                $diff_strategy     Identifier of the diff strategy used
 * @property mixed                                 $id                Primary key (auto-increment, UUID, or ULID)
 * @property null|array<string, mixed>             $metadata          Additional context about the staged change
 * @property array<string, mixed>                  $original_values   Original attribute values before proposed changes
 * @property array<string, mixed>                  $proposed_values   Proposed new attribute values
 * @property null|string                           $reason            Reason for the proposed change
 * @property null|string                           $rejection_reason  Reason for rejection if rejected
 * @property null|Model                            $stageable         The model this staged change targets
 * @property int|string                            $stageable_id      Polymorphic ID of the target model
 * @property string                                $stageable_type    Polymorphic type of the target model
 * @property StagedChangeStatus                    $status            Current workflow status
 * @property Carbon                                $updated_at        When the staged change was last modified
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChange extends Model implements Traceable
{
    use HasFactory;
    use HasRevisions;
    use HasVariablePrimaryKey;

    /** @var array<int, string> */
    protected $fillable = [
        'stageable_type',
        'stageable_id',
        'original_values',
        'proposed_values',
        'diff_strategy',
        'approval_strategy',
        'status',
        'reason',
        'rejection_reason',
        'approval_metadata',
        'author_type',
        'author_id',
        'metadata',
        'applied_at',
    ];

    /**
     * Get the table name from configuration.
     *
     * Reads the configured table name to support custom naming conventions
     * across different Laravel applications. Defaults to 'staged_changes' if
     * not explicitly configured.
     */
    #[Override()]
    public function getTable(): string
    {
        /** @var string */
        return Config::get('tracer.table_names.staged_changes', 'staged_changes');
    }

    /**
     * Get the model this staged change targets.
     *
     * Returns the model instance that will be modified if this staged change
     * is approved and applied. The relationship is polymorphic, supporting any
     * model type that implements the Stageable interface.
     *
     * @return MorphTo<Model, $this>
     */
    public function stageable(): MorphTo
    {
        return $this->morphTo('stageable');
    }

    /**
     * Get the user/entity that authored this change.
     *
     * Returns the model instance representing who proposed the staged change,
     * typically a User model but supporting any authenticated entity. The
     * relationship is polymorphic and nullable for system-generated proposals.
     *
     * @return MorphTo<Model, $this>
     */
    public function author(): MorphTo
    {
        return $this->morphTo('author');
    }

    /**
     * Get the individual approval records.
     *
     * Returns all approval and rejection decisions made for this staged change.
     * Each record represents a single approver's decision with timestamp and
     * optional comments, supporting multi-approver workflows and audit trails.
     *
     * @return HasMany<StagedChangeApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(StagedChangeApproval::class);
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
            'original_values' => 'array',
            'proposed_values' => 'array',
            'status' => StagedChangeStatus::class,
            'approval_metadata' => 'array',
            'metadata' => 'array',
            'applied_at' => 'datetime',
        ];
    }
}
