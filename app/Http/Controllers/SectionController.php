<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Services\InventoryStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class SectionController extends Controller
{
    public function index(): JsonResponse
    {
        $sections = Section::withCount('items')->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $sections]);
    }

    public function store(Request $request, InventoryStorage $storage): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sections')],
        ]);
        $section = $storage->synchronized(fn () => Section::create($data));

        return response()->json(['data' => $section], 201);
    }

    public function update(Request $request, Section $section, InventoryStorage $storage): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sections')->ignore($section)],
        ]);
        $storage->synchronized(fn () => $section->update($data));

        return response()->json(['data' => $section]);
    }

    public function destroy(Section $section, InventoryStorage $storage): Response
    {
        $storage->synchronized(function () use ($section): void {
            abort_if($section->items()->exists(), 422, 'Move or delete the items in this section first.');
            $section->delete();
        });

        return response()->noContent();
    }
}
