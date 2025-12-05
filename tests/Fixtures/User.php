<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Test user model for approval workflows.
 *
 * @property string $email
 * @property int    $id
 * @property string $name
 * @author Brian Faust <brian@cline.sh>
 */
final class User extends Model implements Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
    ];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // Not used in tests
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
