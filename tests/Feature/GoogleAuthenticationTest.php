<?php

use App\Models\User;
use Firebase\JWT\JWT;
use Google\Client;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.google.client_id' => 'test-client.apps.googleusercontent.com']);
});

function googleTestClaims(array $overrides = []): array
{
    return array_replace([
        'aud' => config('services.google.client_id'),
        'iss' => 'https://accounts.google.com',
        'exp' => time() + 3600,
        'iat' => time() - 10,
        'nonce' => 'test-session-nonce',
        'sub' => 'google-subject-123',
        'email' => 'member@kkumail.com',
        'email_verified' => true,
        'hd' => 'kkumail.com',
    ], $overrides);
}

function googleMockToken(array|false $payload): void
{
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('verifyIdToken')->once()->with('test-token')->andReturn($payload);
    app()->instance(Client::class, $client);
}

function googleSession(): array
{
    return ['google_login_nonce' => 'test-session-nonce', 'google_login_expires_at' => time() + 600];
}

test('login has required Thai text and never offers password or registration', function () {
    $this->get('/login')->assertOk()
        ->assertSee('เข้าสู่ระบบด้วย Google')
        ->assertSee('เข้าสู่ระบบด้วยบัญชี KKU')
        ->assertSee('สำหรับนักศึกษามหาวิทยาลัยขอนแก่น กรุณาใช้บัญชี KKU Mail')
        ->assertSee('login-loading')->assertSee('login-error')
        ->assertDontSee('type="password"', false);
    expect(session('google_login_nonce'))->toHaveLength(64);
    $this->get('/register')->assertNotFound();
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/login', ['email' => 'member@kkumail.com', 'password' => 'anything'])->assertStatus(405);
});

test('valid verified KKU member logs in with a new session and backend role', function (string $domain) {
    $email = 'member@'.$domain;
    $user = User::factory()->create(['email' => $email, 'password' => null]);
    googleMockToken(googleTestClaims(['email' => $email, 'hd' => $domain]));
    $this->withSession(googleSession());
    $oldSessionId = session()->getId();
    $this->postJson('/auth/google', [
        'credential' => 'test-token', 'email' => 'attacker@kku.ac.th', 'role' => 'superadmin',
    ])->assertOk()->assertJsonPath('redirect', route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('google-subject-123')
        ->and($user->fresh()->role)->toBe('user')
        ->and($user->fresh()->password)->toBeNull()
        ->and(session()->getId())->not->toBe($oldSessionId)
        ->and(session()->has('google_login_nonce'))->toBeFalse()
        ->and(json_encode(session()->all()))->not->toContain('test-token');
    $this->get('/dashboard')->assertOk()->assertSee($email);
})->with(['kkumail.com', 'kku.ac.th']);

test('invalid token claims fail closed', function (array $overrides) {
    User::factory()->create(['email' => 'member@kkumail.com']);
    googleMockToken(googleTestClaims($overrides));
    $this->withSession(googleSession())->postJson('/auth/google', ['credential' => 'test-token'])->assertStatus(422);
    $this->assertGuest();
})->with([
    'wrong audience' => [['aud' => 'another-client']],
    'wrong issuer' => [['iss' => 'https://evil.example']],
    'expired' => [['exp' => time() - 60]],
    'wrong nonce' => [['nonce' => 'other-session']],
    'unverified email' => [['email_verified' => false]],
    'missing subject' => [['sub' => '']],
    'invalid email' => [['email' => 'not-an-email']],
]);

test('non KKU domains and untrusted Workspace claims are rejected', function (array $overrides) {
    googleMockToken(googleTestClaims($overrides));
    $this->withSession(googleSession())->postJson('/auth/google', ['credential' => 'test-token'])
        ->assertForbidden()->assertJsonPath('message', 'กรุณาเข้าสู่ระบบด้วยบัญชี KKU เท่านั้น');
    $this->assertGuest();
})->with([
    'gmail' => [['email' => 'member@gmail.com', 'hd' => 'gmail.com']],
    'suffix attack' => [['email' => 'member@kkumail.com.evil.example', 'hd' => 'kkumail.com']],
    'missing hosted domain' => [['hd' => null]],
    'foreign hosted domain' => [['hd' => 'evil.example']],
]);

test('unknown and inactive accounts never register automatically', function (bool $inactive) {
    if ($inactive) {
        User::factory()->create(['email' => 'member@kkumail.com', 'is_active' => false]);
    }
    googleMockToken(googleTestClaims());
    $this->withSession(googleSession())->postJson('/auth/google', ['credential' => 'test-token'])->assertForbidden();
    $this->assertGuest();
    expect(User::count())->toBe($inactive ? 1 : 0);
})->with([false, true]);

test('Google subject cannot be switched or attached to another member', function (bool $otherMember) {
    User::factory()->create([
        'email' => 'member@kkumail.com', 'google_id' => $otherMember ? null : 'previous-subject',
    ]);
    if ($otherMember) {
        User::factory()->create(['email' => 'other@kkumail.com', 'google_id' => 'google-subject-123']);
    }
    googleMockToken(googleTestClaims());
    $this->withSession(googleSession())->postJson('/auth/google', ['credential' => 'test-token'])->assertForbidden();
    $this->assertGuest();
})->with([false, true]);

test('nonce is required unexpired and consumed even on failed verification', function () {
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('verifyIdToken')->once()->andReturn(false);
    app()->instance(Client::class, $client);
    $this->postJson('/auth/google', ['credential' => 'test-token'])->assertStatus(419);
    $this->withSession(['google_login_nonce' => 'test-session-nonce', 'google_login_expires_at' => time() - 1])
        ->postJson('/auth/google', ['credential' => 'test-token'])->assertStatus(419);
    $this->withSession(googleSession())->postJson('/auth/google', ['credential' => 'test-token'])->assertStatus(422);
    $this->postJson('/auth/google', ['credential' => 'test-token'])->assertStatus(419);
});

test('logout invalidates session and guests cannot access authenticated page', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['private_value' => 'secret']);
    $oldToken = session()->token();
    $this->post('/logout')->assertRedirect(route('login'));
    $this->assertGuest();
    expect(session()->has('private_value'))->toBeFalse()->and(session()->token())->not->toBe($oldToken);
    $this->get('/logout')->assertStatus(405);
});

test('deactivated member loses existing authenticated session', function () {
    $user = User::factory()->create(['is_active' => false]);
    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('missing configuration and malformed credential produce safe errors', function () {
    config(['services.google.client_id' => null]);
    $this->get('/login')->assertOk()->assertSee('ระบบยังไม่พร้อมให้เข้าสู่ระบบ');
    $this->postJson('/auth/google', ['credential' => 'test-token'])->assertStatus(503);
    config(['services.google.client_id' => 'test-client']);
    $this->postJson('/auth/google', ['email' => 'member@kkumail.com'])->assertStatus(422);
});

test('login endpoint enforces request throttling', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/auth/google', [])->assertStatus(422);
    }
    $this->postJson('/auth/google', [])->assertStatus(429);
});

test('CSRF rejects login and logout requests without a token', function () {
    $this->app['env'] = 'production';
    $this->postJson('/auth/google', ['credential' => 'test-token'])->assertStatus(419);
    $this->actingAs(User::factory()->create())->post('/logout')->assertStatus(419);
    Auth::logout();
});

test('real Google library verifies RSA signature audience issuer and expiry', function (string $scenario) {
    $privateKey = file_get_contents(base_path('tests/Fixtures/google-test-key.pem'));
    $key = openssl_pkey_get_private($privateKey);
    expect($key)->not->toBeFalse();
    $details = openssl_pkey_get_details($key);
    $jwk = [
        'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-key',
        'n' => JWT::urlsafeB64Encode($details['rsa']['n']),
        'e' => JWT::urlsafeB64Encode($details['rsa']['e']),
    ];
    $claims = googleTestClaims();
    if ($scenario === 'audience') {
        $claims['aud'] = 'other-client';
    } elseif ($scenario === 'issuer') {
        $claims['iss'] = 'https://evil.example';
    } elseif ($scenario === 'expired') {
        $claims['exp'] = time() - 120;
    } elseif ($scenario === 'signature') {
        $privateKey = file_get_contents(base_path('tests/Fixtures/google-other-key.pem'));
    }
    $token = JWT::encode($claims, $privateKey, 'RS256', 'test-key');
    $client = new Client(['client_id' => config('services.google.client_id')]);
    $client->setHttpClient(new HttpClient([
        'handler' => HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['keys' => [$jwk]])),
        ])),
    ]));
    app()->instance(Client::class, $client);
    $user = User::factory()->create(['email' => 'member@kkumail.com', 'password' => null]);
    $result = $this->withSession(googleSession())->postJson('/auth/google', ['credential' => $token]);
    if ($scenario === 'valid') {
        $result->assertOk();
        $this->assertAuthenticatedAs($user);
    } else {
        $result->assertStatus(422);
        $this->assertGuest();
    }
})->with(['valid', 'signature', 'audience', 'issuer', 'expired']);
