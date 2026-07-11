<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Http\Requests\UpdateAdvancedPreferencesRequest;
use Modules\Core\Http\Requests\UpdatePreferencesRequest;
use Modules\Core\Services\UserPreferencesService;

final class UserPreferencesController extends Controller
{
    public function __construct(
        private readonly UserPreferencesService $preferencesService
    ) {
    }

    public function show(): JsonResponse
    {
        $preferences = $this->preferencesService->getUserPreferences();

        return response()->json([
            'success' => true,
            'data'    => $preferences,
        ]);
    }

    public function update(UpdatePreferencesRequest $request): JsonResponse
    {
        $user = $this->preferencesService->updateUserPreferences(
            $request->only(['language', 'theme']),
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferencias básicas actualizadas correctamente.',
            'data'    => [
                'language' => $user->language,
                'theme'    => $user->theme,
            ],
        ]);
    }

    public function updateAdvanced(UpdateAdvancedPreferencesRequest $request): JsonResponse
    {
        $user = $this->preferencesService->updateUserPreferences(
            $request->validated(),
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Preferencias avanzadas actualizadas correctamente.',
            'data'    => [
                'language'         => $user->language,
                'theme'            => $user->theme,
                'preferences_data' => $user->preferences_data,
            ],
        ]);
    }

    public function defaults(): JsonResponse
    {
        $defaults = $this->preferencesService->getDefaultPreferences();

        return response()->json([
            'success' => true,
            'data'    => [
                'language'         => 'nl',
                'theme'            => 'light',
                'preferences_data' => $defaults,
            ],
        ]);
    }

    public function resetToDefaults(Request $request): JsonResponse
    {
        $defaults = $this->preferencesService->getDefaultPreferences();

        $user = $this->preferencesService->updateUserPreferences([
            'language'         => 'nl',
            'theme'            => 'light',
            'preferences_data' => $defaults,
        ], $request);

        return response()->json([
            'success' => true,
            'message' => 'Preferencias restauradas a los valores por defecto.',
            'data'    => [
                'language'         => $user->language,
                'theme'            => $user->theme,
                'preferences_data' => $user->preferences_data,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = min(max($limit, 1), 50);

        $history = $this->preferencesService->getPreferencesHistory(
            (int) auth()->id(),
            $limit
        );

        return response()->json([
            'success' => true,
            'data'    => $history,
        ]);
    }

    public function validateStructure(Request $request): JsonResponse
    {
        $preferences = $request->input('preferences_data', []);

        $isValid = $this->preferencesService->validatePreferencesStructure($preferences);

        return response()->json([
            'success' => true,
            'valid'   => $isValid,
            'message' => $isValid
                ? 'La estructura de preferencias es válida.'
                : 'La estructura de preferencias no es válida.',
        ]);
    }
}
