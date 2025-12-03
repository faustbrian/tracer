<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Database\Models;

use Cline\Tracer\Database\Concerns\HasTracerPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Override;

/**
 * Eloquent model representing an approval or rejection record for a staged change.
 *
 * Tracks individual approval/rejection decisions within the workflow, supporting
 * multi-approver and quorum-based approval strategies.
 *
 * @property bool            $approved         Whether this is an approval (true) or rejection (false)
 * @property null|Model      $approver         The user/entity that made this decision
 * @property null|int|string $approver_id      Polymorphic ID of the approver
 * @property null|string     $approver_type    Polymorphic type of the approver
 * @property null|string     $comment          Optional comment from the approver
 * @property Carbon          $created_at       When the approval/rejection was recorded
 * @property mixed           $id               Primary key (auto-increment, UUID, or ULID)
 * @property null|int        $sequence         Order in multi-step approval workflows
 * @property int|string      $staged_change_id Foreign key to the staged change
 * @property StagedChange    $stagedChange     The staged change this approval belongs to
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class StagedChangeApproval extends Model
{
    use HasFactory;
    use HasTracerPrimaryKey;

    public const null UPDATED_AT = null;

    /** @var array<int, string> */
    protected $fillable = [
        'staged_change_id',
        'approved',
        'comment',
        'approver_type',
        'approver_id',
        'sequence',
    ];

    /**
     * Get the table name from configuration.
     *
     * Reads the configured table name to support custom naming conventions
     * across different Laravel applications. Defaults to 'staged_change_approvals'
     * if not explicitly configured.
     */
    #[Override()]
    public function getTable(): string
    {
        /** @var string */
        return Config::get('tracer.table_names.staged_change_approvals', 'staged_change_approvals');
    }

    /**
     * Get the staged change this approval belongs to.
     *
     * Returns the parent staged change that this approval record is associated
     * with. Multiple approval records can exist for a single staged change in
     * multi-approver or quorum-based workflows.
     *
     * @return BelongsTo<StagedChange, $this>
     */
    public function stagedChange(): BelongsTo
    {
        return $this->belongsTo(StagedChange::class);
    }

    /**
     * Get the user/entity that made this decision.
     *
     * Returns the model instance representing who approved or rejected the
     * staged change, typically a User model but supporting any authenticated
     * entity. The relationship is polymorphic and nullable for system decisions.
     *
     * @return MorphTo<Model, $this>
     */
    public function approver(): MorphTo
    {
        return $this->morphTo('approver');
    }

    /**
     * Check if this is an approval (vs rejection).
     *
     * Returns true when the approver gave their approval, allowing the staged
     * change to proceed. Use this method for clearer intent than checking the
     * $approved property directly.
     */
    public function isApproval(): bool
    {
        return $this->approved;
    }

    /**
     * Check if this is a rejection (vs approval).
     *
     * Returns true when the approver rejected the staged change, typically
     * preventing it from being applied. Use this method for clearer intent
     * than checking the $approved property directly.
     */
    public function isRejection(): bool
    {
        return !$this->approved;
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
            'approved' => 'boolean',
            'sequence' => 'integer',
        ];
    }
}
