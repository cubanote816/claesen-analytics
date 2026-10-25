<?php

declare(strict_types=1);


namespace Modules\Core\Tests\Unit;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\GenericUser;
use Illuminate\Auth\SessionGuard;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Cookie\CookieJar;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Modules\Core\Listeners\ClearAuthenticatedViaMarker;
use Modules\Core\Support\AuthenticatedVia;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SocialiteProviders\Azure\Provider;

final class AuthMarkerTrustTest extends TestCase
{
    private Container $previous;
    private Store $session;
    private Request $request;

    protected function setUp(): void
    {
        $this->previous = Container::getInstance();
        Container::setInstance(new Container);
        $this->session = new Store('probe', new ArraySessionHandler(120));
        $this->session->start();
        $this->request = Request::create('/');
        $this->request->setLaravelSession($this->session);
        Container::getInstance()->instance('request', $this->request);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Container::setInstance($this->previous);
    }

    private function guard(): SessionGuard
    {
        $user = new GenericUser(['id' => 2, 'password' => 'hash', 'remember_token' => 'remember']);
        $provider = Mockery::mock(UserProvider::class);
        $provider->shouldReceive('retrieveById', 'retrieveByCredentials', 'retrieveByToken')->andReturn($user);
        $provider->shouldReceive('validateCredentials')->andReturn(true);
        $provider->shouldReceive('rehashPasswordIfRequired', 'updateRememberToken')->andReturnNull();
        $events = new Dispatcher(Container::getInstance());
        $events->listen([Login::class, Logout::class], ClearAuthenticatedViaMarker::class);
        $guard = new SessionGuard('web', $provider, $this->session, $this->request);
        $guard->setDispatcher($events);
        $guard->setCookieJar(new CookieJar);
        return $guard;
    }

    // S2c: another user and every persistent guard entry point must clear synchronously.
    public function test_cross_user_persistent_sign_ins_clear_the_marker(): void
    {
        foreach (['login', 'loginUsingId', 'attempt', 'attemptWhen'] as $method) {
            $guard = $this->guard();
            $guard->login(new GenericUser(['id' => 1, 'password' => 'hash', 'remember_token' => null]));
            AuthenticatedVia::markMicrosoft($this->session);
            $id = $this->session->getId();
            match ($method) {
                'login' => $guard->login(new GenericUser(['id' => 2, 'password' => 'hash', 'remember_token' => null])),
                'loginUsingId' => $guard->loginUsingId(2),
                'attempt' => $guard->attempt(['email' => 'b@example.test', 'password' => 'secret']),
                'attemptWhen' => $guard->attemptWhen(['email' => 'b@example.test', 'password' => 'secret'], fn () => true),
            };
            self::assertSame(2, $guard->id(), $method);
            self::assertFalse(AuthenticatedVia::isMicrosoft($this->session), $method);
            self::assertNotSame($id, $this->session->getId(), $method);
        }
    }

    // S2c: a recaller must not inherit an earlier OAuth exemption.
    public function test_remember_me_clears_the_marker(): void
    {
        $guard = $this->guard();
        AuthenticatedVia::markMicrosoft($this->session);
        $this->request->cookies->set($guard->getRecallerName(), serialize('2|remember|hash')); // Recaller accepts a serialized value; a plain one hits its @unserialize(), which PHPUnit reports as a warning (failOnWarning)
        self::assertSame(2, $guard->user()->getAuthIdentifier());
        self::assertTrue($guard->viaRemember());
        self::assertFalse(AuthenticatedVia::isMicrosoft($this->session));
    }

    // S2c: normal logout clears even before controller session invalidation.
    public function test_logout_clears_the_marker(): void
    {
        $guard = $this->guard();
        $guard->loginUsingId(2);
        AuthenticatedVia::markMicrosoft($this->session);
        $guard->logout();
        self::assertFalse(AuthenticatedVia::isMicrosoft($this->session));
    }

    // S2c: no truthiness or partial-value bypass.
    public function test_only_the_exact_marker_value_is_accepted(): void
    {
        foreach ([null, '', true, 1, 'Microsoft', ['microsoft'], 'microsoft '] as $value) {
            $this->session->put(AuthenticatedVia::SESSION_KEY, $value);
            self::assertFalse(AuthenticatedVia::isMicrosoft($this->session));
        }
        AuthenticatedVia::markMicrosoft($this->session);
        self::assertTrue(AuthenticatedVia::isMicrosoft($this->session));
    }

    // S2c: forged Livewire property updates cannot hydrate session-marker state.
    public function test_livewire_cannot_hydrate_a_session_marker_property(): void
    {
        Container::getInstance()->instance('config', new \Illuminate\Config\Repository);
        $component = new \Modules\Core\Livewire\SessionKeeper;
        $component->setName('session-keeper');
        $handler = (new ReflectionClass(\Livewire\Mechanisms\HandleComponents\HandleComponents::class))->newInstanceWithoutConstructor();
        foreach (['core.auth_via', 'auth_via', 'session.core.auth_via'] as $key) {
            try {
                $handler->updateProperty($component, $key, 'microsoft', new \Livewire\Mechanisms\HandleComponents\ComponentContext($component));
                self::fail('Forged property accepted');
            } catch (\Livewire\Exceptions\PublicPropertyNotFoundException) {
                self::assertFalse(AuthenticatedVia::isMicrosoft($this->session));
            }
        }
    }

    // S2c: the real Azure provider rejects missing, wrong and replayed OAuth state offline.
    public function test_real_provider_validates_state_and_consumes_it_once(): void
    {
        foreach ([null, 'wrong'] as $state) {
            if ($state !== null) {
                $this->session->put('state', 'expected');
                $this->request->query->set('state', $state);
            }
            $http = new MockHandler;
            $provider = new Provider($this->request, 'id', 'secret', 'https://example.test/callback');
            $provider->setHttpClient(new Client(['handler' => HandlerStack::create($http)]));
            try {
                $provider->user();
                self::fail('Invalid state accepted');
            } catch (InvalidStateException) {
                self::assertNull($http->getLastRequest());
            }
        }
        $this->session->put('state', 'expected');
        $this->request->query->set('state', 'expected');
        $http = new MockHandler([
            new Response(200, [], json_encode(['access_token' => 'test-token'])),
            new Response(200, [], json_encode(['id' => 'oid', 'displayName' => 'B', 'userPrincipalName' => 'b@example.test', 'mail' => 'b@example.test'])),
        ]);
        $provider = new Provider($this->request, 'id', 'secret', 'https://example.test/callback');
        $provider->setHttpClient(new Client(['handler' => HandlerStack::create($http)]));
        self::assertSame('b@example.test', $provider->user()->getEmail());
        self::assertFalse($this->session->has('state'));
        $replay = new Provider($this->request, 'id', 'secret', 'https://example.test/callback');
        $this->expectException(InvalidStateException::class);
        $replay->user();
    }
}
