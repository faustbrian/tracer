<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\Tracer\Concerns\HasRevisions;
use Cline\Tracer\Concerns\HasStagedChanges;
use Cline\Tracer\Contracts\Stageable;
use Cline\Tracer\Contracts\Traceable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Test article model with revision tracking and staged changes.
 *
 * @property string      $content
 * @property int         $id
 * @property null|string $price
 * @property string      $status
 * @property string      $title
 * @author Brian Faust <brian@cline.sh>
 */
final class Article extends Model implements Stageable, Traceable
{
    use HasFactory;
    use HasRevisions;
    use HasStagedChanges;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'status',
        'price',
    ];

    /**
     * Attributes that should not be tracked in revisions.
     *
     * @var array<string>
     */
    private array $untrackedAttributes = [];

    /**
     * Attributes that cannot be staged.
     *
     * @var array<string>
     */
    private array $unstageableAttributes = [];
}
