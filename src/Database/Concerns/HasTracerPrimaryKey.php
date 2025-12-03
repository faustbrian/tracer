<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Database\Concerns;

use Cline\Tracer\Enums\PrimaryKeyType;
use Cline\Tracer\Exceptions\NonStringUlidPrimaryKeyException;
use Cline\Tracer\Exceptions\NonStringUuidPrimaryKeyException;
use Cline\Tracer\Support\PrimaryKeyGenerator;
use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

use function in_array;
use function is_string;

/**
 * Configures model primary keys based on Tracer package configuration.
 *
 * This trait enables automatic primary key type detection and generation based
 * on the `tracer.primary_key_type` configuration. It supports standard auto-incrementing
 * IDs, UUIDs, and ULIDs, automatically generating values during model creation.
 *
 * The trait overrides Laravel's default primary key behavior to respect package
 * configuration, ensuring consistent key types across all Tracer models without
 * requiring per-model customization.
 *
 * ```php
 * // In config/tracer.php
 * 'primary_key_type' => 'uuid',
 *
 * // Models automatically use UUIDs
 * $revision = Revision::create([...]);
 * echo $revision->id; // "9b5e4c12-..."
 * ```
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasTracerPrimaryKey
{
    /**
     * Determine if the model's primary key is auto-incrementing.
     *
     * Returns false when using UUIDs or ULIDs, as these are generated values
     * rather than database-managed auto-increment sequences. Falls back to the
     * model's $incrementing property for standard numeric keys.
     */
    public function getIncrementing(): bool
    {
        if (in_array($this->getKeyName(), $this->uniqueIds(), true)) {
            return false;
        }

        return $this->incrementing;
    }

    /**
     * Get the data type of the model's primary key.
     *
     * Returns 'string' when using UUIDs or ULIDs, otherwise falls back to the
     * model's $keyType property. This ensures proper type casting and query
     * binding for string-based identifiers.
     */
    public function getKeyType(): string
    {
        if (in_array($this->getKeyName(), $this->uniqueIds(), true)) {
            return 'string';
        }

        return $this->keyType;
    }

    /**
     * Generate a new unique identifier for the model.
     *
     * Delegates to PrimaryKeyGenerator which reads package configuration to
     * determine whether to generate a UUID, ULID, or return null for numeric
     * auto-increment keys handled by the database.
     *
     * @return null|string The generated identifier, or null for auto-increment keys
     */
    public function newUniqueId(): ?string
    {
        return PrimaryKeyGenerator::generate()->value;
    }

    /**
     * Get the columns that should use unique identifiers.
     *
     * Returns an array containing the primary key name when configured for
     * UUIDs or ULIDs. Returns an empty array for numeric auto-increment keys,
     * allowing Laravel to skip unique ID generation.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        /** @var string $configValue */
        $configValue = Config::get('tracer.primary_key_type', 'id');
        $primaryKeyType = PrimaryKeyType::tryFrom($configValue) ?? PrimaryKeyType::Numeric;

        return match ($primaryKeyType) {
            PrimaryKeyType::Ulid, PrimaryKeyType::Uuid => [$this->getKeyName()],
            PrimaryKeyType::Numeric => [],
        };
    }

    /**
     * Boot the trait and register model event listeners.
     *
     * Registers a creating event listener that automatically generates and assigns
     * primary key values for UUIDs and ULIDs. Validates that manually-assigned values
     * for UUID/ULID keys are strings, throwing exceptions for type mismatches.
     *
     * @throws NonStringUuidPrimaryKeyException When a UUID value is not a string
     * @throws NonStringUlidPrimaryKeyException When a ULID value is not a string
     */
    #[Boot()]
    protected static function registerPrimaryKeyGenerator(): void
    {
        static::creating(function (Model $model): void {
            $primaryKey = PrimaryKeyGenerator::generate();

            if ($primaryKey->isAutoIncrementing()) {
                return;
            }

            $keyName = $model->getKeyName();
            $existingValue = $model->getAttribute($keyName);

            if (!$existingValue) {
                $model->setAttribute($keyName, $primaryKey->value);

                return;
            }

            if ($primaryKey->type === PrimaryKeyType::Uuid && !is_string($existingValue)) {
                throw NonStringUuidPrimaryKeyException::forValue($existingValue);
            }

            if ($primaryKey->type === PrimaryKeyType::Ulid && !is_string($existingValue)) {
                throw NonStringUlidPrimaryKeyException::forValue($existingValue);
            }
        });
    }
}
