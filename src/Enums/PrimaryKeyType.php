<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Enums;

/**
 * Represents the primary key type used by Tracer package tables.
 *
 * Defines the identifier strategy for all Tracer models (Revision, StagedChange,
 * StagedChangeApproval). This configuration centralizes primary key management,
 * ensuring consistent behavior across all package tables without per-model setup.
 *
 * The choice affects database column types in migrations, identifier generation
 * during model creation, and query binding behavior. All package tables automatically
 * adapt to the configured strategy through the HasTracerPrimaryKey trait.
 *
 * ```php
 * // In config/tracer.php
 * 'primary_key_type' => 'uuid',
 *
 * // All Tracer models use UUIDs: Revision, StagedChange, StagedChangeApproval
 * $revision = Revision::create([...]);
 * echo $revision->id; // "9b5e4c12-4f5b-4a3c-8c5d-6e7f8a9b0c1d"
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum PrimaryKeyType: string
{
    /**
     * Standard auto-incrementing numeric primary keys.
     *
     * Uses database-managed auto-increment sequences with INTEGER/BIGINT columns.
     * This is the default Laravel approach, providing sequential, compact identifiers
     * ideal for high-volume inserts and efficient indexing. No application-level
     * ID generation is required.
     */
    case Numeric = 'id';

    /**
     * UUID version 4 primary keys.
     *
     * Uses RFC 4122 UUIDs (128-bit identifiers stored as CHAR(36) or BINARY(16)).
     * Provides globally unique identifiers suitable for distributed systems, data
     * merging, and external API exposure. Generated at the application layer before
     * insertion, eliminating database round-trips for ID retrieval.
     */
    case Uuid = 'uuid';

    /**
     * ULID primary keys.
     *
     * Uses Universally Unique Lexicographically Sortable Identifiers stored as
     * CHAR(26). Combines UUID uniqueness with timestamp-based sorting, providing
     * chronological ordering like auto-increment while maintaining distributed
     * generation capabilities. Optimized for readability and database indexing.
     */
    case Ulid = 'ulid';
}
