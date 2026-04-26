<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        /** @var string $tableName */
        $tableName = Config::get('tracer.table_names.staged_changes', 'staged_changes');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->string('conflict_resolution', 32)->nullable()->after('approval_metadata');
            $table->json('resolved_values')->nullable()->after('conflict_resolution');
            $table->json('conflict_snapshot')->nullable()->after('resolved_values');
        });
    }

    public function down(): void
    {
        /** @var string $tableName */
        $tableName = Config::get('tracer.table_names.staged_changes', 'staged_changes');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn([
                'conflict_resolution',
                'resolved_values',
                'conflict_snapshot',
            ]);
        });
    }
};
