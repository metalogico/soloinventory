<?php

use App\Models\InventoryItem;
use App\Services\InventoryAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    config(['soloinventory.username' => 'test-home', 'soloinventory.password_hash' => Hash::make('test-password-123')]);
    RateLimiter::clear('soloinventory-login|127.0.0.1');
});

test('private endpoints return 401 without the shared session', function (string $method, string $path) {
    $item = InventoryItem::factory()->create();
    $this->json($method, str_replace('{id}', (string) $item->id, $path))
        ->assertUnauthorized();
    $this->assertModelExists($item);
})->with([
    ['GET', '/api/v1/items'], ['GET', '/api/v1/items/{id}'], ['GET', '/api/v1/items/{id}/photo'],
    ['POST', '/api/v1/items'], ['PUT', '/api/v1/items/{id}'], ['DELETE', '/api/v1/items/{id}'],
    ['GET', '/api/v1/sections'], ['POST', '/api/v1/sections'], ['PUT', '/api/v1/sections/{id}'], ['DELETE', '/api/v1/sections/{id}'],
    ['GET', '/api/v1/categories'], ['POST', '/api/v1/categories'], ['PUT', '/api/v1/categories/{id}'], ['DELETE', '/api/v1/categories/{id}'],
    ['GET', '/api/v1/positions?section_id=1'],
]);

test('login and logout use the configured account without creating users', function () {
    $this->postJson('/api/v1/session', ['username' => 'test-home', 'password' => 'test-password-123'])
        ->assertOk()->assertJsonPath('authenticated', true)->assertJsonStructure(['csrf_token']);
    $this->getJson('/api/v1/items')->assertOk();
    $this->deleteJson('/api/v1/session')->assertOk()->assertJsonPath('authenticated', false);
    $this->getJson('/api/v1/items')->assertUnauthorized();
    $this->assertDatabaseCount('users', 0);
});

test('invalid credentials cannot open a session', function () {
    $this->postJson('/api/v1/session', ['username' => 'test-home', 'password' => 'wrong'])
        ->assertUnprocessable()->assertJsonValidationErrors(['username' => 'Incorrect username or password.']);
    $this->getJson('/api/v1/items')->assertUnauthorized();
});

test('an unconfigured account fails closed', function () {
    config(['soloinventory.password_hash' => '']);
    $this->getJson('/api/v1/session')->assertOk()->assertJsonPath('configured', false);
    $this->postJson('/api/v1/session', ['username' => 'test-home', 'password' => 'test-password-123'])
        ->assertUnprocessable();
});

test('changing credentials invalidates an existing session', function () {
    $this->withSession(['soloinventory_account' => app(InventoryAccount::class)->fingerprint()]);
    config(['soloinventory.password_hash' => Hash::make('new-password-456')]);
    $this->getJson('/api/v1/items')->assertUnauthorized();
});

test('login attempts are limited', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/session', ['username' => 'test-home', 'password' => 'wrong'])->assertUnprocessable();
    }
    $this->postJson('/api/v1/session', ['username' => 'test-home', 'password' => 'wrong'])->assertTooManyRequests();
});

test('private photos are not available through a public storage URL', function () {
    Storage::fake('soloinventory');
    Storage::disk('soloinventory')->put('photos/private.jpg', 'private bytes');
    $item = InventoryItem::factory()->create(['photo_path' => 'photos/private.jpg']);

    $this->get('/storage/photos/private.jpg')->assertNotFound();
    $this->withSession(['soloinventory_account' => app(InventoryAccount::class)->fingerprint()])
        ->get('/api/v1/items/'.$item->id.'/photo')->assertOk()->assertHeaderContains('Cache-Control', 'no-store');
});

test('unknown private records also require login', function () {
    $this->getJson('/api/v1/items/999999')->assertUnauthorized();
});

test('login refuses missing CSRF tokens and accepts a valid token', function () {
    $this->app['env'] = 'local';
    $token = $this->getJson('/api/v1/session')->assertOk()->json('csrf_token');
    $this->postJson('/api/v1/session', ['username' => 'test-home', 'password' => 'test-password-123'])
        ->assertStatus(419);
    $this->withHeader('X-CSRF-TOKEN', $token)
        ->postJson('/api/v1/session', ['username' => 'test-home', 'password' => 'test-password-123'])
        ->assertOk()->assertJsonPath('authenticated', true);
});
