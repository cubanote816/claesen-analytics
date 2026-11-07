<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * CLA-661 — the backoffice has no internet egress. Direct LAN requests to the
 * backoffice host get local storage URLs; tunnel requests carry the same Host
 * (forced by 192.168.60.10) but come from 127.0.0.1 and must keep MEDIA_URL.
 * The decision reads the connection address, so trusting the tunnel's
 * X-Forwarded-For cannot turn public visitors into "LAN" requests.
 */
class StorageUrlTunnelDetectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://backoffice.claesen.local']);
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

        parent::tearDown();
    }

    public function test_tunnel_request_keeps_media_url_even_when_the_proxy_is_trusted(): void
    {
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
        $request = Request::create('https://backoffice.claesen.local/media', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
        ]);

        $this->assertSame('203.0.113.7', $request->ip());
        $this->assertFalse(AppServiceProvider::servesLocalStorageUrls($request));
    }

    public function test_direct_lan_request_to_the_backoffice_host_gets_local_storage_urls(): void
    {
        $request = Request::create('https://backoffice.claesen.local/media', server: [
            'REMOTE_ADDR' => '192.168.254.51',
        ]);

        $this->assertTrue(AppServiceProvider::servesLocalStorageUrls($request));
    }

    public function test_request_to_another_host_keeps_media_url(): void
    {
        $request = Request::create('https://service.claesen-verlichting.be/media', server: [
            'REMOTE_ADDR' => '192.168.254.51',
        ]);

        $this->assertFalse(AppServiceProvider::servesLocalStorageUrls($request));
    }
}
