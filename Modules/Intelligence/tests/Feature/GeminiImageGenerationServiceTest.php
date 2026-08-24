<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Mockery;
use Modules\Intelligence\Services\GeminiImageGenerationService;
use Modules\Intelligence\Services\GoogleServiceAccountAuthService;
use Tests\TestCase;

/**
 * CLA-409 (CLA-390 Fase 3) — abstention rules mirror the rest of the vision
 * family: a missing token or an unusable API response never throws, always
 * resolves to status=failed so the caller falls back to manual frame-type
 * creation, exactly like ClaudeVisionService's "unknown" outcomes.
 */
class GeminiImageGenerationServiceTest extends TestCase
{
    public function test_it_fails_without_hitting_the_network_when_no_access_token_is_available(): void
    {
        $auth = Mockery::mock(GoogleServiceAccountAuthService::class);
        $auth->shouldReceive('getAccessToken')->once()->andReturn(null);

        Http::fake(function () {
            $this->fail('HTTP request should never be sent without a token.');
        });

        $service = new GeminiImageGenerationService($auth);
        $result = $service->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['image_base64']);
        $this->assertNull($result['suggested_name']);
    }

    public function test_it_fails_when_the_api_response_has_no_image_part(): void
    {
        $auth = Mockery::mock(GoogleServiceAccountAuthService::class);
        $auth->shouldReceive('getAccessToken')->once()->andReturn('fake-token');

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'I could not generate an image.']]],
                ]],
            ], 200),
        ]);

        $service = new GeminiImageGenerationService($auth);
        $result = $service->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('failed', $result['status']);
    }

    public function test_it_fails_gracefully_when_the_api_request_itself_fails(): void
    {
        $auth = Mockery::mock(GoogleServiceAccountAuthService::class);
        $auth->shouldReceive('getAccessToken')->once()->andReturn('fake-token');

        Http::fake([
            '*' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        $service = new GeminiImageGenerationService($auth);
        $result = $service->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('failed', $result['status']);
    }

    public function test_it_extracts_the_suggested_name_and_chroma_keys_the_background(): void
    {
        $auth = Mockery::mock(GoogleServiceAccountAuthService::class);
        $auth->shouldReceive('getAccessToken')->once()->andReturn('fake-token');

        // A tiny real PNG (solid magenta square) so the chroma-key step has
        // real image bytes to process, not a fake string.
        $magenta = imagecreatetruecolor(10, 10);
        imagefill($magenta, 0, 0, imagecolorallocate($magenta, 255, 0, 255));
        ob_start();
        imagepng($magenta);
        $pngBytes = ob_get_clean();
        imagedestroy($magenta);

        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($pngBytes)]],
                        ['text' => "Here you go.\nSUGGESTED_NAME: Test stadium headframe\n"],
                    ]],
                ]],
            ], 200),
        ]);

        $service = new GeminiImageGenerationService($auth);
        $result = $service->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('generated', $result['status']);
        $this->assertSame('Test stadium headframe', $result['suggested_name']);
        $this->assertSame('image/png', $result['mime_type']);
        $this->assertNotNull($result['image_base64']);

        // The whole 10x10 square is background — the chroma-keyed output
        // should be entirely transparent at the corner.
        $processed = imagecreatefromstring(base64_decode($result['image_base64']));
        $rgba = imagecolorat($processed, 1, 1);
        $alpha = ($rgba >> 24) & 0x7F;
        $this->assertSame(127, $alpha);
    }

    private function tinyPngBase64(): string
    {
        $image = imagecreatetruecolor(4, 4);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 200, 200));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return base64_encode($bytes);
    }
}
