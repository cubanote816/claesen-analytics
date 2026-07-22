<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAdvancedPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => 'sometimes|string|in:nl,en,fr,es',
            'theme' => 'sometimes|string|in:light,dark',
            'preferences_data' => 'sometimes|array',

            'preferences_data.cache' => 'sometimes|array',
            'preferences_data.cache.clientsAutoRefresh' => 'sometimes|boolean',
            'preferences_data.cache.clientsCacheDuration' => 'sometimes|integer|min:1|max:168',
            'preferences_data.cache.complexesAutoRefresh' => 'sometimes|boolean',
            'preferences_data.cache.complexesCacheDuration' => 'sometimes|integer|min:1|max:168',
            'preferences_data.cache.terrainsAutoRefresh' => 'sometimes|boolean',
            'preferences_data.cache.terrainsCacheDuration' => 'sometimes|integer|min:1|max:168',
            'preferences_data.cache.autoRefreshOnLogin' => 'sometimes|boolean',
            'preferences_data.cache.backgroundSync' => 'sometimes|boolean',
            'preferences_data.cache.compressionEnabled' => 'sometimes|boolean',
            'preferences_data.cache.maxCacheSize' => 'sometimes|integer|min:10|max:1000',

            'preferences_data.server' => 'sometimes|array',
            'preferences_data.server.autoSync' => 'sometimes|boolean',
            'preferences_data.server.syncInterval' => 'sometimes|integer|min:5|max:300',
            'preferences_data.server.offlineMode' => 'sometimes|boolean',
            'preferences_data.server.dataPreloading' => 'sometimes|boolean',
            'preferences_data.server.notificationsEnabled' => 'sometimes|boolean',
            'preferences_data.server.debugMode' => 'sometimes|boolean',
            'preferences_data.server.apiTimeout' => 'sometimes|integer|min:5|max:120',
            'preferences_data.server.retryAttempts' => 'sometimes|integer|min:1|max:10',

            'preferences_data.notifications' => 'sometimes|array',
            'preferences_data.notifications.fieldopsDatabase' => 'sometimes|boolean',
            'preferences_data.notifications.fieldopsEmail' => 'sometimes|boolean',

            'preferences_data.useServerPreferences' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'language.in' => 'El idioma debe ser uno de: español (es), inglés (en), holandés (nl), francés (fr).',
            'theme.in' => 'El tema debe ser: light o dark.',
        ];
    }
}
