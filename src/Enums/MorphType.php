<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Enums;

/**
 * Represents the column type used for polymorphic relation identifier storage.
 *
 * Defines how polymorphic *_type columns store model class identifiers in the
 * database. This determines both storage format and query optimization strategies
 * for polymorphic relationships like morphTo, morphMany, and morphToMany.
 *
 * The choice affects database column types in migrations and must match the
 * format of stored class names or morph map aliases.
 *
 * ```php
 * // In config/tracer.php
 * 'morph_type' => 'string',
 *
 * // Results in VARCHAR columns for traceable_type, causer_type, etc.
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
enum MorphType: string
{
    /**
     * Standard string storage for polymorphic types.
     *
     * Uses VARCHAR columns to store class names or morph map aliases.
     * This is the most common and flexible option, suitable for human-readable
     * identifiers and supporting morph maps with custom string aliases.
     */
    case String = 'string';

    /**
     * UUID storage for polymorphic types.
     *
     * Uses UUID/CHAR(36) columns to store model type identifiers. This is
     * uncommon but supports systems using UUIDs as type identifiers instead
     * of class names, typically for cross-system data portability.
     */
    case Uuid = 'uuid';

    /**
     * ULID storage for polymorphic types.
     *
     * Uses ULID/CHAR(26) columns to store model type identifiers. This is
     * uncommon but supports systems using ULIDs as type identifiers instead
     * of class names, providing sortable unique identifiers for type tracking.
     */
    case Ulid = 'ulid';
}
