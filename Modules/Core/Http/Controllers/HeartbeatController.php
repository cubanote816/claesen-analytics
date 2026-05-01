<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class HeartbeatController extends Controller
{
    /**
     * Update the user's session last activity timestamp.
     */
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
