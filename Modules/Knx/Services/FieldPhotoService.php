<?php

declare(strict_types=1);

namespace Modules\Knx\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The photos the field app attaches, in one place.
 *
 * Both field writes carry a `photoDataUrl` — a device registration (V11.c) and a
 * reported incident (V11.d) — and both must answer the same way when the app sends
 * something that is not an image, so the rules live here instead of being written
 * twice and drifting apart.
 *
 * Two encodings are accepted because the app produces both: `;base64` is what a
 * camera gives through a canvas, and the percent-encoded form is what its own
 * fixture generates for its inline SVGs.
 */
class FieldPhotoService
{
    /** What one photo may weigh once decoded. Cameras rarely exceed this. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** The image types we accept, and the extension to store them under. */
    private const EXTENSIONS = [
        'png' => 'png',
        'jpeg' => 'jpg',
        'jpg' => 'jpg',
        'webp' => 'webp',
        'gif' => 'gif',
        'svg+xml' => 'svg',
    ];

    /**
     * Decode a data URL and put it on disk, answering the stored path.
     *
     * The file is named after the app's own `clientId`, so a retry of the same
     * registration can never store the same photo twice under two names.
     */
    public function store(string $clientId, ?string $dataUrl): ?string
    {
        if ($dataUrl === null || $dataUrl === '') {
            return null;
        }

        // The header may carry parameters between the subtype and the comma:
        // `;base64` from a canvas, `;utf8` / `;charset=utf-8` from an inline SVG.
        // Parsing the three parts beats one pattern per encoding.
        if (! preg_match('#^data:image/([a-z0-9.+-]+)(;[^,]*)?,(.*)$#is', $dataUrl, $matches)) {
            throw ValidationException::withMessages(['photoDataUrl' => [__('knx::field.photo_invalid')]]);
        }

        $extension = self::EXTENSIONS[strtolower($matches[1])] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages(['photoDataUrl' => [__('knx::field.photo_unsupported')]]);
        }

        $parameters = strtolower($matches[2] ?? '');

        $bytes = str_contains($parameters, 'base64')
            ? base64_decode($matches[3], true)
            : rawurldecode($matches[3]);

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages(['photoDataUrl' => [__('knx::field.photo_invalid')]]);
        }

        if (strlen($bytes) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['photoDataUrl' => [__('knx::field.photo_too_large')]]);
        }

        $path = 'knx/devices/'.$clientId.'.'.$extension;

        Storage::disk('local')->put($path, $bytes);

        return $path;
    }
}
