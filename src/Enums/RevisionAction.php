<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Enums;

/**
 * Represents the type of action that created a revision record.
 *
 * Defines the complete set of model lifecycle events that trigger revision
 * creation. Each action represents a specific state transition captured in
 * the audit trail, enabling precise tracking of how models change over time.
 *
 * Actions are automatically determined during revision creation based on the
 * model event that triggered the revision. Understanding these actions is
 * essential for filtering revision histories, generating audit reports, and
 * implementing custom business logic based on change types.
 *
 * ```php
 * // Query revisions by action type
 * $updates = $model->revisions()
 *     ->where('action', RevisionAction::Updated)
 *     ->get();
 *
 * // Check action type
 * if ($revision->action === RevisionAction::StagedApplied) {
 *     // Handle applied staged change
 * }
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum RevisionAction: string
{
    /**
     * The model was created.
     *
     * Triggered on initial model creation, capturing the first snapshot with
     * all attribute values in new_values and empty old_values. This establishes
     * the baseline for all subsequent revisions.
     */
    case Created = 'created';

    /**
     * The model was updated.
     *
     * Triggered when model attributes are modified, capturing both previous
     * (old_values) and new (new_values) states. This is the most common action
     * type, representing standard data modifications during the model lifecycle.
     */
    case Updated = 'updated';

    /**
     * The model was deleted.
     *
     * Triggered when a model is soft deleted. Captures the final state before
     * deletion in old_values. For models using SoftDeletes, this action preserves
     * the complete state before the deleted_at timestamp was set.
     */
    case Deleted = 'deleted';

    /**
     * The model was restored from soft delete.
     *
     * Triggered when a soft-deleted model is restored. Captures the transition
     * from deleted back to active state, including any attribute changes that
     * occurred during restoration beyond just clearing deleted_at.
     */
    case Restored = 'restored';

    /**
     * The model was force deleted permanently.
     *
     * Triggered when a model is permanently removed from the database via force
     * delete. This is the final revision for a model, capturing its last state
     * before permanent destruction with no possibility of restoration.
     */
    case ForceDeleted = 'force_deleted';

    /**
     * A staged change was applied to the model.
     *
     * Triggered when an approved staged change is applied to the model. Captures
     * the transition from proposed to actual state, with old_values representing
     * the state before application and new_values showing the applied changes.
     */
    case StagedApplied = 'staged_applied';

    /**
     * The model was reverted to a previous revision.
     *
     * Triggered when a model is explicitly rolled back to a prior revision state.
     * Captures the revert operation itself, with old_values showing the current
     * state and new_values showing the restored historical state.
     */
    case Reverted = 'reverted';
}
