<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\User;
use Modules\Core\Models\UserPreferencesLog;

final class UserPreferencesService
{
    public function getUserPreferences(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return [
            'language' => $user->language ?? 'nl',
            'theme' => $user->theme ?? 'light',
            'preferences_data' => $user->preferences_data ?? $this->getDefaultPreferences(),
        ];
    }

    public function updateUserPreferences(array $newPreferences, Request $request): User
    {
        /** @var User $user */
        $user = Auth::user();

        $oldPreferences = [
            'language' => $user->language,
            'theme' => $user->theme,
            'preferences_data' => $user->preferences_data,
        ];

        $updateData = [];
        $changedFields = [];

        if (isset($newPreferences['language']) && $newPreferences['language'] !== $user->language) {
            $updateData['language'] = $newPreferences['language'];
            $changedFields['language'] = [
                'old' => $user->language,
                'new' => $newPreferences['language'],
            ];
        }

        if (isset($newPreferences['theme']) && $newPreferences['theme'] !== $user->theme) {
            $updateData['theme'] = $newPreferences['theme'];
            $changedFields['theme'] = [
                'old' => $user->theme,
                'new' => $newPreferences['theme'],
            ];
        }

        if (isset($newPreferences['preferences_data'])) {
            $currentPreferencesData = $user->preferences_data ?? [];
            $newPreferencesData = array_replace_recursive($currentPreferencesData, $newPreferences['preferences_data']);

            if ($newPreferencesData !== $currentPreferencesData) {
                $updateData['preferences_data'] = $newPreferencesData;
                $changedFields['preferences_data'] = [
                    'old' => $currentPreferencesData,
                    'new' => $newPreferencesData,
                ];
            }
        }

        if (! empty($updateData)) {
            $user->update($updateData);

            $this->logPreferencesChange(
                user: $user,
                oldPreferences: $oldPreferences,
                newPreferences: [
                    'language' => $user->fresh()->language,
                    'theme' => $user->fresh()->theme,
                    'preferences_data' => $user->fresh()->preferences_data,
                ],
                changedFields: $changedFields,
                request: $request
            );
        }

        return $user->fresh();
    }

    public function getDefaultPreferences(): array
    {
        return [
            'cache' => [
                'clientsAutoRefresh' => true,
                'clientsCacheDuration' => 24,
                'complexesAutoRefresh' => true,
                'complexesCacheDuration' => 12,
                'terrainsAutoRefresh' => true,
                'terrainsCacheDuration' => 6,
                'autoRefreshOnLogin' => true,
                'backgroundSync' => true,
                'compressionEnabled' => true,
                'maxCacheSize' => 100,
            ],
            'server' => [
                'autoSync' => true,
                'syncInterval' => 30,
                'offlineMode' => false,
                'dataPreloading' => true,
                'notificationsEnabled' => true,
                'debugMode' => false,
                'apiTimeout' => 30,
                'retryAttempts' => 3,
            ],
            'notifications' => [
                'fieldopsDatabase' => true,
                'fieldopsEmail' => true,
            ],
        ];
    }

    public function applyDefaultPreferences(User $user): User
    {
        if (empty($user->preferences_data)) {
            $user->update([
                'preferences_data' => $this->getDefaultPreferences(),
                'language' => $user->language ?? 'nl',
                'theme' => $user->theme ?? 'light',
            ]);
        }

        return $user->fresh();
    }

    private function logPreferencesChange(
        User $user,
        array $oldPreferences,
        array $newPreferences,
        array $changedFields,
        Request $request
    ): void {
        UserPreferencesLog::create([
            'user_id' => $user->id,
            'old_preferences' => $oldPreferences,
            'new_preferences' => $newPreferences,
            'changed_fields' => $changedFields,
            'changed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function getPreferencesHistory(int $userId, int $limit = 10): array
    {
        return UserPreferencesLog::where('user_id', $userId)
            ->orderBy('changed_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function validatePreferencesStructure(array $preferences): bool
    {
        $requiredStructure = [
            'cache' => [
                'clientsAutoRefresh',
                'clientsCacheDuration',
                'complexesAutoRefresh',
                'complexesCacheDuration',
                'terrainsAutoRefresh',
                'terrainsCacheDuration',
                'autoRefreshOnLogin',
                'backgroundSync',
                'compressionEnabled',
                'maxCacheSize',
            ],
            'server' => [
                'autoSync',
                'syncInterval',
                'offlineMode',
                'dataPreloading',
                'notificationsEnabled',
                'debugMode',
                'apiTimeout',
                'retryAttempts',
            ],
        ];

        foreach ($requiredStructure as $section => $fields) {
            if (! isset($preferences[$section])) {
                return false;
            }

            foreach ($fields as $field) {
                if (! array_key_exists($field, $preferences[$section])) {
                    return false;
                }
            }
        }

        return true;
    }
}
