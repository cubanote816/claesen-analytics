<?php

namespace Modules\Mailing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Modules\Mailing\Services\PreferenceService;
use Modules\Prospects\Models\Prospect;

class PreferencesController extends Controller
{
    public function __construct(private readonly PreferenceService $preferences) {}

    public function show(Prospect $prospect, string $token): View
    {
        $this->verifyToken($prospect, $token);

        App::setLocale($prospect->language ?? 'nl');

        return view('mailing::preferences', [
            'prospect'    => $prospect,
            'token'       => $token,
            'preferences' => $this->preferences->getPreferences($prospect),
            'categories'  => config('mailing.preference_categories', []),
            'locale'      => App::getLocale(),
            'saved'       => false,
        ]);
    }

    public function update(Request $request, Prospect $prospect, string $token): RedirectResponse
    {
        $this->verifyToken($prospect, $token);

        // Build complete map for ALL known categories.
        // Unchecked checkboxes are absent from the request, so we explicitly
        // set them to false — HTML omits unchecked inputs.
        $knownCategories = array_keys(config('mailing.preference_categories', []));

        $preferences = [];
        foreach ($knownCategories as $category) {
            $preferences[$category] = $request->boolean($category);
        }

        $this->preferences->updatePreferences($prospect, $preferences);

        return redirect()
            ->route('mailing.preferences.show', [
                'prospect' => $prospect->id,
                'token'    => $token,
            ])
            ->with('saved', true);
    }

    private function verifyToken(Prospect $prospect, string $token): void
    {
        if (! hash_equals($prospect->getUnsubscribeToken(), $token)) {
            abort(403, 'Invalid token.');
        }
    }
}
