<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Modules\Intelligence\Services\OpenAiImageGenerationService;
use Tests\TestCase;

/**
 * CLA-440 — replaces GeminiImageGenerationServiceTest. Abstention rules mirror
 * the rest of the vision family: a missing key, missing references, an
 * undecodable photo, a failed/timed-out request, or a response with no image
 * part never throws — always resolves to status=failed. A naming failure is
 * different: it must never discard an otherwise valid generated image.
 */
class OpenAiImageGenerationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'fake-key',
            'services.openai.image_model' => 'gpt-image-2',
            'services.openai.image_quality' => 'low',
            'services.openai.name_model' => 'gpt-5.4-nano-2026-03-17',
        ]);
    }

    public function test_it_fails_without_hitting_the_network_when_no_api_key_is_configured(): void
    {
        config(['services.openai.key' => null]);

        Http::fake(function () {
            $this->fail('HTTP request should never be sent without an API key.');
        });

        $result = (new OpenAiImageGenerationService())->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('failed', $result['status']);
        $this->assertNull($result['image_base64']);
        $this->assertNull($result['suggested_name']);
    }

    public function test_it_fails_without_hitting_the_network_when_no_reference_images_are_found(): void
    {
        Http::fake(function () {
            $this->fail('HTTP request should never be sent without reference images.');
        });

        $service = new class extends OpenAiImageGenerationService
        {
            protected function buildReferenceFiles(): array
            {
                return [];
            }
        };

        $result = $service->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('failed', $result['status']);
    }

    public function test_it_fails_when_the_technician_photo_cannot_be_decoded(): void
    {
        Http::fake(function () {
            $this->fail('HTTP request should never be sent for an undecodable photo.');
        });

        $result = (new OpenAiImageGenerationService())
            ->generateFrameTypeImage(base64_encode('not a real image'), 'image/jpeg');

        $this->assertSame('failed', $result['status']);
    }

    public function test_it_fails_gracefully_when_the_image_request_itself_fails(): void
    {
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        $result = (new OpenAiImageGenerationService())->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('failed', $result['status']);
    }

    public function test_it_fails_when_the_image_response_has_no_image_data(): void
    {
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response(['data' => [[]]], 200),
        ]);

        $result = (new OpenAiImageGenerationService())->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('failed', $result['status']);
    }

    public function test_it_sends_all_seven_images_with_the_photo_last_and_the_correct_parameters(): void
    {
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response([
                'data' => [['b64_json' => base64_encode($this->magentaPngBytes())]],
            ], 200),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['name' => 'Test headframe'])]]],
            ], 200),
        ]);

        (new OpenAiImageGenerationService())->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.openai.com/v1/images/edits') {
                return true;
            }

            $body = (string) $request->body();

            $this->assertSame(7, substr_count($body, 'name="image[]"'));
            $this->assertStringContainsString('reference_5.jpg', $body);
            $this->assertStringContainsString('photo.jpg', $body);
            $this->assertLessThan(strpos($body, 'photo.jpg'), strpos($body, 'reference_5.jpg'));
            $this->assertStringContainsString('gpt-image-2', $body);
            $this->assertStringContainsString('transparent', $body);
            $this->assertStringContainsString('png', $body);
            $this->assertStringContainsString('1024x1024', $body);
            $this->assertStringContainsString('low', $body);

            return true;
        });
    }

    public function test_it_sends_the_configured_model_in_the_naming_request(): void
    {
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response([
                'data' => [['b64_json' => base64_encode($this->magentaPngBytes())]],
            ], 200),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['name' => 'Test headframe'])]]],
            ], 200),
        ]);

        (new OpenAiImageGenerationService())->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.openai.com/v1/chat/completions') {
                return false;
            }

            $this->assertSame('gpt-5.4-nano-2026-03-17', $request->data()['model']);

            return true;
        });
    }

    public function test_it_returns_the_image_and_suggested_name_on_success(): void
    {
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response([
                'data' => [['b64_json' => base64_encode($this->magentaPngBytes())]],
            ], 200),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['name' => 'Test stadium headframe'])]]],
            ], 200),
        ]);

        $result = (new OpenAiImageGenerationService())->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('generated', $result['status']);
        $this->assertSame('image/png', $result['mime_type']);
        $this->assertSame('Test stadium headframe', $result['suggested_name']);
        // Bytes must pass through untouched — no chroma-key/GD reprocessing,
        // unlike GeminiImageGenerationService (native transparency here).
        $this->assertSame(base64_encode($this->magentaPngBytes()), $result['image_base64']);
    }

    public function test_a_failed_naming_call_still_returns_the_valid_generated_image(): void
    {
        Http::fake([
            'https://api.openai.com/v1/images/edits' => Http::response([
                'data' => [['b64_json' => base64_encode($this->magentaPngBytes())]],
            ], 200),
            'https://api.openai.com/v1/chat/completions' => Http::response(['error' => ['message' => 'boom']], 500),
        ]);

        $result = (new OpenAiImageGenerationService())->generateFrameTypeImage($this->tinyPngBase64(), 'image/jpeg');

        $this->assertSame('generated', $result['status']);
        $this->assertNotNull($result['image_base64']);
        $this->assertNull($result['suggested_name']);
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

    private function magentaPngBytes(): string
    {
        $image = imagecreatetruecolor(10, 10);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 255));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
