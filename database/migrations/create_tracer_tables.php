<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Enums\MorphType;
use Cline\Tracer\Enums\PrimaryKeyType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $this->createRevisionsTable();
        $this->createStagedChangesTable();
        $this->createStagedChangeApprovalsTable();
    }

    public function down(): void
    {
        /** @var string $approvalsTable */
        $approvalsTable = Config::get('tracer.table_names.staged_change_approvals', 'staged_change_approvals');
        /** @var string $stagedChangesTable */
        $stagedChangesTable = Config::get('tracer.table_names.staged_changes', 'staged_changes');
        /** @var string $revisionsTable */
        $revisionsTable = Config::get('tracer.table_names.revisions', 'revisions');

        Schema::dropIfExists($approvalsTable);
        Schema::dropIfExists($stagedChangesTable);
        Schema::dropIfExists($revisionsTable);
    }

    private function createRevisionsTable(): void
    {
        /** @var string $tableName */
        $tableName = Config::get('tracer.table_names.revisions', 'revisions');

        Schema::create($tableName, function (Blueprint $table): void {
            $this->addPrimaryKey($table);

            // Polymorphic relationship to the tracked model
            $this->addMorphColumn($table, 'traceable');

            // Version number (sequential per model)
            $table->unsignedInteger('version');

            // Action that created this revision
            $table->string('action', 32);

            // Stored diff values
            $table->json('old_values');
            $table->json('new_values');

            // Strategy identifier for applying/reverting
            $table->string('diff_strategy', 64);

            // Who made this change (polymorphic)
            $this->addNullableMorphColumn($table, 'causer');

            // Additional context
            $table->json('metadata')->nullable();

            $table->timestamp('created_at');

            // Indexes
            $table->index(['traceable_type', 'traceable_id', 'version']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    private function createStagedChangesTable(): void
    {
        /** @var string $tableName */
        $tableName = Config::get('tracer.table_names.staged_changes', 'staged_changes');

        Schema::create($tableName, function (Blueprint $table): void {
            $this->addPrimaryKey($table);

            // Polymorphic relationship to the target model
            $this->addMorphColumn($table, 'stageable');

            // Original and proposed values
            $table->json('original_values');
            $table->json('proposed_values');

            // Strategy identifiers
            $table->string('diff_strategy', 64);
            $table->string('approval_strategy', 64);

            // Workflow status
            $table->string('status', 32)->default('pending');

            // Reason for change and rejection
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();

            // Approval workflow metadata
            $table->json('approval_metadata')->nullable();

            // Who authored this change (polymorphic)
            $this->addNullableMorphColumn($table, 'author');

            // Additional context
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->timestamp('applied_at')->nullable();

            // Indexes
            $table->index(['stageable_type', 'stageable_id', 'status']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    private function createStagedChangeApprovalsTable(): void
    {
        /** @var string $tableName */
        $tableName = Config::get('tracer.table_names.staged_change_approvals', 'staged_change_approvals');
        /** @var string $stagedChangesTable */
        $stagedChangesTable = Config::get('tracer.table_names.staged_changes', 'staged_changes');

        Schema::create($tableName, function (Blueprint $table) use ($stagedChangesTable): void {
            $this->addPrimaryKey($table);

            // Foreign key to staged change
            $this->addForeignKey($table, 'staged_change_id', $stagedChangesTable);

            // Approval or rejection
            $table->boolean('approved');

            // Comment/reason
            $table->text('comment')->nullable();

            // Who made this decision (polymorphic)
            $this->addNullableMorphColumn($table, 'approver');

            // Order in multi-step workflows
            $table->unsignedInteger('sequence')->nullable();

            $table->timestamp('created_at');

            // Indexes
            $table->index(['staged_change_id', 'approved']);
        });
    }

    private function addPrimaryKey(Blueprint $table): void
    {
        $primaryKeyType = $this->getPrimaryKeyType();

        match ($primaryKeyType) {
            PrimaryKeyType::Uuid => $table->uuid('id')->primary(),
            PrimaryKeyType::Ulid => $table->ulid('id')->primary(),
            default => $table->id(),
        };
    }

    private function addForeignKey(Blueprint $table, string $column, string $references): void
    {
        $primaryKeyType = $this->getPrimaryKeyType();

        match ($primaryKeyType) {
            PrimaryKeyType::Uuid => $table->uuid($column),
            PrimaryKeyType::Ulid => $table->ulid($column),
            default => $table->unsignedBigInteger($column),
        };

        $table->foreign($column)->references('id')->on($references)->cascadeOnDelete();
    }

    private function addMorphColumn(Blueprint $table, string $name): void
    {
        $morphType = $this->getMorphType();

        match ($morphType) {
            MorphType::Uuid => $table->uuidMorphs($name),
            MorphType::Ulid => $table->ulidMorphs($name),
            default => $table->morphs($name),
        };
    }

    private function addNullableMorphColumn(Blueprint $table, string $name): void
    {
        $morphType = $this->getMorphType();

        match ($morphType) {
            MorphType::Uuid => $table->nullableUuidMorphs($name),
            MorphType::Ulid => $table->nullableUlidMorphs($name),
            default => $table->nullableMorphs($name),
        };
    }

    private function getPrimaryKeyType(): PrimaryKeyType
    {
        /** @var string $configValue */
        $configValue = Config::get('tracer.primary_key_type', 'id');

        return PrimaryKeyType::tryFrom($configValue) ?? PrimaryKeyType::Numeric;
    }

    private function getMorphType(): MorphType
    {
        /** @var string $configValue */
        $configValue = Config::get('tracer.morph_type', 'string');

        return MorphType::tryFrom($configValue) ?? MorphType::String;
    }
};
