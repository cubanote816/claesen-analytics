<?php

namespace Modules\Intelligence\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CLA-440 — replaces GeminiImageGenerationService (CLA-409). Real side-by-side
 * comparison against gpt-image-2 showed Gemini 2.5 Flash Image's catalog
 * illustrations far below usable quality; gpt-image-2 also produces genuine
 * native alpha transparency (verified against real PNG bytes, not just the
 * API's own claim) — no chroma-key post-processing step needed, unlike Gemini.
 *
 * Two separate calls, not one: gpt-image-2 (unlike Gemini's one-shot
 * multimodal response) never returns text alongside the generated image, so
 * the suggested catalog name comes from a second, small vision-capable chat
 * completion fed the GENERATED illustration (not the technician's raw photo)
 * using Structured Outputs — no fragile "SUGGESTED_NAME: ..." text parsing.
 * A naming failure never discards a valid image: status stays "generated"
 * with suggested_name=null.
 *
 * Time budget is deliberate, not just a preference: the public proxy in
 * front of this API (sbapu03, backend.claesen-verlichting.be's /api/ block)
 * has proxy_read_timeout 60s. Measured real image-generation latency at
 * quality=medium was 56-61s (no safety margin, one run already exceeded it);
 * at quality=low it's 25-28s — still dramatically better than Gemini's best
 * output — leaving real margin for the ~5s naming call on top.
 */
class OpenAiImageGenerationService
{
    private const IMAGE_TIMEOUT_SECONDS = 45;

    private const NAME_TIMEOUT_SECONDS = 8;

    private const IMAGE_SIZE = '1024x1024';

    private const REFERENCE_FILES = [
        'curved-stadium-headframe.png',
        'fixed-cross-arm-headframe.png',
        'fixed-platform-stadium-headframe.png',
        'lowering-headframe.png',
        'oval-stadium-headframe.png',
        'tubular-cage-headframe.png',
    ];

    /**
     * @return array{status: string, image_base64: ?string, mime_type: ?string, suggested_name: ?string}
     */
    public function generateFrameTypeImage(string $photoBase64, string $photoMediaType): array
    {
        $fail = ['status' => 'failed', 'image_base64' => null, 'mime_type' => null, 'suggested_name' => null];

        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            Log::error('OpenAiImageGenerationService: no API key configured.');

            return $fail;
        }

        $referenceFiles = $this->buildReferenceFiles();

        if (empty($referenceFiles)) {
            Log::error('OpenAiImageGenerationService: no reference catalog images found on disk.');

            return $fail;
        }

        $downscaledPhoto = $this->downscaleFromBytes(base64_decode($photoBase64), 900);

        if ($downscaledPhoto === null) {
            Log::error('OpenAiImageGenerationService: could not decode the technician photo.');

            return $fail;
        }

        $image = $this->requestImage($apiKey, $referenceFiles, $downscaledPhoto);

        if ($image === null) {
            return $fail;
        }

        return [
            'status' => 'generated',
            'image_base64' => $image['base64'],
            'mime_type' => $image['mime_type'],
            'suggested_name' => $this->requestName($apiKey, $image['base64'], $image['mime_type']),
        ];
    }

    /**
     * @param array<int, string> $referenceFiles Raw bytes, downscaled.
     * @return array{base64: string, mime_type: string}|null
     */
    protected function requestImage(string $apiKey, array $referenceFiles, string $photoBytes): ?array
    {
        try {
            $request = Http::withToken($apiKey)->timeout(self::IMAGE_TIMEOUT_SECONDS);

            foreach ($referenceFiles as $i => $bytes) {
                $request = $request->attach('image[]', $bytes, "reference_{$i}.jpg");
            }

            $request = $request->attach('image[]', $photoBytes, 'photo.jpg');

            $response = $request->post('https://api.openai.com/v1/images/edits', [
                'model' => config('services.openai.image_model', 'gpt-image-2'),
                'prompt' => $this->buildImagePrompt(),
                'background' => 'transparent',
                'output_format' => 'png',
                'size' => self::IMAGE_SIZE,
                'quality' => config('services.openai.image_quality', 'low'),
            ]);

            if ($response->failed()) {
                Log::error('OpenAiImageGenerationService: image request failed.', ['body' => $response->body()]);

                return null;
            }

            $b64 = $response->json('data.0.b64_json');

            if (empty($b64)) {
                Log::warning('OpenAiImageGenerationService: response had no image data.');

                return null;
            }

            return ['base64' => $b64, 'mime_type' => 'image/png'];
        } catch (\Throwable $e) {
            Log::error('OpenAiImageGenerationService: image request exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Naming failure never discards a valid generated image — callers treat
     * a null return as "generated without a suggested name", not a failure.
     */
    protected function requestName(string $apiKey, string $imageBase64, string $mimeType): ?string
    {
        try {
            $response = Http::withToken($apiKey)
                ->timeout(self::NAME_TIMEOUT_SECONDS)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.name_model', 'gpt-5.4-mini-2026-03-17'),
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $this->buildNamingPrompt()],
                            ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]],
                        ],
                    ]],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'frame_type_name',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => ['name' => ['type' => 'string']],
                                'required' => ['name'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('OpenAiImageGenerationService: naming request failed.', ['body' => $response->body()]);

                return null;
            }

            $content = $response->json('choices.0.message.content');
            $decoded = json_decode((string) $content, true);
            $name = $decoded['name'] ?? null;

            return is_string($name) && $name !== '' ? $name : null;
        } catch (\Throwable $e) {
            Log::warning('OpenAiImageGenerationService: naming request exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @return array<int, string> Raw downscaled JPEG bytes, one per reference file found on disk.
     */
    protected function buildReferenceFiles(): array
    {
        $files = [];

        foreach (self::REFERENCE_FILES as $file) {
            $path = public_path('assets/frame-types/'.$file);

            if (! is_readable($path)) {
                continue;
            }

            $downscaled = $this->downscaleFromBytes((string) file_get_contents($path), 300);

            if ($downscaled !== null) {
                $files[] = $downscaled;
            }
        }

        return $files;
    }

    /**
     * Same downscale rationale as the reference/photo handling this replaces
     * (payload size, cost, latency) — kept even though the Gemini-specific
     * upstream anti-abuse block this originally worked around doesn't apply
     * to OpenAI's API.
     */
    protected function downscaleFromBytes(string $bytes, int $maxDimension): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($bytes);

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxDimension / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        // Flatten onto white first — a transparent PNG source has no JPEG
        // alpha equivalent, so transparent areas would otherwise turn black.
        $flattened = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flattened, 255, 255, 255);
        imagefill($flattened, 0, 0, $white);
        imagealphablending($flattened, true);
        imagecopy($flattened, $source, 0, 0, 0, 0, $width, $height);

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $flattened, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($resized, null, 80);
        $data = ob_get_clean();

        imagedestroy($source);
        imagedestroy($flattened);
        imagedestroy($resized);

        return $data === false ? null : $data;
    }

    protected function buildImagePrompt(): string
    {
        return <<<'PROMPT'
You are generating a technical catalog illustration for a sports-lighting contractor's internal parts catalog. The last attached image is a real photo taken by a field technician of a physical lighting frame structure at a job site (it may have mounted luminaires, background scenery, or clutter — ignore all of that, judge only the bare frame structure itself: mast, arms, tiers, mounting mechanism). The preceding attached images are the exact house visual style already used for every other frame-type catalog entry: isolated structure only, no luminaires mounted, no background scenery, flat even lighting, slightly technical/illustrative rendering (not a real photograph).

Generate a NEW image of the SAME physical frame structure shown in the technician's photo, redrawn from scratch in that exact catalog house style (same camera angle/framing conventions, same grey/silver structure with subtle green accents where applicable). Do not literally edit or crop the technician's photo — draw a clean illustration of the same structure. The background must be fully transparent.
PROMPT;
    }

    protected function buildNamingPrompt(): string
    {
        return <<<'PROMPT'
Suggest a short catalog name for this lighting frame structure, following the exact same naming convention as these existing catalog entries: "Curved stadium headframe", "Fixed cross-arm headframe", "Fixed platform stadium headframe", "Lowering headframe", "Oval stadium headframe", "Tubular cage headframe" (structural shape + "headframe", Title Case, in English).
PROMPT;
    }
}
