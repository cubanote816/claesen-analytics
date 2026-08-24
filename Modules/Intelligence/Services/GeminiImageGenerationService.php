<?php

namespace Modules\Intelligence\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CLA-409 (CLA-390 Fase 3) — generates a catalog-style illustration of a
 * physical luminaire frame from a technician's field photo, redrawn in the
 * house visual style already used by the frame-type catalog (the reference
 * images in public/assets/frame-types/), for frames that Phase 1
 * (ClaudeVisionService::identifyFrameType) couldn't match against an
 * existing catalog entry.
 *
 * Real image generation (Gemini 2.5 Flash Image via Vertex AI, same auth as
 * GoogleServiceAccountAuthService) — a genuinely different capability from
 * ClaudeVisionService's identification/detection, which never generates
 * anything. Confirmed live (2026-08-21/24) that asking the model directly
 * for a "transparent background" produces a false claim (it renders a solid
 * black background instead, verified by inspecting the real PNG color type,
 * not by trusting the model's own description of what it did) — so this
 * asks for a flat, uniform, single-color background instead (which the
 * model reliably produces) and converts that to real alpha transparency in
 * PHP afterwards (samplePixel + color-distance chroma key), matching the
 * genuine transparency already present in the 6 reference catalog images.
 */
class GeminiImageGenerationService
{
    private const MODEL = 'gemini-2.5-flash-image';

    private const CHROMA_KEY_THRESHOLD = 40;

    public function __construct(protected GoogleServiceAccountAuthService $auth)
    {
    }

    /**
     * @return array{status: string, image_base64: ?string, mime_type: ?string, suggested_name: ?string}
     */
    public function generateFrameTypeImage(string $photoBase64, string $photoMediaType): array
    {
        $fail = ['status' => 'failed', 'image_base64' => null, 'mime_type' => null, 'suggested_name' => null];

        $token = $this->auth->getAccessToken();

        if (empty($token)) {
            Log::error('GeminiImageGenerationService: could not obtain a service account access token.');

            return $fail;
        }

        $referenceParts = $this->buildReferenceParts();

        if (empty($referenceParts)) {
            Log::error('GeminiImageGenerationService: no reference catalog images found on disk.');

            return $fail;
        }

        $project = config('services.gemini.vertex_project', 'gen-lang-client-0849598291');
        $location = config('services.gemini.vertex_location', 'us-central1');
        $url = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$project}/locations/{$location}/publishers/google/models/".self::MODEL.':generateContent';

        // A real phone photo can be several MB — well past the size that
        // triggers the upstream block confirmed above — so it's downscaled
        // the same way as the reference images, just to a larger dimension
        // since it's the primary subject the model has to read structure
        // details from.
        $downscaledPhoto = $this->downscaleFromBytes(base64_decode($photoBase64), 900);

        if ($downscaledPhoto === null) {
            Log::error('GeminiImageGenerationService: could not decode the technician photo.');

            return $fail;
        }

        $parts = array_merge(
            [
                ['text' => $this->buildPrompt()],
                ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $downscaledPhoto]],
            ],
            $referenceParts,
        );

        try {
            $response = Http::timeout(60)->withToken($token)->post($url, [
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => ['responseModalities' => ['IMAGE', 'TEXT']],
            ]);

            if ($response->failed()) {
                Log::error('GeminiImageGenerationService: request failed.', ['body' => $response->body()]);

                return $fail;
            }

            $responseParts = $response->json('candidates.0.content.parts', []);

            $imageBase64 = null;
            $mimeType = null;
            $text = '';

            foreach ($responseParts as $part) {
                if (isset($part['inlineData']['data'])) {
                    $imageBase64 = $part['inlineData']['data'];
                    $mimeType = $part['inlineData']['mimeType'] ?? 'image/png';
                } elseif (isset($part['text'])) {
                    $text .= $part['text'];
                }
            }

            if ($imageBase64 === null) {
                Log::warning('GeminiImageGenerationService: response had no image part.');

                return $fail;
            }

            $transparentBase64 = $this->applyChromaKeyTransparency($imageBase64);

            return [
                'status' => 'generated',
                'image_base64' => $transparentBase64 ?? $imageBase64,
                'mime_type' => 'image/png',
                'suggested_name' => $this->extractSuggestedName($text),
            ];
        } catch (\Throwable $e) {
            Log::error('GeminiImageGenerationService Exception: '.$e->getMessage());

            return $fail;
        }
    }

    /**
     * Sending all 6 reference images at full size (plus the technician's
     * photo) produces a request large enough (~1.4MB JSON body, confirmed
     * live) to get intercepted upstream with a generic Google "automated
     * queries" block page instead of a real API response — smaller requests
     * (1-3 references) reach Vertex's real logic cleanly. Downscaling the
     * references to a small JPEG for the API call only (never touching the
     * stored catalog originals) keeps all 6 as style guidance while staying
     * well under that threshold — style/color/proportions don't need full
     * resolution for this purpose.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildReferenceParts(): array
    {
        $files = [
            'curved-stadium-headframe.png',
            'fixed-cross-arm-headframe.png',
            'fixed-platform-stadium-headframe.png',
            'lowering-headframe.png',
            'oval-stadium-headframe.png',
            'tubular-cage-headframe.png',
        ];

        $parts = [];

        foreach ($files as $file) {
            $path = public_path('assets/frame-types/'.$file);

            if (! is_readable($path)) {
                continue;
            }

            $downscaled = $this->downscaleFromBytes((string) file_get_contents($path), 300);

            if ($downscaled === null) {
                continue;
            }

            $parts[] = [
                'inline_data' => [
                    'mime_type' => 'image/jpeg',
                    'data' => $downscaled,
                ],
            ];
        }

        return $parts;
    }

    /**
     * A real phone photo (JPEG, no alpha) and the reference catalog PNGs
     * (transparent) both go through this, re-encoded as JPEG at a bounded
     * dimension — keeps the request body well under the size that triggers
     * the upstream block confirmed live, regardless of what the caller's
     * device/screenshot tool produced.
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

        return $data === false ? null : base64_encode($data);
    }

    protected function buildPrompt(): string
    {
        return <<<'PROMPT'
You are generating a technical catalog illustration for a sports-lighting contractor's internal parts catalog. The first attached image is a real photo taken by a field technician of a physical lighting frame structure at a job site (it may have mounted luminaires, background scenery, or clutter — ignore all of that, judge only the bare frame structure itself: mast, arms, tiers, mounting mechanism). The remaining attached images are the exact house visual style already used for every other frame-type catalog entry: isolated structure only, no luminaires mounted, no background scenery, flat even lighting, slightly technical/illustrative rendering (not a real photograph).

Task 1 — Image: generate a NEW image of the SAME physical frame structure shown in the technician's photo, redrawn from scratch in that exact catalog house style (same camera angle/framing conventions, same grey/silver structure with subtle green accents where applicable). Do not literally edit or crop the technician's photo — draw a clean illustration of the same structure.

CRITICAL BACKGROUND REQUIREMENT: the background must be a single, perfectly flat, uniform solid color, edge to edge, no gradient, no shadow, no vignette, no texture — chosen so it visually contrasts with the grey/silver/green structure (a saturated color such as magenta works well). This is required for automated background removal in post-processing and is more important than photorealism.

Task 2 — Name: suggest a short catalog name for this frame type, following the exact same naming convention as these existing catalog entries: "Curved stadium headframe", "Fixed cross-arm headframe", "Fixed platform stadium headframe", "Lowering headframe", "Oval stadium headframe", "Tubular cage headframe" (structural shape + "headframe", Title Case, in English). Return it as a text part in exactly this format on its own line: SUGGESTED_NAME: <name>
PROMPT;
    }

    protected function extractSuggestedName(string $text): ?string
    {
        if (preg_match('/SUGGESTED_NAME:\s*(.+)/i', $text, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Gemini reliably produces a flat solid-color background when asked for
     * one, but not genuine alpha transparency when asked for that directly
     * (verified against the real PNG bytes, not the model's own claim). This
     * samples the actual background color from a corner pixel and converts
     * every pixel within a color-distance threshold to real transparency,
     * matching the genuine alpha channel already present in the 6 reference
     * catalog images.
     */
    protected function applyChromaKeyTransparency(string $imageBase64): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = base64_decode($imageBase64);
        $image = @imagecreatefromstring($raw);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $bgColor = imagecolorat($image, 2, 2);
        $bgR = ($bgColor >> 16) & 0xFF;
        $bgG = ($bgColor >> 8) & 0xFF;
        $bgB = $bgColor & 0xFF;

        $out = imagecreatetruecolor($width, $height);
        imagesavealpha($out, true);
        imagealphablending($out, false);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;

                $distance = sqrt((($r - $bgR) ** 2) + (($g - $bgG) ** 2) + (($b - $bgB) ** 2));
                $alpha = $distance < self::CHROMA_KEY_THRESHOLD ? 127 : 0;

                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $alpha));
            }
        }

        ob_start();
        imagepng($out);
        $processed = ob_get_clean();

        imagedestroy($image);
        imagedestroy($out);

        return $processed === false ? null : base64_encode($processed);
    }
}
