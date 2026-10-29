<?php

use App\Models\Category;
use App\Models\InventoryItem;
use App\Models\Position;
use App\Models\Section;
use App\Services\InventoryAccount;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);
beforeEach(function () {
    config(['soloinventory.username' => 'test-home', 'soloinventory.password_hash' => Hash::make('test-password-123')]);
    $this->withSession(['soloinventory_account' => app(InventoryAccount::class)->fingerprint()]);
});

test('an item requires name quantity section and category', function () {
    $this->postJson('/api/v1/items', [])->assertUnprocessable()->assertJsonValidationErrors([
        'name' => 'Enter an item name.',
        'quantity' => 'Enter a quantity.',
        'section_id' => 'Select a section.',
        'category_id' => 'Select a category.',
    ]);
    $this->assertDatabaseCount('inventory_items', 0);
});

test('minimal items and duplicate names and serials are independent', function () {
    Storage::fake('soloinventory');
    $section = Section::factory()->create();
    $category = Category::factory()->create();
    $data = ['quantity' => 1, 'name' => 'Cavo USB', 'section_id' => $section->id, 'category_id' => $category->id, 'serial' => '000ABC'];
    $first = $this->postJson('/api/v1/items', $data)->assertCreated()
        ->assertJsonPath('data.section_id', $section->id)->assertJsonPath('data.category_id', $category->id)->json('data.id');
    $second = $this->postJson('/api/v1/items', $data)->assertCreated()->json('data.id');

    $this->putJson('/api/v1/items/'.$first, [...$data, 'name' => 'Cavo aggiornato'])->assertOk();
    $this->getJson('/api/v1/items/'.$second)->assertOk()->assertJsonPath('data.name', 'Cavo USB')->assertJsonPath('data.serial', '000ABC');
    $this->assertDatabaseCount('inventory_items', 2);
    $this->assertDatabaseHas('inventory_items', ['id' => $first, 'name' => 'Cavo aggiornato', 'purchased_on' => null, 'photo_path' => null]);
});

test('all fields and section moves are saved with positions scoped to the section', function () {
    Storage::fake('soloinventory');
    $oldSection = Section::factory()->create();
    $newSection = Section::factory()->create();
    $category = Category::factory()->create();
    $newCategory = Category::factory()->create();
    Position::factory()->create(['section_id' => $oldSection->id, 'name' => 'Solo vecchia']);
    $data = ['quantity' => 1, 'name' => 'Radio', 'section_id' => $oldSection->id, 'category_id' => $category->id, 'position' => 'Cassetto A'];
    $id = $this->postJson('/api/v1/items', $data)->assertCreated()->json('data.id');

    $this->putJson('/api/v1/items/'.$id, [
        ...$data, 'name' => 'Radio portatile', 'section_id' => $newSection->id, 'category_id' => $newCategory->id,
        'serial' => '00-RADIO', 'purchased_on' => '2020-02-29', 'expires_on' => '2021-01-01',
    ])->assertOk()->assertJsonPath('data.purchased_on', '2020-02-29')->assertJsonPath('data.expires_on', '2021-01-01')->assertJsonPath('data.serial', '00-RADIO');

    $this->getJson('/api/v1/positions?section_id='.$newSection->id)->assertOk()->assertExactJson(['data' => ['Cassetto A']]);
    $this->getJson('/api/v1/positions?section_id='.$oldSection->id)->assertOk()->assertJsonCount(2, 'data');
    $this->assertDatabaseHas('inventory_items', ['id' => $id, 'section_id' => $newSection->id, 'category_id' => $newCategory->id]);
    $this->assertDatabaseHas('positions', ['section_id' => $newSection->id, 'name' => 'Cassetto A']);
    $this->assertDatabaseHas('positions', ['section_id' => $oldSection->id, 'name' => 'Cassetto A']);
});

test('search and combined filters find names or serials without SQL wildcards', function () {
    $section = Section::factory()->create();
    $category = Category::factory()->create();
    $target = InventoryItem::factory()->create(['name' => 'Trapano 100%', 'serial' => '000ZX', 'position' => 'Cassetto A', 'section_id' => $section->id, 'category_id' => $category->id]);
    InventoryItem::factory()->create(['name' => 'Trapano normale', 'position' => 'Cassetto A', 'category_id' => $category->id]);
    InventoryItem::factory()->create(['name' => 'Radio', 'position' => 'Cassetto B', 'section_id' => $section->id, 'category_id' => $category->id]);

    $this->getJson('/api/v1/items?'.http_build_query(['q' => '000zx', 'section_id' => $section->id, 'category_id' => $category->id, 'position' => 'Cassetto A']))
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
    $this->getJson('/api/v1/items?q=100%25')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/items?q=%27%20OR%201%3D1')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/items?position=Cassetto+A')->assertUnprocessable();
});

test('photo can be added replaced removed and permanently deleted', function () {
    Storage::fake('soloinventory');
    $section = Section::factory()->create();
    $category = Category::factory()->create();
    $data = ['quantity' => 1, 'name' => 'Fotocamera', 'section_id' => $section->id, 'category_id' => $category->id];
    $id = $this->post('/api/v1/items', [...$data, 'photo' => UploadedFile::fake()->image('camera.jpg')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    $firstPath = InventoryItem::findOrFail($id)->photo_path;
    Storage::disk('soloinventory')->assertExists($firstPath);

    $this->post('/api/v1/items/'.$id, [...$data, '_method' => 'PUT', 'photo' => UploadedFile::fake()->image('new.png')], ['Accept' => 'application/json'])->assertOk();
    $secondPath = InventoryItem::findOrFail($id)->photo_path;
    Storage::disk('soloinventory')->assertMissing($firstPath);
    Storage::disk('soloinventory')->assertExists($secondPath);

    $this->putJson('/api/v1/items/'.$id, [...$data, 'remove_photo' => true])->assertOk()->assertJsonPath('data.photo_url', null);
    Storage::disk('soloinventory')->assertMissing($secondPath);
    $this->getJson('/api/v1/items/'.$id.'/photo')->assertNotFound();

    $this->post('/api/v1/items/'.$id, [...$data, '_method' => 'PUT', 'photo' => UploadedFile::fake()->image('last.webp')], ['Accept' => 'application/json'])->assertOk();
    $finalPath = InventoryItem::findOrFail($id)->photo_path;
    $this->deleteJson('/api/v1/items/'.$id, ['confirm' => true])->assertNoContent();
    $this->assertDatabaseMissing('inventory_items', ['id' => $id]);
    Storage::disk('soloinventory')->assertMissing($finalPath);
});

test('deletion requires explicit confirmation and leaves other duplicate cards intact', function () {
    Storage::fake('soloinventory');
    $first = InventoryItem::factory()->create(['name' => 'Duplicato', 'serial' => '0001', 'photo_path' => 'photos/a.jpg']);
    $second = InventoryItem::factory()->create(['name' => 'Duplicato', 'serial' => '0001']);
    Storage::disk('soloinventory')->put('photos/a.jpg', 'photo');

    $this->deleteJson('/api/v1/items/'.$first->id, ['confirm' => false])->assertUnprocessable();
    $this->assertModelExists($first);
    Storage::disk('soloinventory')->assertExists('photos/a.jpg');
    $this->deleteJson('/api/v1/items/'.$first->id, ['confirm' => true])->assertNoContent();
    $this->assertModelMissing($first);
    $this->assertModelExists($second);
    Storage::disk('soloinventory')->assertMissing('photos/a.jpg');
});

test('invalid fields cannot change an existing item', function (array $invalid, string $field) {
    $item = InventoryItem::factory()->create(['name' => 'Originale']);
    $data = ['quantity' => 1, 'name' => 'Originale', 'section_id' => $item->section_id, 'category_id' => $item->category_id];
    $this->putJson('/api/v1/items/'.$item->id, [...$data, ...$invalid])->assertUnprocessable()->assertJsonValidationErrors([$field]);
    $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'name' => 'Originale']);
})->with([
    'unknown section' => [['section_id' => 99999], 'section_id'],
    'unknown category' => [['category_id' => 99999], 'category_id'],
    'invalid date' => [['purchased_on' => '2025-02-30'], 'purchased_on'],
    'date with time' => [['expires_on' => '2025-02-01T10:00:00Z'], 'expires_on'],
    'long name' => [['name' => str_repeat('a', 201)], 'name'],
    'long serial' => [['serial' => str_repeat('a', 201)], 'serial'],
    'long position' => [['position' => str_repeat('a', 151)], 'position'],
]);

test('unsafe and oversized photos are refused without saving an item', function (string $kind) {
    Storage::fake('soloinventory');
    $section = Section::factory()->create();
    $category = Category::factory()->create();
    $photo = match ($kind) {
        'svg' => UploadedFile::fake()->createWithContent('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'text' => UploadedFile::fake()->createWithContent('fake.jpg', 'not an image'),
        'size' => UploadedFile::fake()->image('large.jpg')->size(12289),
        'dimensions' => UploadedFile::fake()->image('wide.jpg', 8001, 1),
    };
    $this->post('/api/v1/items', ['name' => 'Foto', 'quantity' => 1, 'section_id' => $section->id, 'category_id' => $category->id, 'photo' => $photo], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['photo']);
    $this->assertDatabaseCount('inventory_items', 0);
    expect(Storage::disk('soloinventory')->allFiles('photos'))->toBe([]);
})->with(['svg', 'text', 'size', 'dimensions']);

test('request fields cannot control a stored photo path or record id', function () {
    Storage::fake('soloinventory');
    $section = Section::factory()->create();
    $category = Category::factory()->create();
    $this->postJson('/api/v1/items', ['name' => '<script>alert(1)</script>', 'quantity' => 1, 'section_id' => $section->id, 'category_id' => $category->id, 'photo_path' => '/etc/passwd', 'id' => 999])
        ->assertCreated()->assertJsonPath('data.photo_url', null);
    $this->assertDatabaseMissing('inventory_items', ['id' => 999]);
    $this->assertDatabaseHas('inventory_items', ['name' => '<script>alert(1)</script>', 'photo_path' => null]);
});

test('sections and shared categories can be created renamed and removed only when empty', function (string $kind) {
    Storage::fake('soloinventory');
    $id = $this->postJson('/api/v1/'.$kind, ['name' => 'Nuova voce'])->assertCreated()->json('data.id');
    $this->putJson('/api/v1/'.$kind.'/'.$id, ['name' => 'Voce rinominata'])->assertOk()->assertJsonPath('data.name', 'Voce rinominata');
    $this->assertDatabaseHas($kind, ['id' => $id, 'name' => 'Voce rinominata']);
    $item = InventoryItem::factory()->create([$kind === 'sections' ? 'section_id' : 'category_id' => $id]);
    $this->deleteJson('/api/v1/'.$kind.'/'.$id)->assertUnprocessable();
    $this->assertModelExists($item);
    $this->deleteJson('/api/v1/items/'.$item->id, ['confirm' => true])->assertNoContent();
    $this->deleteJson('/api/v1/'.$kind.'/'.$id)->assertNoContent();
    $this->assertDatabaseMissing($kind, ['id' => $id]);
})->with(['sections', 'categories']);

test('unicode names and the digit zero remain searchable', function () {
    InventoryItem::factory()->create(['name' => 'CAFFÈ', 'serial' => '0001']);
    $this->getJson('/api/v1/items?'.http_build_query(['q' => 'caffè']))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/items?q=0')->assertOk()->assertJsonCount(1, 'data');
});

test('quantity is saved exposed as an integer and editable independently', function () {
    Storage::fake('soloinventory');
    $section = Section::factory()->create();
    $category = Category::factory()->create();
    $data = ['name' => 'Pile AA', 'quantity' => '12', 'section_id' => $section->id, 'category_id' => $category->id];
    $other = InventoryItem::factory()->create(['name' => 'Pile AA', 'quantity' => 3]);

    $id = $this->postJson('/api/v1/items', $data)->assertCreated()->assertJsonPath('data.quantity', 12)->json('data.id');
    $this->getJson('/api/v1/items/'.$id)->assertOk()->assertJsonPath('data.quantity', 12);
    $this->putJson('/api/v1/items/'.$id, [...$data, 'quantity' => 5])->assertOk()->assertJsonPath('data.quantity', 5);

    $this->assertDatabaseHas('inventory_items', ['id' => $id, 'quantity' => 5]);
    $this->assertDatabaseHas('inventory_items', ['id' => $other->id, 'quantity' => 3]);
});

test('invalid quantities return 422 without changing the item', function (mixed $quantity, string $message) {
    $item = InventoryItem::factory()->create(['quantity' => 4]);
    $data = ['name' => $item->name, 'quantity' => $quantity, 'section_id' => $item->section_id, 'category_id' => $item->category_id];

    $this->putJson('/api/v1/items/'.$item->id, $data)->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity' => $message]);

    $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'quantity' => 4]);
})->with([
    'empty' => [null, 'Enter a quantity.'],
    'zero' => [0, 'Quantity must be at least 1.'],
    'negative' => [-1, 'Quantity must be at least 1.'],
    'fraction' => [1.5, 'Quantity must be a whole number.'],
    'text' => ['molte', 'Quantity must be a whole number.'],
    'too large' => [1000000, 'Quantity must not exceed 999999.'],
]);
