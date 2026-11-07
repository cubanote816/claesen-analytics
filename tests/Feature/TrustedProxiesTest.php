<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * CLA-661 — public traffic reaches this app through the reverse SSH tunnel
 * from 192.168.60.10, so its connection address is always 127.0.0.1. The
 * edge appends the real client to X-Forwarded-For; only that hop, and only
 * that header, is trusted.
 */
class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/__test/client', fn (Request $request) => response()->json([
            'ip' => $request->ip(),
            'host' => $request->getHost(),
            'scheme' => $request->getScheme(),
            'port' => $request->getPort(),
        ]));
    }

    public function test_tunnel_request_resolves_the_address_appended_by_the_edge(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.66, 203.0.113.7'])
            ->get('/__test/client')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.7');
    }

    public function test_tunnel_request_without_forwarded_for_keeps_loopback(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/__test/client')
            ->assertOk()
            ->assertJsonPath('ip', '127.0.0.1');
    }

    public function test_direct_lan_request_ignores_forwarded_for(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.254.51'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
            ->get('/__test/client')
            ->assertOk()
            ->assertJsonPath('ip', '192.168.254.51');
    }

    public function test_only_forwarded_for_is_trusted_from_the_tunnel(): void
    {
        $baseline = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/__test/client')
            ->json();

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-For' => '203.0.113.7',
                'X-Forwarded-Host' => 'evil.example',
                'X-Forwarded-Proto' => $baseline['scheme'] === 'https' ? 'http' : 'https',
                'X-Forwarded-Port' => '8443',
            ])
            ->get('/__test/client')
            ->assertOk()
            ->assertJsonPath('ip', '203.0.113.7')
            ->assertJsonPath('host', $baseline['host'])
            ->assertJsonPath('scheme', $baseline['scheme'])
            ->assertJsonPath('port', $baseline['port']);
    }
}
