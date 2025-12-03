<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Support;

use Cline\Tracer\Enums\PrimaryKeyType;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Generates primary key values based on configuration.
 *
 * Creates PrimaryKeyValue objects with appropriate types (ULID, UUID, or numeric)
 * based on the 'tracer.primary_key_type' configuration setting. Supports different
 * primary key strategies for revision and staging tables.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PrimaryKeyGenerator
{
    /**
     * Generate a new primary key value based on configuration.
     *
     * Reads the 'tracer.primary_key_type' configuration value and generates
     * the appropriate primary key type. Falls back to numeric (auto-increment)
     * if the configuration value is invalid or not set.
     *
     * @return PrimaryKeyValue Value object containing the generated key type and value
     */
    public static function generate(): PrimaryKeyValue
    {
        /** @var string $configValue */
        $configValue = Config::get('tracer.primary_key_type', 'id');
        $primaryKeyType = PrimaryKeyType::tryFrom($configValue) ?? PrimaryKeyType::Numeric;

        $value = match ($primaryKeyType) {
            PrimaryKeyType::Ulid => Str::lower((string) Str::ulid()),
            PrimaryKeyType::Uuid => Str::lower((string) Str::uuid()),
            PrimaryKeyType::Numeric => null,
        };

        return new PrimaryKeyValue($primaryKeyType, $value);
    }
}
