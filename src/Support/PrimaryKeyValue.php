<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Support;

use Cline\Tracer\Enums\PrimaryKeyType;

/**
 * Value object representing a generated primary key.
 *
 * Encapsulates both the type of primary key (ULID, UUID, or numeric) and its
 * generated value. For numeric keys, the value is null as the database will
 * auto-increment. Immutable by design to ensure thread-safety and predictability.
 *
 * @author Brian Faust <brian@cline.sh>
 * @psalm-immutable
 */
final readonly class PrimaryKeyValue
{
    /**
     * Create a new primary key value object.
     *
     * @param PrimaryKeyType $type  The type of primary key being used (ULID, UUID, or numeric).
     *                              Determines how the key is generated and stored in the database.
     * @param null|string    $value The generated primary key value for ULID/UUID types, or null
     *                              for numeric auto-incrementing keys. ULIDs and UUIDs are stored
     *                              in lowercase format for consistency and case-insensitive matching.
     */
    public function __construct(
        public PrimaryKeyType $type,
        public ?string $value,
    ) {}

    /**
     * Check if this is an auto-incrementing primary key.
     *
     * @return bool True if the key type is numeric (auto-increment), false for ULID/UUID
     */
    public function isAutoIncrementing(): bool
    {
        return $this->type === PrimaryKeyType::Numeric;
    }
}
