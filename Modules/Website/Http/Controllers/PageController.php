<?php

namespace Modules\Website\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Website\Models\Page;

class PageController extends Controller
{
    public function index()
    {
        $pages = Page::published()
            ->ordered()
            ->get(['slug', 'title', 'published_at']);

        return response()->json([
            'data' => $pages
        ]);
    }

    public function show($slug)
    {
        $page = Page::where('slug', $slug)
            ->published()
            ->firstOrFail();

        return response()->json([
            'data' => $page
        ]);
    }
}
