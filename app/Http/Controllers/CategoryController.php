<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\InventoryStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::withCount('items')->orderBy('name')->get(['id', 'name']);

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request, InventoryStorage $storage): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')],
        ]);
        $category = $storage->synchronized(fn () => Category::create($data));

        return response()->json(['data' => $category], 201);
    }

    public function update(Request $request, Category $category, InventoryStorage $storage): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($category)],
        ]);
        $storage->synchronized(fn () => $category->update($data));

        return response()->json(['data' => $category]);
    }

    public function destroy(Category $category, InventoryStorage $storage): Response
    {
        $storage->synchronized(function () use ($category): void {
            abort_if($category->items()->exists(), 422, 'Move or delete the items in this category first.');
            $category->delete();
        });

        return response()->noContent();
    }
}
