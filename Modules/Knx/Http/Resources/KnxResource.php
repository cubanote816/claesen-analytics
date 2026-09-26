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
     * @param  mixed  $resource
     */
    public static function list($resource, Request $request): array
    {
        return static::collection($resource)->resolve($request);
    }
}
