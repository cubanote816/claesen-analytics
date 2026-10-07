<?php

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base for every payload of this module.
 *
 * The contract describes the objects themselves — `{id, name, …}` for a session,
 * `[{code, …}, …]` for a project list — and never an envelope, so nothing here
 * may come back as `{data: […]}`.
 *
 * That takes two things, which is why this class exists:
 *   - `$wrap = null` covers a single resource (the response reads this class's
 *     static);
 *   - `list()` covers a collection, because ResourceResponse reads the wrapper
 *     from the *collection* class, which is Laravel's own and always says `data`.
 */
abstract class KnxResource extends JsonResource
{
    public static $wrap = null;

    /**
     * A list payload of the contract: a plain JSON array.
     *
     * Items are built by hand instead of with `static::collection()`, and that is a
     * fix rather than a preference: Laravel's collection path instantiates each item
     * through `Collection::mapInto()`, which passes the *collection key* as the
     * second constructor argument — so a resource with an optional second parameter
     * silently receives 0, 1, 2…
     *
     * That is not a theory. `DocumentResource` used to take `bool $withUrl`, and
     * every item after the first came back with a signed URL inside a list that
     * promises none. It went unnoticed because the fixture had no files on disk, so
     * the URL was skipped for a missing file anyway; the moment the seeder started
     * writing real plan files, the office's document table began building one signed
     * URL per row.
     *
     * A resource that genuinely needs a second value now gets a `TypeError` here
     * instead of a wrong answer, which is the point.
     *
     * @param  mixed  $resource
     */
    public static function list($resource, Request $request): array
    {
        return collect($resource)
            ->map(fn ($item): array => (new static($item))->resolve($request))
            ->all();
    }
}
