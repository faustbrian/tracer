<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Tracer\Resolvers;

use Cline\Tracer\Contracts\CauserResolver;
use Illuminate\Database\Eloquent\Model;

use function auth;

/**
 * Default causer resolver using Laravel's authentication system.
 *
 * Resolves the causer (the entity responsible for a change) from the currently
 * authenticated user via the default authentication guard. This is the standard
 * implementation for web applications where changes are made by authenticated users.
 *
 * For unauthenticated requests (e.g., CLI, queue jobs, or public endpoints),
 * this resolver returns null, allowing the system to record anonymous changes.
 * Custom resolvers can be implemented for specialized causer attribution such as
 * API tokens, service accounts, or system processes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AuthCauserResolver implements CauserResolver
{
    /**
     * Resolve the authenticated user as the causer of a model change.
     *
     * Returns the currently authenticated user from Laravel's default guard,
     * or null if no user is authenticated. The returned model can be of any type
     * that extends Eloquent Model, typically a User model.
     *
     * @return null|Model The authenticated user model, or null for unauthenticated requests
     */
    public function resolve(): ?Model
    {
        /** @var null|Model */
        return auth()->guard()->user();
    }
}
