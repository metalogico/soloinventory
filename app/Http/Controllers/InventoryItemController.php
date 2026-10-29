<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveInventoryItemRequest;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Services\InventoryStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'position' => ['nullable', 'string', 'max:150'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        abort_if(
            isset($data['position']) && ! isset($data['section_id']),
            422,
            'Select a section to filter by location.',
        );
        $items = InventoryItem::query()->with(['section', 'category']);

        if (isset($data['q'])) {
            $term = mb_strtolower($data['q']);
            $items->where(function (Builder $query) use ($term): void {
                $query->whereRaw('instr(unicode_lower(name), ?) > 0', [$term])
                    ->orWhereRaw('instr(unicode_lower(coalesce(serial, ?)), ?) > 0', ['', $term]);
            });
        }

        foreach (['section_id', 'category_id', 'position'] as $filter) {
            if (isset($data[$filter])) {
                $items->where($filter, $data[$filter]);
            }
        }

        return InventoryItemResource::collection(
            $items->orderByDesc('updated_at')->orderByDesc('id')->paginate(24),
        );
    }

    public function store(SaveInventoryItemRequest $request, InventoryStorage $storage): InventoryItemResource
    {
        $item = $storage->save(
            null,
            $request->safe()->except(['photo', 'remove_photo']),
            $request->file('photo'),
            false,
        );

        return new InventoryItemResource($item);
    }

    public function show(InventoryItem $item): InventoryItemResource
    {
        return new InventoryItemResource($item->load(['section', 'category']));
    }

    public function update(
        SaveInventoryItemRequest $request,
        InventoryItem $item,
        InventoryStorage $storage,
    ): InventoryItemResource {
        $item = $storage->save(
            $item,
            $request->safe()->except(['photo', 'remove_photo']),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        );

        return new InventoryItemResource($item);
    }

    public function destroy(Request $request, InventoryItem $item, InventoryStorage $storage): Response
    {
        $request->validate(['confirm' => ['required', 'accepted']]);
        $storage->delete($item);

        return response()->noContent();
    }

    public function photo(InventoryItem $item): StreamedResponse
    {
        abort_unless($item->photo_path && Storage::disk('soloinventory')->exists($item->photo_path), 404);

        return Storage::disk('soloinventory')->response($item->photo_path, null, [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
