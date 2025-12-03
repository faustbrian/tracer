<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Tracer\Database\Models\Revision;
use Cline\Tracer\Enums\PrimaryKeyType;
use Cline\Tracer\Support\PrimaryKeyGenerator;
use Cline\Tracer\Support\PrimaryKeyValue;

describe('PrimaryKeyValue', function (): void {
    test('identifies auto-incrementing primary key', function (): void {
        $value = new PrimaryKeyValue(PrimaryKeyType::Numeric, null);

        expect($value->isAutoIncrementing())->toBeTrue();
    });

    test('identifies non-auto-incrementing uuid key', function (): void {
        $value = new PrimaryKeyValue(PrimaryKeyType::Uuid, 'some-uuid');

        expect($value->isAutoIncrementing())->toBeFalse();
    });

    test('identifies non-auto-incrementing ulid key', function (): void {
        $value = new PrimaryKeyValue(PrimaryKeyType::Ulid, 'some-ulid');

        expect($value->isAutoIncrementing())->toBeFalse();
    });
});

describe('PrimaryKeyGenerator', function (): void {
    test('generates null value for numeric primary key', function (): void {
        config(['tracer.primary_key_type' => 'id']);

        $result = PrimaryKeyGenerator::generate();

        expect($result->type)->toBe(PrimaryKeyType::Numeric);
        expect($result->value)->toBeNull();
    });

    test('generates uuid value for uuid primary key', function (): void {
        config(['tracer.primary_key_type' => 'uuid']);

        $result = PrimaryKeyGenerator::generate();

        expect($result->type)->toBe(PrimaryKeyType::Uuid);
        expect($result->value)->not->toBeNull();
        expect($result->value)->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/');
    });

    test('generates ulid value for ulid primary key', function (): void {
        config(['tracer.primary_key_type' => 'ulid']);

        $result = PrimaryKeyGenerator::generate();

        expect($result->type)->toBe(PrimaryKeyType::Ulid);
        expect($result->value)->not->toBeNull();
        expect($result->value)->toHaveLength(26);
    });

    test('defaults to numeric for invalid config', function (): void {
        config(['tracer.primary_key_type' => 'invalid-type']);

        $result = PrimaryKeyGenerator::generate();

        expect($result->type)->toBe(PrimaryKeyType::Numeric);
    });
});

describe('HasTracerPrimaryKey', function (): void {
    test('returns string key type for uuid config', function (): void {
        config(['tracer.primary_key_type' => 'uuid']);

        $revision = new Revision();

        expect($revision->getKeyType())->toBe('string');
        expect($revision->getIncrementing())->toBeFalse();
    });

    test('returns string key type for ulid config', function (): void {
        config(['tracer.primary_key_type' => 'ulid']);

        $revision = new Revision();

        expect($revision->getKeyType())->toBe('string');
        expect($revision->getIncrementing())->toBeFalse();
    });

    test('generates new unique id for uuid', function (): void {
        config(['tracer.primary_key_type' => 'uuid']);

        $revision = new Revision();
        $uniqueId = $revision->newUniqueId();

        expect($uniqueId)->not->toBeNull();
        expect($uniqueId)->toMatch('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/');
    });

    test('returns unique id columns for uuid', function (): void {
        config(['tracer.primary_key_type' => 'uuid']);

        $revision = new Revision();

        expect($revision->uniqueIds())->toContain($revision->getKeyName());
    });

    test('returns empty unique id columns for numeric', function (): void {
        config(['tracer.primary_key_type' => 'id']);

        $revision = new Revision();

        expect($revision->uniqueIds())->toBeEmpty();
    });
});
